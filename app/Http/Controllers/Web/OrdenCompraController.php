<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InventarioMovimiento;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\CotizacionCompra;
use App\Models\CotizacionCompraDetalle;
use App\Models\Proveedor;
use App\Models\Producto;
use App\Models\Empresa;
use App\Models\CuentaPorPagar;
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
        if ($request->has('cotizacion_id')) {
            $cotizacionCompra = CotizacionCompra::with('detalles.producto')->findOrFail($request->cotizacion_id);
            if (!$cotizacionCompra->puedeGenerarOrden()) {
                return redirect()->route('cotizaciones-compra.show', $cotizacionCompra->id)->with('error', 'La cotización debe estar aprobada');
            }
        }
        return view('ordenes-compra.create', compact('empresa', 'folio', 'orden', 'cotizacionCompra'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'fecha' => 'required|date',
            'fecha_entrega_estimada' => 'nullable|date',
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
            $orden->fecha = $validated['fecha'];
            $orden->fecha_entrega_estimada = $validated['fecha_entrega_estimada'] ?? null;
            $orden->moneda = 'MXN';
            $orden->tipo_cambio = 1;
            $orden->subtotal = $subtotal;
            $orden->descuento = $descuento;
            $orden->iva = $iva;
            $orden->total = $total;
            $orden->dias_credito = $proveedor->dias_credito ?? 0;
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
        $ordenCompra->load(['proveedor', 'detalles.producto', 'cotizacionCompra', 'cuentaPorPagar', 'usuario']);
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
            $vencimiento = $ordenCompra->fecha->copy()->addDays($ordenCompra->dias_credito);
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
            return back()->with('success', 'Orden aceptada. Se creó la cuenta por pagar. Puedes recibir la mercancía.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    public function recibir(OrdenCompra $ordenCompra)
    {
        if (!$ordenCompra->puedeRecibirse()) {
            return back()->with('error', 'Solo se puede recibir mercancía en órdenes aceptadas');
        }
        DB::beginTransaction();
        try {
            foreach ($ordenCompra->detalles as $detalle) {
                if ($detalle->producto_id && $detalle->producto && $detalle->producto->controla_inventario) {
                    InventarioMovimiento::registrar(
                        $detalle->producto,
                        InventarioMovimiento::TIPO_ENTRADA_COMPRA,
                        (float) $detalle->cantidad,
                        auth()->id(),
                        null,
                        null,
                        $ordenCompra->id,
                        null
                    );
                }
            }
            $ordenCompra->update(['estado' => 'recibida', 'fecha_recepcion' => now()]);
            DB::commit();
            return back()->with('success', 'Mercancía recibida. Se registró la entrada de inventario.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }
}
