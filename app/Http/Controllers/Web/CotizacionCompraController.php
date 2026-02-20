<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CotizacionCompra;
use App\Models\CotizacionCompraDetalle;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\Proveedor;
use App\Models\Producto;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CotizacionCompraController extends Controller
{
    public function index(Request $request)
    {
        $query = CotizacionCompra::with(['proveedor', 'usuario']);
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('folio', 'like', "%{$s}%")
                ->orWhere('proveedor_nombre', 'like', "%{$s}%"));
        }
        $cotizaciones = $query->orderBy('created_at', 'desc')->paginate(20);
        $estadisticas = [
            'borrador' => CotizacionCompra::where('estado', 'borrador')->count(),
            'aprobada' => CotizacionCompra::where('estado', 'aprobada')->count(),
            'convertida_oc' => CotizacionCompra::where('estado', 'convertida_oc')->count(),
        ];
        return view('cotizaciones-compra.index', compact('cotizaciones', 'estadisticas'));
    }

    public function create(Request $request)
    {
        $empresa = Empresa::principal();
        if (!$empresa) {
            return redirect()->route('dashboard')->with('error', 'Configura los datos de la empresa primero');
        }
        $folio = CotizacionCompra::generarFolio();
        $cotizacion = null;
        if ($request->has('id')) {
            $cotizacion = CotizacionCompra::with(['detalles.producto'])->findOrFail($request->id);
            if (!$cotizacion->puedeEditarse()) {
                return redirect()->route('cotizaciones-compra.show', $cotizacion->id)->with('error', 'No se puede editar');
            }
        }
        return view('cotizaciones-compra.create', compact('empresa', 'folio', 'cotizacion'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'fecha' => 'required|date',
            'fecha_vencimiento' => 'nullable|date',
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

            $cotizacionId = $request->input('cotizacion_id');
            if ($cotizacionId) {
                $cotizacion = CotizacionCompra::findOrFail($cotizacionId);
                if (!$cotizacion->puedeEditarse()) {
                    throw new \Exception('No se puede editar');
                }
                $cotizacion->detalles()->delete();
            } else {
                $cotizacion = new CotizacionCompra();
                $cotizacion->folio = CotizacionCompra::generarFolio();
                $cotizacion->estado = 'borrador';
                $cotizacion->usuario_id = auth()->id();
            }

            $cotizacion->fill([
                'proveedor_id' => $proveedor->id,
                'empresa_id' => $empresa->id,
                'proveedor_nombre' => $proveedor->nombre,
                'proveedor_rfc' => $proveedor->rfc,
                'proveedor_email' => $proveedor->email,
                'proveedor_telefono' => $proveedor->telefono,
                'fecha' => $validated['fecha'],
                'fecha_vencimiento' => $validated['fecha_vencimiento'] ?? null,
                'moneda' => 'MXN',
                'tipo_cambio' => 1,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'iva' => $iva,
                'total' => $total,
                'observaciones' => $validated['observaciones'] ?? null,
            ]);
            $cotizacion->save();

            foreach ($validated['productos'] as $index => $item) {
                $producto = !empty($item['producto_id']) ? Producto::find($item['producto_id']) : null;
                CotizacionCompraDetalle::create([
                    'cotizacion_compra_id' => $cotizacion->id,
                    'producto_id' => $producto?->id,
                    'codigo' => $producto?->codigo ?? 'MANUAL',
                    'descripcion' => $item['descripcion'],
                    'es_producto_manual' => $item['es_producto_manual'] ?? false,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'descuento_porcentaje' => $item['descuento_porcentaje'] ?? 0,
                    'tasa_iva' => $item['tasa_iva'] ?? null,
                    'orden' => $index,
                ]);
            }
            DB::commit();
            return redirect()->route('cotizaciones-compra.show', $cotizacion->id)
                ->with('success', $cotizacionId ? 'Cotización de compra actualizada' : 'Cotización de compra creada');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(CotizacionCompra $cotizacionCompra)
    {
        $cotizacionCompra->load(['proveedor', 'detalles.producto', 'usuario']);
        return view('cotizaciones-compra.show', compact('cotizacionCompra'));
    }

    public function aprobar(CotizacionCompra $cotizacionCompra)
    {
        if (!$cotizacionCompra->puedeAprobarse()) {
            return back()->with('error', 'Solo se puede aprobar una cotización en borrador');
        }
        $cotizacionCompra->update(['estado' => 'aprobada']);
        return back()->with('success', 'Cotización aprobada. Puedes generar la orden de compra.');
    }

    public function generarOrdenCompra(CotizacionCompra $cotizacionCompra)
    {
        if (!$cotizacionCompra->puedeGenerarOrden()) {
            return back()->with('error', 'Solo se puede generar orden desde una cotización aprobada');
        }

        DB::beginTransaction();
        try {
            $orden = new OrdenCompra();
            $orden->folio = OrdenCompra::generarFolio();
            $orden->estado = 'borrador';
            $orden->cotizacion_compra_id = $cotizacionCompra->id;
            $orden->proveedor_id = $cotizacionCompra->proveedor_id;
            $orden->empresa_id = $cotizacionCompra->empresa_id;
            $orden->proveedor_nombre = $cotizacionCompra->proveedor_nombre;
            $orden->proveedor_rfc = $cotizacionCompra->proveedor_rfc;
            $orden->fecha = $cotizacionCompra->fecha;
            $orden->moneda = $cotizacionCompra->moneda;
            $orden->tipo_cambio = $cotizacionCompra->tipo_cambio;
            $orden->subtotal = $cotizacionCompra->subtotal;
            $orden->descuento = $cotizacionCompra->descuento;
            $orden->iva = $cotizacionCompra->iva;
            $orden->total = $cotizacionCompra->total;
            $orden->observaciones = $cotizacionCompra->observaciones;
            $orden->usuario_id = auth()->id();
            $orden->dias_credito = $cotizacionCompra->proveedor->dias_credito ?? 0;
            $orden->save();

            foreach ($cotizacionCompra->detalles as $index => $d) {
                OrdenCompraDetalle::create([
                    'orden_compra_id' => $orden->id,
                    'producto_id' => $d->producto_id,
                    'codigo' => $d->codigo,
                    'descripcion' => $d->descripcion,
                    'es_producto_manual' => $d->es_producto_manual,
                    'cantidad' => $d->cantidad,
                    'precio_unitario' => $d->precio_unitario,
                    'descuento_porcentaje' => $d->descuento_porcentaje,
                    'tasa_iva' => $d->tasa_iva,
                    'subtotal' => $d->subtotal,
                    'descuento_monto' => $d->descuento_monto,
                    'iva_monto' => $d->iva_monto,
                    'total' => $d->total,
                    'orden' => $index,
                ]);
            }

            $cotizacionCompra->update(['estado' => 'convertida_oc']);
            DB::commit();
            return redirect()->route('ordenes-compra.show', $orden->id)->with('success', 'Orden de compra generada');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    public function buscarProveedores(Request $request)
    {
        $q = $request->get('q', '');
        $list = Proveedor::activos()
            ->where(fn ($query) => $query->where('nombre', 'like', "%{$q}%")->orWhere('codigo', 'like', "%{$q}%"))
            ->limit(10)
            ->get(['id', 'codigo', 'nombre', 'rfc', 'dias_credito']);
        return response()->json($list);
    }

    public function buscarProductos(Request $request)
    {
        $q = $request->get('q', '');
        $list = Producto::where('activo', true)
            ->where(fn ($query) => $query->where('nombre', 'like', "%{$q}%")->orWhere('codigo', 'like', "%{$q}%"))
            ->limit(10)
            ->get(['id', 'codigo', 'nombre', 'precio_venta', 'tasa_iva', 'tipo_factor']);
        $list = $list->map(fn ($p) => [
            'id' => $p->id,
            'codigo' => $p->codigo,
            'nombre' => $p->nombre,
            'precio_venta' => $p->precio_venta,
            'tasa_iva' => ($p->tipo_factor ?? 'Tasa') === 'Exento' ? null : (float) $p->tasa_iva,
        ]);
        return response()->json($list);
    }
}
