<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\CotizacionCompra;
use App\Models\CotizacionCompraDetalle;
use App\Models\Proveedor;
use App\Models\Producto;
use App\Models\Empresa;
use App\Models\CuentaPorPagar;
use App\Services\FacturaCompraDesdeOrdenCompraService;
use App\Services\PDFService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdenCompraController extends Controller
{
    public function index(Request $request)
    {
        $query = OrdenCompra::with(['proveedor', 'usuario']);
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('folio', 'like', "%{$s}%")->orWhere('proveedor_nombre', 'like', "%{$s}%"));
        }
        $ordenes = $query->orderBy('created_at', 'desc')->paginate(20);
        $estadisticas = [
            'borrador' => OrdenCompra::where('estado', 'borrador')->count(),
            'aceptada' => OrdenCompra::where('estado', 'aceptada')->count(),
            'recibida' => OrdenCompra::where('estado', 'recibida')->count(),
            'convertida_compra' => OrdenCompra::where('estado', 'convertida_compra')->count(),
            'cancelada' => OrdenCompra::where('estado', 'cancelada')->count(),
        ];
        return view('ordenes-compra.index', compact('ordenes', 'estadisticas'));
    }

    public function create(Request $request)
    {
        $empresa = Empresa::principal();
        if (!$empresa) {
            return redirect()->route('dashboard')->with('error', 'Configura la empresa primero');
        }
        $folio = OrdenCompra::generarFolio();
        $orden = null;
        $cotizacionCompra = null;
        $proveedorPrecargado = null;
        if ($request->has('cotizacion_id')) {
            $cotizacionCompra = CotizacionCompra::with('detalles.producto')->findOrFail($request->cotizacion_id);
            if (!$cotizacionCompra->puedeGenerarOrden()) {
                return redirect()->route('cotizaciones-compra.show', $cotizacionCompra->id)->with('error', 'La cotización debe estar aprobada');
            }
        }
        if ($request->filled('proveedor_id')) {
            $p = Proveedor::find($request->proveedor_id);
            if ($p) {
                $proveedorPrecargado = $p->only(['id', 'codigo', 'nombre', 'rfc', 'dias_credito', 'regimen_fiscal', 'uso_cfdi']);
            }
        }
        return view('ordenes-compra.create', compact('empresa', 'folio', 'orden', 'cotizacionCompra', 'proveedorPrecargado'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'fecha' => 'required|date',
            'fecha_entrega_estimada' => 'nullable|date',
            'dias_credito' => 'nullable|integer|min:0',
            'observaciones' => 'nullable|string',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'nullable|exists:productos,id',
            'productos.*.descripcion' => 'required|string',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.precio_unitario' => 'required|numeric|min:0',
            'productos.*.descuento_porcentaje' => 'nullable|numeric|min:0|max:100',
            'productos.*.tasa_iva' => 'nullable|numeric',
            'productos.*.es_producto_manual' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $proveedor = Proveedor::findOrFail($validated['proveedor_id']);
            $empresa = Empresa::principal();
            $subtotal = $descuento = $iva = 0;
            foreach ($validated['productos'] as $item) {
                $imp = CotizacionCompraDetalle::calcularImportes($item);
                $subtotal += $imp['subtotal'];
                $descuento += $imp['descuento_monto'];
                $iva += $imp['iva_monto'];
            }
            $total = $subtotal - $descuento + $iva;

            $orden = new OrdenCompra();
            $orden->folio = OrdenCompra::generarFolio();
            $orden->estado = 'borrador';
            $orden->proveedor_id = $proveedor->id;
            $orden->empresa_id = $empresa->id;
            $orden->proveedor_nombre = $proveedor->nombre;
            $orden->proveedor_rfc = $proveedor->rfc;
            $orden->proveedor_regimen_fiscal = $proveedor->regimen_fiscal;
            $orden->proveedor_uso_cfdi = $proveedor->uso_cfdi;
            $orden->fecha = $validated['fecha'];
            $orden->fecha_entrega_estimada = $validated['fecha_entrega_estimada'] ?? null;
            $orden->moneda = 'MXN';
            $orden->tipo_cambio = 1;
            $orden->subtotal = $subtotal;
            $orden->descuento = $descuento;
            $orden->iva = $iva;
            $orden->total = $total;
            $orden->dias_credito = isset($validated['dias_credito']) ? (int) $validated['dias_credito'] : ($proveedor->dias_credito ?? 0);
            $orden->observaciones = $validated['observaciones'] ?? null;
            $orden->usuario_id = auth()->id();
            $orden->save();

            foreach ($validated['productos'] as $index => $item) {
                $producto = !empty($item['producto_id']) ? Producto::find($item['producto_id']) : null;
                $imp = CotizacionCompraDetalle::calcularImportes($item);
                OrdenCompraDetalle::create([
                    'orden_compra_id' => $orden->id,
                    'producto_id' => $producto?->id,
                    'codigo' => $producto?->codigo ?? 'MANUAL',
                    'descripcion' => $item['descripcion'],
                    'es_producto_manual' => $item['es_producto_manual'] ?? false,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'descuento_porcentaje' => $item['descuento_porcentaje'] ?? 0,
                    'tasa_iva' => $item['tasa_iva'] ?? null,
                    'subtotal' => $imp['subtotal'],
                    'descuento_monto' => $imp['descuento_monto'],
                    'iva_monto' => $imp['iva_monto'],
                    'total' => $imp['total'],
                    'orden' => $index,
                ]);
            }
            DB::commit();
            return redirect()->route('ordenes-compra.show', $orden->id)->with('success', 'Orden de compra creada');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(OrdenCompra $ordenCompra)
    {
        $ordenCompra->load(['proveedor', 'detalles.producto', 'cotizacionCompra', 'cuentaPorPagar', 'usuario', 'facturaCompra.cuentaPorPagar']);
        return view('ordenes-compra.show', compact('ordenCompra'));
    }

    public function aceptar(OrdenCompra $ordenCompra)
    {
        if (!$ordenCompra->puedeAceptarse()) {
            return back()->with('error', 'Solo se puede aceptar una orden en borrador');
        }
        DB::beginTransaction();
        try {
            $ordenCompra->update(['estado' => 'aceptada']);
            $diasCredito = (int) ($ordenCompra->dias_credito ?? 0);
            if ($diasCredito > 0) {
                $vencimiento = $ordenCompra->fecha->copy()->addDays($diasCredito);
                CuentaPorPagar::create([
                    'orden_compra_id' => $ordenCompra->id,
                    'proveedor_id' => $ordenCompra->proveedor_id,
                    'monto_total' => $ordenCompra->total,
                    'monto_pagado' => 0,
                    'monto_pendiente' => $ordenCompra->total,
                    'fecha_emision' => $ordenCompra->fecha,
                    'fecha_vencimiento' => $vencimiento,
                    'estado' => 'pendiente',
                ]);
                DB::commit();
                return back()->with('success', 'Orden aceptada. Se creó la cuenta por pagar. Puedes convertir la orden en compra cuando recibas la factura o la mercancía.');
            }
            DB::commit();
            return back()->with('success', 'Orden aceptada. Compra de contado (0 días crédito), no se registró en Cuentas por Pagar. Puedes convertir la orden en compra cuando corresponda.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    public function convertirACompraNormal(OrdenCompra $ordenCompra, FacturaCompraDesdeOrdenCompraService $service)
    {
        if (! $ordenCompra->puedeConvertirseACompra()) {
            return back()->with('error', 'Solo se puede convertir una orden aceptada que aún no tenga compra asociada.');
        }
        try {
            $fc = $service->crearRegistroDesdeOrden($ordenCompra);

            return redirect()->route('compras.show', $fc->id)
                ->with('success', 'Compra generada desde la orden '.$ordenCompra->folio.'. Continúe con la recepción de mercancía en la ficha de la compra si aplica.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Ver PDF en navegador
     */
    public function verPDF(OrdenCompra $ordenCompra)
    {
        try {
            $ordenCompra->load(['detalles.producto', 'proveedor', 'empresa']);
            $pdfPath = app(PDFService::class)->generarOrdenCompraPDF($ordenCompra);
            return response()->file(storage_path('app/' . $pdfPath));
        } catch (\Exception $e) {
            return back()->with('error', 'Error al generar PDF: ' . $e->getMessage());
        }
    }

    /**
     * Descargar PDF
     */
    public function descargarPDF(OrdenCompra $ordenCompra)
    {
        try {
            $ordenCompra->load(['detalles.producto', 'proveedor', 'empresa']);
            $pdfPath = app(PDFService::class)->generarOrdenCompraPDF($ordenCompra);
            return response()->download(
                storage_path('app/' . $pdfPath),
                'OrdenCompra_' . $ordenCompra->folio . '.pdf'
            );
        } catch (\Exception $e) {
            return back()->with('error', 'Error al descargar PDF: ' . $e->getMessage());
        }
    }

    /**
     * Editar orden (solo borrador)
     */
    public function edit(OrdenCompra $ordenCompra)
    {
        if (!$ordenCompra->puedeEditarse()) {
            return back()->with('error', 'Solo se pueden editar órdenes en borrador');
        }
        $empresa = Empresa::principal();
        $ordenCompra->load(['detalles.producto', 'proveedor']);
        return view('ordenes-compra.edit', compact('ordenCompra', 'empresa'));
    }

    /**
     * Actualizar orden (solo borrador)
     */
    public function update(Request $request, OrdenCompra $ordenCompra)
    {
        if (!$ordenCompra->puedeEditarse()) {
            return back()->with('error', 'Solo se pueden editar órdenes en borrador');
        }
        $validated = $request->validate([
            'fecha' => 'required|date',
            'fecha_entrega_estimada' => 'nullable|date',
            'dias_credito' => 'nullable|integer|min:0',
            'observaciones' => 'nullable|string',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'nullable|exists:productos,id',
            'productos.*.descripcion' => 'required|string',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.precio_unitario' => 'required|numeric|min:0',
            'productos.*.descuento_porcentaje' => 'nullable|numeric|min:0|max:100',
            'productos.*.tasa_iva' => 'nullable|numeric',
            'productos.*.es_producto_manual' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $subtotal = $descuento = $iva = 0;
            foreach ($validated['productos'] as $item) {
                $imp = CotizacionCompraDetalle::calcularImportes($item);
                $subtotal += $imp['subtotal'];
                $descuento += $imp['descuento_monto'];
                $iva += $imp['iva_monto'];
            }
            $total = $subtotal - $descuento + $iva;

            $ordenCompra->update([
                'fecha' => $validated['fecha'],
                'fecha_entrega_estimada' => $validated['fecha_entrega_estimada'] ?? null,
                'dias_credito' => (int) ($validated['dias_credito'] ?? 0),
                'observaciones' => $validated['observaciones'] ?? null,
                'proveedor_regimen_fiscal' => $ordenCompra->proveedor->regimen_fiscal ?? $ordenCompra->proveedor_regimen_fiscal,
                'proveedor_uso_cfdi' => $ordenCompra->proveedor->uso_cfdi ?? $ordenCompra->proveedor_uso_cfdi,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'iva' => $iva,
                'total' => $total,
            ]);

            $ordenCompra->detalles()->delete();
            foreach ($validated['productos'] as $index => $item) {
                $producto = !empty($item['producto_id']) ? Producto::find($item['producto_id']) : null;
                $imp = CotizacionCompraDetalle::calcularImportes($item);
                OrdenCompraDetalle::create([
                    'orden_compra_id' => $ordenCompra->id,
                    'producto_id' => $producto?->id,
                    'codigo' => $producto?->codigo ?? 'MANUAL',
                    'descripcion' => $item['descripcion'],
                    'es_producto_manual' => $item['es_producto_manual'] ?? false,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'descuento_porcentaje' => $item['descuento_porcentaje'] ?? 0,
                    'tasa_iva' => $item['tasa_iva'] ?? null,
                    'subtotal' => $imp['subtotal'],
                    'descuento_monto' => $imp['descuento_monto'],
                    'iva_monto' => $imp['iva_monto'],
                    'total' => $imp['total'],
                    'orden' => $index,
                ]);
            }
            DB::commit();
            return redirect()->route('ordenes-compra.show', $ordenCompra->id)->with('success', 'Orden actualizada correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Cancelar orden - solo después de estar aceptada (con cuenta por pagar)
     */
    public function destroy(OrdenCompra $ordenCompra)
    {
        if (! $ordenCompra->puedeCancelarse()) {
            return back()->with('error', 'Solo se pueden cancelar órdenes aceptadas que aún no se hayan convertido en compra.');
        }
        DB::beginTransaction();
        try {
            if ($ordenCompra->cuentaPorPagar) {
                $ordenCompra->cuentaPorPagar->update(['estado' => 'cancelada']);
            }
            $ordenCompra->update(['estado' => 'cancelada']);
            DB::commit();
            return redirect()->route('ordenes-compra.index')->with('success', 'Orden cancelada');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }
}
