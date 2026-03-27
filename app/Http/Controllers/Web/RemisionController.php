<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Remision;
use App\Models\RemisionDetalle;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Empresa;
use App\Services\PDFService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RemisionController extends Controller
{
    /**
     * Asegura que `remisiones_detalle` tenga snapshot tipo cotización.
     * Esto evita que el PDF/visual dependa de cambios futuros en `productos`.
     */
    private function asegurarSnapshotDetalles(Remision $remision): void
    {
        $remision->loadMissing(['detalles.producto']);

        foreach ($remision->detalles as $detalle) {
            if (! $detalle->producto_id) {
                continue;
            }

            // Si ya está completo, no toca nada.
            // Nota: para exento `tasa_iva` puede ser NULL; por eso no se exige.
            if ($detalle->precio_unitario !== null && $detalle->total !== null) {
                continue;
            }

            $producto = $detalle->producto;
            if (! $producto) {
                continue;
            }

            $cantidad = (float) ($detalle->cantidad ?? 0);
            $precioUnitario = (float) ($producto->precio_venta ?? 0);
            $tipoFactor = $producto->tipo_factor ?? 'Tasa';
            $tasaIva = $tipoFactor === 'Exento' ? null : (float) ($producto->tasa_iva ?? 0);

            $unidad = $producto->unidad ?? 'PZA';
            $subtotal = round($cantidad * $precioUnitario, 2);
            $ivaMonto = $tasaIva === null ? 0.0 : round($subtotal * (float) $tasaIva, 2);
            $total = round($subtotal + $ivaMonto, 2);

            $detalle->fill([
                'codigo' => $producto->codigo,
                'descripcion' => $producto->nombre,
                'unidad' => $unidad,
                'precio_unitario' => $precioUnitario,
                'tasa_iva' => $tasaIva,
                'subtotal' => $subtotal,
                'iva_monto' => $ivaMonto,
                'total' => $total,
            ]);

            $detalle->save();
        }
    }

    public function index(Request $request)
    {
        $query = Remision::with(['cliente', 'usuario', 'factura', 'facturaCancelada']);
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('search')) {
            $query->buscar($request->search);
        }
        $remisiones = $query->orderBy('created_at', 'desc')->paginate(20);
        $estadisticas = [
            'borrador' => Remision::where('estado', 'borrador')->count(),
            'enviada' => Remision::where('estado', 'enviada')->count(),
            'entregada' => Remision::where('estado', 'entregada')->count(),
            'cancelada' => Remision::where('estado', 'cancelada')->count(),
        ];
        return view('remisiones.index', compact('remisiones', 'estadisticas'));
    }

    public function create()
    {
        $empresa = Empresa::principal();
        if (!$empresa) {
            return redirect()->route('dashboard')->with('error', 'Configura la empresa primero');
        }
        $folio = $empresa ? $empresa->obtenerSiguienteFolioRemision() : 'REM-0001';
        return view('remisiones.create', compact('folio'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'fecha' => 'required|date',
            'direccion_entrega' => 'nullable|string|max:2000',
            'observaciones' => 'nullable|string|max:2000',
            'orden_compra' => 'nullable|string|max:200',
            'productos' => 'required|array|min:1',
            // Seguridad: en remisiones no se permiten partidas manuales (producto_id requerido).
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.descripcion' => 'required|string|max:500',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.unidad' => 'nullable|string|max:10',
        ]);

        DB::beginTransaction();
        try {
            $cliente = Cliente::findOrFail($validated['cliente_id']);
            $empresa = Empresa::principal();

            $remision = new Remision();
            $remision->folio = Remision::generarFolio();
            $remision->estado = 'borrador';
            $remision->cliente_id = $cliente->id;
            $remision->empresa_id = $empresa?->id;
            $remision->orden_compra = $validated['orden_compra'] ?? null;
            $remision->cliente_nombre = $cliente->nombre;
            $remision->cliente_rfc = $cliente->rfc;
            $remision->fecha = $validated['fecha'];
            $remision->direccion_entrega = $validated['direccion_entrega'] ?? null;
            $remision->observaciones = $validated['observaciones'] ?? null;
            $remision->usuario_id = auth()->id();
            $remision->save();

            foreach ($validated['productos'] as $index => $item) {
                $producto = Producto::findOrFail($item['producto_id']);
                // Snapshot tipo cotización: unidad, precio e IVA se toman del producto al guardar.
                $unidad = $producto->unidad ?? 'PZA';
                $cantidad = (float) ($item['cantidad'] ?? 0);
                $precioUnitario = (float) ($producto->precio_venta ?? 0);
                $tipoFactor = $producto->tipo_factor ?? 'Tasa';
                $tasaIva = $tipoFactor === 'Exento' ? null : (float) ($producto->tasa_iva ?? 0);

                $subtotal = round($cantidad * $precioUnitario, 2);
                $ivaMonto = $tasaIva === null ? 0.0 : round($subtotal * (float) $tasaIva, 2);
                $total = round($subtotal + $ivaMonto, 2);

                RemisionDetalle::create([
                    'remision_id' => $remision->id,
                    'producto_id' => $producto->id,
                    'codigo' => $producto->codigo,
                    'descripcion' => $producto->nombre,
                    'cantidad' => $cantidad,
                    'unidad' => $unidad,
                    'precio_unitario' => $precioUnitario,
                    'tasa_iva' => $tasaIva,
                    'subtotal' => $subtotal,
                    'iva_monto' => $ivaMonto,
                    'total' => $total,
                    'orden' => $index,
                ]);
            }
            DB::commit();
            return redirect()->route('remisiones.show', $remision->id)->with('success', 'Remisión creada correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Remision $remision)
    {
        $remision->load(['cliente', 'detalles.producto', 'factura', 'facturaCancelada', 'usuario']);
        $this->asegurarSnapshotDetalles($remision);
        return view('remisiones.show', compact('remision'));
    }

    public function edit(Remision $remision)
    {
        if (!$remision->puedeEditarse()) {
            return redirect()->route('remisiones.show', $remision->id)->with('error', 'Solo se pueden editar remisiones en borrador');
        }
        $remision->load(['cliente.direccionesEntrega', 'detalles.producto']);
        $this->asegurarSnapshotDetalles($remision);
        return view('remisiones.edit', compact('remision'));
    }

    public function update(Request $request, Remision $remision)
    {
        if (!$remision->puedeEditarse()) {
            return redirect()->route('remisiones.show', $remision->id)->with('error', 'Solo se pueden editar remisiones en borrador');
        }

        $validated = $request->validate([
            'fecha' => 'required|date',
            'direccion_entrega' => 'nullable|string|max:2000',
            'observaciones' => 'nullable|string|max:2000',
            'orden_compra' => 'nullable|string|max:200',
            'productos' => 'required|array|min:1',
            // Seguridad: en remisiones no se permiten partidas manuales (producto_id requerido).
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.descripcion' => 'required|string|max:500',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.unidad' => 'nullable|string|max:10',
        ]);

        DB::beginTransaction();
        try {
            $remision->fecha = $validated['fecha'];
            $remision->direccion_entrega = $validated['direccion_entrega'] ?? null;
            $remision->observaciones = $validated['observaciones'] ?? null;
            $remision->orden_compra = $validated['orden_compra'] ?? null;
            $remision->save();

            $remision->detalles()->delete();
            foreach ($validated['productos'] as $index => $item) {
                $producto = Producto::findOrFail($item['producto_id']);
                // Snapshot tipo cotización: unidad, precio e IVA se toman del producto al guardar.
                $unidad = $producto->unidad ?? 'PZA';
                $cantidad = (float) ($item['cantidad'] ?? 0);
                $precioUnitario = (float) ($producto->precio_venta ?? 0);
                $tipoFactor = $producto->tipo_factor ?? 'Tasa';
                $tasaIva = $tipoFactor === 'Exento' ? null : (float) ($producto->tasa_iva ?? 0);

                $subtotal = round($cantidad * $precioUnitario, 2);
                $ivaMonto = $tasaIva === null ? 0.0 : round($subtotal * (float) $tasaIva, 2);
                $total = round($subtotal + $ivaMonto, 2);

                RemisionDetalle::create([
                    'remision_id' => $remision->id,
                    'producto_id' => $producto->id,
                    'codigo' => $producto->codigo,
                    'descripcion' => $producto->nombre,
                    'cantidad' => $cantidad,
                    'unidad' => $unidad,
                    'precio_unitario' => $precioUnitario,
                    'tasa_iva' => $tasaIva,
                    'subtotal' => $subtotal,
                    'iva_monto' => $ivaMonto,
                    'total' => $total,
                    'orden' => $index,
                ]);
            }
            DB::commit();
            return redirect()->route('remisiones.show', $remision->id)->with('success', 'Remisión actualizada');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(Remision $remision)
    {
        if (!$remision->puedeEditarse()) {
            return redirect()->route('remisiones.index')->with('error', 'Solo se pueden eliminar remisiones en borrador');
        }
        $remision->delete();
        return redirect()->route('remisiones.index')->with('success', 'Remisión eliminada');
    }

    public function enviar(Remision $remision)
    {
        if (!$remision->puedeEnviarse()) {
            return back()->with('error', 'Solo se puede enviar una remisión en borrador');
        }
        $remision->update(['estado' => 'enviada']);
        return back()->with('success', 'Remisión marcada como enviada');
    }

    public function entregar(Remision $remision)
    {
        if (!$remision->puedeEntregarse()) {
            return back()->with('error', 'Solo se puede marcar como entregada una remisión enviada');
        }
        \DB::beginTransaction();
        try {
            foreach ($remision->detalles as $detalle) {
                if ($detalle->producto_id && $detalle->producto && $detalle->producto->controla_inventario) {
                    \App\Models\InventarioMovimiento::registrar(
                        $detalle->producto,
                        \App\Models\InventarioMovimiento::TIPO_SALIDA_REMISION,
                        (float) $detalle->cantidad,
                        auth()->id(),
                        null,
                        $remision->id,
                        null,
                        null
                    );
                }
            }
            $remision->update(['estado' => 'entregada', 'fecha_entrega' => now()]);
            \DB::commit();
            return back()->with('success', 'Remisión marcada como entregada. Se registró la salida de inventario.');
        } catch (\Exception $e) {
            \DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancelar(Remision $remision)
    {
        $remision->loadMissing(['factura', 'facturaCancelada', 'detalles.producto']);

        if ($remision->estado === 'cancelada') {
            return back()->with('error', 'Esta remisión ya está cancelada.');
        }

        // Cancelación de remisión entregada: revierte inventario, pero se bloquea si
        // existe una factura timbrada vinculada (seguridad).
        if ($remision->estado === 'entregada') {
            $facturaTimbrada = ($remision->factura && $remision->factura->estado === 'timbrada')
                || ($remision->facturaCancelada && $remision->facturaCancelada->estado === 'timbrada');
            if ($facturaTimbrada) {
                return back()->with('error', 'No se puede cancelar: existe una factura timbrada vinculada a esta remisión.');
            }

            \DB::beginTransaction();
            try {
                foreach ($remision->detalles as $detalle) {
                    if ($detalle->producto_id && $detalle->producto && $detalle->producto->controla_inventario) {
                        \App\Models\InventarioMovimiento::registrar(
                            $detalle->producto,
                            \App\Models\InventarioMovimiento::TIPO_ENTRADA_REMISION,
                            (float) $detalle->cantidad,
                            auth()->id(),
                            null,
                            $remision->id,
                            null,
                            null,
                            'Reversa por cancelación de remisión'
                        );
                    }
                }

                $remision->update([
                    'estado' => 'cancelada',
                ]);

                \DB::commit();
                return back()->with('success', 'Remisión cancelada y productos reversados.');
            } catch (\Exception $e) {
                \DB::rollBack();
                return back()->with('error', 'Error al cancelar la remisión: ' . $e->getMessage());
            }
        }

        // Cancelación normal (borrador/enviada) - sin reversa de inventario.
        if (!$remision->puedeCancelarse()) {
            return back()->with('error', 'No se puede cancelar esta remisión');
        }

        $remision->update(['estado' => 'cancelada']);
        return back()->with('success', 'Remisión cancelada');
    }

    /**
     * Ver PDF de la remisión en el navegador.
     */
    public function verPDF(Remision $remision)
    {
        $remision->loadMissing(['detalles.producto', 'cliente', 'empresa']);
        $this->asegurarSnapshotDetalles($remision);
        $pdfPath = app(PDFService::class)->generarRemisionPDF($remision);
        return response()->file(storage_path('app/' . $pdfPath));
    }

    /**
     * Descargar PDF de la remisión.
     */
    public function descargarPDF(Remision $remision)
    {
        $remision->loadMissing(['detalles.producto', 'cliente', 'empresa']);
        $this->asegurarSnapshotDetalles($remision);
        $pdfPath = app(PDFService::class)->generarRemisionPDF($remision);
        $filename = 'Remision_' . $remision->folio . '.pdf';
        return response()->download(storage_path('app/' . $pdfPath), $filename);
    }

    public function buscarClientes(Request $request)
    {
        $search = $request->get('q', '');
        $clientes = Cliente::activos()
            ->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('rfc', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get(['id', 'nombre', 'rfc', 'codigo']);
        return response()->json($clientes);
    }

    public function buscarProductos(Request $request)
    {
        $search = $request->get('q', '');
        $productos = Producto::where('activo', true)
            ->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get(['id', 'codigo', 'nombre', 'unidad', 'precio_venta', 'tasa_iva']);
        return response()->json($productos->map(fn ($p) => [
            'id' => $p->id,
            'codigo' => $p->codigo,
            'nombre' => $p->nombre,
            'unidad' => $p->unidad ?? 'PZA',
            'precio_unitario' => $p->precio_venta ?? 0,
            'tasa_iva' => $p->tasa_iva,
        ]));
    }
}
