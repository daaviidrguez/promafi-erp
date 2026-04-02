<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\Remision;
use App\Models\RemisionDetalle;
use App\Services\LogisticaRemisionSyncService;
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
        $query = Remision::with(['cliente', 'usuario', 'factura', 'facturaCancelada', 'logisticaEnvio']);
        if ($request->filled('estado')) {
            $estado = $request->estado;
            if ($estado === 'en_ruta') {
                $query->where('estado', 'enviada')
                    ->whereHas('logisticaEnvios', fn ($q) => $q->where('estado', 'en_ruta'));
            } elseif ($estado === 'entrega_parcial') {
                $query->where('estado', 'enviada')
                    ->whereHas('logisticaEnvios', fn ($q) => $q->where('estado', 'entrega_parcial'));
            } else {
                $query->where('estado', $estado);
            }
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
        if (! $empresa) {
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
            'productos.*.precio_unitario' => 'required|numeric|min:0',
            'productos.*.unidad' => 'nullable|string|max:10',
        ]);

        DB::beginTransaction();
        try {
            $cliente = Cliente::findOrFail($validated['cliente_id']);
            $empresa = Empresa::principal();

            $remision = new Remision;
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
                // Snapshot de remisión: unidad e IVA se toman del producto al guardar.
                // El precio unitario puede ser ajustado por el usuario en la captura.
                $unidad = $producto->unidad ?? 'PZA';
                $cantidad = (float) ($item['cantidad'] ?? 0);
                $precioUnitario = (float) ($item['precio_unitario'] ?? $producto->precio_venta ?? 0);
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
        $remision->load(['cliente', 'detalles.producto', 'factura', 'facturaCancelada', 'usuario', 'logisticaEnvio']);
        $this->asegurarSnapshotDetalles($remision);

        return view('remisiones.show', compact('remision'));
    }

    public function edit(Remision $remision)
    {
        if (! $remision->puedeEditarse()) {
            return redirect()->route('remisiones.show', $remision->id)->with('error', 'Solo se pueden editar remisiones en borrador');
        }
        $remision->load(['cliente.direccionesEntrega', 'detalles.producto']);
        $this->asegurarSnapshotDetalles($remision);

        return view('remisiones.edit', compact('remision'));
    }

    public function update(Request $request, Remision $remision)
    {
        if (! $remision->puedeEditarse()) {
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
            'productos.*.precio_unitario' => 'required|numeric|min:0',
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
                // Snapshot de remisión: unidad e IVA se toman del producto al guardar.
                // El precio unitario puede ser ajustado por el usuario en la captura.
                $unidad = $producto->unidad ?? 'PZA';
                $cantidad = (float) ($item['cantidad'] ?? 0);
                $precioUnitario = (float) ($item['precio_unitario'] ?? $producto->precio_venta ?? 0);
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
        if (! $remision->puedeEditarse()) {
            return redirect()->route('remisiones.index')->with('error', 'Solo se pueden eliminar remisiones en borrador');
        }
        $remision->delete();

        return redirect()->route('remisiones.index')->with('success', 'Remisión eliminada');
    }

    public function enviar(Remision $remision)
    {
        if (! $remision->puedeEnviarse()) {
            return back()->with('error', 'Solo se puede enviar una remisión en borrador');
        }

        $remision->loadMissing(['detalles.producto']);

        DB::beginTransaction();
        try {
            foreach ($remision->detalles as $detalle) {
                if ($detalle->producto_id && $detalle->producto && $detalle->producto->controla_inventario) {
                    InventarioMovimiento::registrar(
                        $detalle->producto,
                        InventarioMovimiento::TIPO_SALIDA_REMISION,
                        (float) $detalle->cantidad,
                        auth()->id(),
                        null,
                        $remision->id,
                        null,
                        null
                    );
                }
            }

            $remision->update(['estado' => 'enviada']);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', $e->getMessage());
        }

        app(LogisticaRemisionSyncService::class)->syncFromRemision($remision->fresh());

        return back()->with('success', 'Remisión marcada como enviada. Se descontó el inventario de los productos que lo controlan.');
    }

    public function entregar(Remision $remision)
    {
        if (! $remision->puedeEntregarse()) {
            return back()->with('error', 'Solo se puede marcar como entregada una remisión enviada');
        }

        $remision->update(['estado' => 'entregada', 'fecha_entrega' => now()]);
        app(LogisticaRemisionSyncService::class)->syncFromRemision($remision->fresh());

        return back()->with('success', 'Remisión marcada como entregada. (El inventario ya se había descontado al enviarla.)');
    }

    public function cancelar(Remision $remision)
    {
        $remision->loadMissing(['factura', 'facturaCancelada', 'detalles.producto']);

        if ($remision->estado === 'cancelada') {
            return back()->with('error', 'Esta remisión ya está cancelada.');
        }

        $facturaTimbradaVinculada = ($remision->factura && $remision->factura->estado === 'timbrada')
            || ($remision->facturaCancelada && $remision->facturaCancelada->estado === 'timbrada');

        // Envío físico registrado: el inventario se descontó al marcar "enviada".
        if (in_array($remision->estado, ['enviada', 'entregada'], true)) {
            if ($facturaTimbradaVinculada) {
                return back()->with('error', 'No se puede cancelar: existe una factura timbrada vinculada a esta remisión.');
            }

            DB::beginTransaction();
            try {
                foreach ($remision->detalles as $detalle) {
                    if ($detalle->producto_id && $detalle->producto && $detalle->producto->controla_inventario) {
                        InventarioMovimiento::registrar(
                            $detalle->producto,
                            InventarioMovimiento::TIPO_ENTRADA_REMISION,
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

                $remision->update(['estado' => 'cancelada']);

                DB::commit();
                app(LogisticaRemisionSyncService::class)->syncFromRemision($remision->fresh());

                return back()->with('success', 'Remisión cancelada y se revirtió el inventario.');
            } catch (\Exception $e) {
                DB::rollBack();

                return back()->with('error', 'Error al cancelar la remisión: '.$e->getMessage());
            }
        }

        if ($remision->estado === 'borrador') {
            $remision->update(['estado' => 'cancelada']);
            app(LogisticaRemisionSyncService::class)->syncFromRemision($remision->fresh());

            return back()->with('success', 'Remisión cancelada');
        }

        return back()->with('error', 'No se puede cancelar esta remisión');
    }

    /**
     * Ver PDF de la remisión en el navegador.
     */
    public function verPDF(Remision $remision)
    {
        $remision->loadMissing(['detalles.producto', 'cliente', 'empresa']);
        $this->asegurarSnapshotDetalles($remision);
        $pdfPath = app(PDFService::class)->generarRemisionPDF($remision);

        return response()->file(storage_path('app/'.$pdfPath));
    }

    /**
     * Descargar PDF de la remisión.
     */
    public function descargarPDF(Remision $remision)
    {
        $remision->loadMissing(['detalles.producto', 'cliente', 'empresa']);
        $this->asegurarSnapshotDetalles($remision);
        $pdfPath = app(PDFService::class)->generarRemisionPDF($remision);
        $filename = 'Remision_'.$remision->folio.'.pdf';

        return response()->download(storage_path('app/'.$pdfPath), $filename);
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
