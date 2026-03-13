<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\FacturaCompra;
use App\Models\FacturaCompraDetalle;
use App\Models\FacturaCompraImpuesto;
use App\Models\Proveedor;
use App\Models\Producto;
use App\Models\Empresa;
use App\Models\CotizacionCompraDetalle;
use App\Models\InventarioMovimiento;
use App\Services\FacturaCompraCfdiService;
use App\Services\PDFService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    public function index(Request $request)
    {
        $query = FacturaCompra::with(['proveedor', 'usuario']);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('folio', 'like', "%{$s}%")
                ->orWhere('uuid', 'like', "%{$s}%")
                ->orWhere('nombre_emisor', 'like', "%{$s}%")
                ->orWhere('rfc_emisor', 'like', "%{$s}%"));
        }
        $compras = $query->orderBy('fecha_emision', 'desc')->paginate(20);
        return view('compras.index', compact('compras'));
    }

    public function create(Request $request)
    {
        $empresa = Empresa::principal();
        if (!$empresa) {
            return redirect()->route('dashboard')->with('error', 'Configura la empresa primero');
        }
        return view('compras.create', compact('empresa'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'fecha_emision' => 'required|date',
            'forma_pago' => 'nullable|string|max:2',
            'metodo_pago' => 'nullable|string|max:3',
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

            $fc = FacturaCompra::create([
                'serie' => '',
                'folio' => FacturaCompra::generarFolioManual(),
                'tipo_comprobante' => 'E',
                'estado' => 'registrada',
                'proveedor_id' => $proveedor->id,
                'empresa_id' => $empresa->id,
                'rfc_emisor' => $proveedor->rfc ?? '',
                'nombre_emisor' => $proveedor->nombre,
                'regimen_fiscal_emisor' => $proveedor->regimen_fiscal ?? null,
                'rfc_receptor' => $empresa->rfc ?? '',
                'nombre_receptor' => $empresa->razon_social ?? '',
                'regimen_fiscal_receptor' => $empresa->regimen_fiscal ?? null,
                'fecha_emision' => $validated['fecha_emision'],
                'forma_pago' => $validated['forma_pago'] ?? null,
                'metodo_pago' => $validated['metodo_pago'] ?? 'PUE',
                'moneda' => 'MXN',
                'tipo_cambio' => 1,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'total' => $total,
                'observaciones' => $validated['observaciones'] ?? null,
                'usuario_id' => auth()->id(),
            ]);

            foreach ($validated['productos'] as $index => $item) {
                $producto = !empty($item['producto_id']) ? Producto::find($item['producto_id']) : null;
                $imp = CotizacionCompraDetalle::calcularImportes($item);
                $detalle = FacturaCompraDetalle::create([
                    'factura_compra_id' => $fc->id,
                    'producto_id' => $producto?->id,
                    'clave_prod_serv' => $producto?->clave_sat ?? '01010101',
                    'clave_unidad' => $producto?->clave_unidad_sat ?? 'H87',
                    'unidad' => $producto?->unidad ?? 'Pieza',
                    'no_identificacion' => $producto?->codigo ?? null,
                    'descripcion' => $item['descripcion'],
                    'cantidad' => $item['cantidad'],
                    'valor_unitario' => $item['precio_unitario'],
                    'importe' => $imp['subtotal'],
                    'descuento' => $imp['descuento_monto'],
                    'base_impuesto' => $imp['base_imponible'],
                    'objeto_impuesto' => $producto && in_array($producto->objeto_impuesto ?? '02', ['02', '03']) ? '02' : '01',
                    'orden' => $index,
                ]);
                if ($imp['iva_monto'] > 0) {
                    FacturaCompraImpuesto::create([
                        'factura_compra_detalle_id' => $detalle->id,
                        'tipo' => 'traslado',
                        'impuesto' => '002',
                        'tipo_factor' => 'Tasa',
                        'tasa_o_cuota' => 0.16,
                        'base' => $imp['base_imponible'],
                        'importe' => $imp['iva_monto'],
                    ]);
                }
            }

            // Cuenta por pagar si PPD y proveedor tiene días crédito
            $diasCredito = (int) ($proveedor->dias_credito ?? 0);
            if (($validated['metodo_pago'] ?? 'PUE') === 'PPD' && $diasCredito > 0) {
                $fechaEmision = \Carbon\Carbon::parse($fc->fecha_emision);
                $fechaVencimiento = $fechaEmision->copy()->addDays($diasCredito);
                \App\Models\CuentaPorPagar::create([
                    'factura_compra_id' => $fc->id,
                    'orden_compra_id' => null,
                    'proveedor_id' => $proveedor->id,
                    'monto_total' => $fc->total,
                    'monto_pagado' => 0,
                    'monto_pendiente' => $fc->total,
                    'fecha_emision' => $fechaEmision,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'estado' => 'pendiente',
                ]);
            }

            DB::commit();
            return redirect()->route('compras.show', $fc->id)->with('success', 'Compra registrada correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(FacturaCompra $compra)
    {
        $compra->load(['proveedor', 'detalles.producto', 'detalles.impuestos', 'cuentaPorPagar', 'usuario']);
        return view('compras.show', compact('compra'));
    }

    public function recibir(FacturaCompra $compra)
    {
        if (!$compra->puedeRecibirse()) {
            return back()->with('error', 'Solo se puede recibir mercancía en compras registradas');
        }
        DB::beginTransaction();
        try {
            foreach ($compra->detalles as $detalle) {
                if ($detalle->producto_id && $detalle->producto && $detalle->producto->controla_inventario) {
                    InventarioMovimiento::registrar(
                        $detalle->producto,
                        InventarioMovimiento::TIPO_ENTRADA_COMPRA,
                        (float) $detalle->cantidad,
                        auth()->id(),
                        null,
                        null,
                        null,
                        $compra->id,  // factura_compra_id
                        null         // observaciones
                    );
                }
            }
            $compra->update(['estado' => 'recibida', 'fecha_recepcion' => now()]);
            DB::commit();
            return back()->with('success', 'Mercancía recibida. Se registró la entrada de inventario.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    public function uploadCfdi(Request $request)
    {
        if ($request->isMethod('post')) {
            $request->validate([
                'xml_file' => 'required|file|mimes:xml|max:5120',
            ]);
            $content = file_get_contents($request->file('xml_file')->getRealPath());
            $service = app(FacturaCompraCfdiService::class);
            $result = $service->parsearYGuardar($content);
            if ($result['success']) {
                return redirect()->route('compras.show', $result['factura_compra']->id)
                    ->with('success', $result['message']);
            }
            return back()->with('error', $result['message']);
        }
        return view('compras.upload-cfdi');
    }

    public function verPDF(FacturaCompra $compra)
    {
        try {
            $compra->load(['detalles.producto', 'proveedor', 'empresa']);
            $pdfPath = app(PDFService::class)->generarFacturaCompraPDF($compra);
            return response()->file(storage_path('app/' . $pdfPath));
        } catch (\Exception $e) {
            return back()->with('error', 'Error al generar PDF: ' . $e->getMessage());
        }
    }

    public function descargarPDF(FacturaCompra $compra)
    {
        try {
            $compra->load(['detalles.producto', 'proveedor', 'empresa']);
            $pdfPath = app(PDFService::class)->generarFacturaCompraPDF($compra);
            return response()->download(
                storage_path('app/' . $pdfPath),
                'Compra_' . $compra->folio_completo . '.pdf'
            );
        } catch (\Exception $e) {
            return back()->with('error', 'Error al descargar PDF: ' . $e->getMessage());
        }
    }

    public function buscarProveedores(Request $request)
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }
        return response()->json(
            Proveedor::activos()
                ->Buscar($q)
                ->limit(15)
                ->get(['id', 'nombre', 'rfc', 'dias_credito'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'rfc' => $p->rfc ?? '',
                    'dias_credito' => $p->dias_credito ?? 0,
                ])
        );
    }

    public function buscarProductos(Request $request)
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }
        return response()->json(
            Producto::where('activo', true)
                ->where(fn ($qb) => $qb->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%"))
                ->limit(15)
                ->get(['id', 'codigo', 'nombre', 'costo', 'costo_promedio'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'codigo' => $p->codigo ?? '',
                    'nombre' => $p->nombre,
                    'costo' => (float) ($p->costo ?? $p->costo_promedio ?? 0),
                    'tasa_iva' => (float) ($p->tasa_iva ?? 0.16),
                ])
        );
    }
}
