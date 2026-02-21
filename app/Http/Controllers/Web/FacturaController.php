<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/FacturaController.php
// REEMPLAZA el contenido actual con este

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\FacturaImpuesto;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Empresa;
use App\Models\CuentaPorCobrar;
use App\Models\InventarioMovimiento;
use App\Models\FormaPago;
use App\Models\MetodoPago;
use App\Models\UsoCfdi;
use App\Services\PACServiceInterface;
use App\Services\PDFService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FacturaController extends Controller
{
    public function __construct(
        protected PACServiceInterface $pacService,
        protected PDFService $pdfService
    ) {}

    /**
     * Listado de facturas
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $estado = $request->get('estado');
        
        $facturas = Factura::with(['cliente', 'usuario'])
            ->when($search, function($query) use ($search) {
                $query->buscar($search);
            })
            ->when($estado, function($query) use ($estado) {
                $query->where('estado', $estado);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('facturas.index', compact('facturas', 'search', 'estado'));
    }

    /**
     * Formulario crear factura
     */
    public function create(Request $request)
    {
        $empresa = Empresa::principal();
        
        if (!$empresa) {
            return redirect()->route('dashboard')
                ->with('error', 'Debes configurar los datos de la empresa primero');
        }

        $clientes = Cliente::activos()->orderBy('nombre')->get();
        $productos = Producto::activos()->with('categoria')->orderBy('nombre')->get();
        $formasPago = FormaPago::activos()->get();
        $metodosPago = MetodoPago::activos()->get();
        $usosCfdi = UsoCfdi::activos()->get();

        $clientePreseleccionado = null;
        if ($request->has('cliente_id')) {
            $clientePreseleccionado = Cliente::find($request->cliente_id);
        }

        return view('facturas.create', compact('empresa', 'clientes', 'productos', 'clientePreseleccionado', 'formasPago', 'metodosPago', 'usosCfdi'));
    }

    /**
     * Guardar factura
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'fecha_emision' => 'required|date',
            'forma_pago' => 'required|string|exists:formas_pago,clave',
            'metodo_pago' => 'required|string|exists:metodos_pago,clave',
            'uso_cfdi' => 'required|string|exists:usos_cfdi,clave',
            'observaciones' => 'nullable|string',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'nullable|exists:productos,id',
            'productos.*.descripcion' => 'required|string',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.valor_unitario' => 'required|numeric|min:0',
            'productos.*.descuento' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $empresa = Empresa::principal();
            $cliente = Cliente::findOrFail($validated['cliente_id']);

            // Calcular totales
            $subtotal = 0;
            $descuentoTotal = 0;
            $ivaTotal = 0;

            foreach ($validated['productos'] as $prod) {
                $cantidad = $prod['cantidad'];
                $valorUnitario = $prod['valor_unitario'];
                $descuento = $prod['descuento'] ?? 0;
                
                $importe = $cantidad * $valorUnitario;
                $subtotal += $importe;
                $descuentoTotal += $descuento;

                $producto = isset($prod['producto_id']) ? Producto::find($prod['producto_id']) : null;
                $baseImpuesto = $importe - $descuento;
                if ($producto && $producto->aplicaImpuestoTraslado()) {
                    $ivaTotal += round($baseImpuesto * (float)$producto->tasa_iva, 2);
                }
            }

            $total = $subtotal - $descuentoTotal + $ivaTotal;

            // Obtener siguiente folio
            $folio = $empresa->folio_factura;
            
            // Crear factura
            $factura = Factura::create([
                'serie' => $empresa->serie_factura,
                'folio' => $folio,
                'tipo_comprobante' => 'I',
                'estado' => 'borrador',
                'cliente_id' => $cliente->id,
                'empresa_id' => $empresa->id,
                'rfc_emisor' => $empresa->rfc,
                'nombre_emisor' => $empresa->razon_social,
                'regimen_fiscal_emisor' => $empresa->regimen_fiscal,
                'rfc_receptor' => $cliente->rfc,
                'nombre_receptor' => $cliente->nombre,
                'uso_cfdi' => $validated['uso_cfdi'],
                'regimen_fiscal_receptor' => $cliente->regimen_fiscal,
                'domicilio_fiscal_receptor' => $cliente->codigo_postal,
                'lugar_expedicion' => $empresa->codigo_postal,
                'fecha_emision' => $validated['fecha_emision'],
                'forma_pago' => $validated['forma_pago'],
                'metodo_pago' => $validated['metodo_pago'],
                'moneda' => 'MXN',
                'tipo_cambio' => 1,
                'subtotal' => $subtotal,
                'descuento' => $descuentoTotal,
                'total' => $total,
                'observaciones' => $validated['observaciones'],
                'usuario_id' => auth()->id(),
            ]);

            // Crear detalles e impuestos por línea (datos fiscales del producto)
            foreach ($validated['productos'] as $index => $prod) {
                $producto = isset($prod['producto_id']) ? Producto::find($prod['producto_id']) : null;
                $objetoImpuesto = $producto ? ($producto->objeto_impuesto ?? '02') : '02';
                $baseImpuesto = $prod['cantidad'] * $prod['valor_unitario'] - ($prod['descuento'] ?? 0);

                $detalle = FacturaDetalle::create([
                    'factura_id' => $factura->id,
                    'producto_id' => $prod['producto_id'] ?? null,
                    'clave_prod_serv' => $producto?->clave_sat ?? '01010101',
                    'clave_unidad' => $producto?->clave_unidad_sat ?? 'H87',
                    'unidad' => $producto?->unidad ?? 'Pieza',
                    'no_identificacion' => $producto?->codigo ?? null,
                    'descripcion' => $prod['descripcion'],
                    'cantidad' => $prod['cantidad'],
                    'valor_unitario' => $prod['valor_unitario'],
                    'importe' => $prod['cantidad'] * $prod['valor_unitario'],
                    'descuento' => $prod['descuento'] ?? 0,
                    'base_impuesto' => $baseImpuesto,
                    'objeto_impuesto' => $objetoImpuesto,
                    'orden' => $index,
                ]);

                // Impuestos traslado (IVA) según datos fiscales del producto
                if ($producto && in_array($objetoImpuesto, ['02', '03'], true)) {
                    $tipoFactor = $producto->tipo_factor ?? 'Tasa';
                    $tasa = (float)($producto->tasa_iva ?? 0);
                    FacturaImpuesto::create([
                        'factura_detalle_id' => $detalle->id,
                        'tipo' => 'traslado',
                        'impuesto' => $producto->tipo_impuesto ?? '002',
                        'tipo_factor' => $tipoFactor,
                        'tasa_o_cuota' => $tipoFactor === 'Tasa' ? $tasa : null,
                        'base' => $baseImpuesto,
                        'importe' => $tipoFactor === 'Tasa' && $tasa > 0 ? round($baseImpuesto * $tasa, 2) : null,
                    ]);
                }

                if ($producto && $producto->controla_inventario) {
                    InventarioMovimiento::registrar(
                        $producto,
                        InventarioMovimiento::TIPO_SALIDA_FACTURA,
                        (float) $prod['cantidad'],
                        auth()->id(),
                        $factura->id,
                        null,
                        null,
                        null
                    );
                }
            }

            // Incrementar folio
            $empresa->incrementarFolioFactura();

            // Si es a crédito (PPD), crear cuenta por cobrar
            if ($factura->metodo_pago === 'PPD') {
                $fechaVencimiento = now()->addDays($cliente->dias_credito);
                
                CuentaPorCobrar::create([
                    'factura_id' => $factura->id,
                    'cliente_id' => $cliente->id,
                    'monto_total' => $total,
                    'monto_pagado' => 0,
                    'monto_pendiente' => $total,
                    'fecha_emision' => $validated['fecha_emision'],
                    'fecha_vencimiento' => $fechaVencimiento,
                    'estado' => 'pendiente',
                ]);

                // Actualizar saldo del cliente
                $cliente->actualizarSaldo();
            }

            DB::commit();

            return redirect()->route('facturas.show', $factura->id)
                ->with('success', 'Factura creada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Error al crear factura: ' . $e->getMessage());
        }
    }

    /**
     * Ver detalle de factura
     */
    public function show(Factura $factura)
    {
        $factura->load(['cliente', 'detalles.producto', 'detalles.impuestos', 'cuentaPorCobrar', 'usuario']);
        return view('facturas.show', compact('factura'));
    }

    /**
     * Timbrar factura
     */
    public function timbrar(Factura $factura)
    {
        if (!$factura->puedeTimbrar()) {
            return back()->with('error', 'Esta factura no puede ser timbrada');
        }

        DB::beginTransaction();
        try {
            // Llamar al servicio de timbrado
            $resultado = $this->pacService->timbrarFactura($factura);

            if (!$resultado['success']) {
                throw new \Exception($resultado['message']);
            }

            // Actualizar factura con datos del timbrado
            $factura->update([
                'estado' => 'timbrada',
                'uuid' => $resultado['uuid'],
                'fecha_timbrado' => $resultado['fecha_timbrado'] ?? now(),
                'no_certificado_sat' => $resultado['no_certificado_sat'] ?? null,
                'sello_cfdi' => $resultado['sello_cfdi'] ?? null,
                'sello_sat' => $resultado['sello_sat'] ?? null,
                'cadena_original' => $resultado['cadena_original'] ?? null,
                'xml_content' => $resultado['xml'] ?? null,
            ]);

            // Guardar XML
            if (isset($resultado['xml'])) {
                $xmlPath = $this->guardarXML($factura, $resultado['xml']);
                $factura->update(['xml_path' => $xmlPath]);
            }

            // Generar PDF automáticamente
            $pdfPath = $this->pdfService->generarFacturaPDF($factura);
            $factura->update(['pdf_path' => $pdfPath]);

            DB::commit();

            return redirect()->route('facturas.show', $factura->id)
                ->with('success', $resultado['message'] . ' - PDF generado automáticamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al timbrar: ' . $e->getMessage());
        }
    }

    /**
     * Cancelar factura
     */
    public function cancelar(Request $request, Factura $factura)
    {
        if (!$factura->puedeCancelar()) {
            return back()->with('error', 'Esta factura no puede ser cancelada');
        }

        $validated = $request->validate([
            'motivo_cancelacion' => 'required|string|max:2',
        ]);

        DB::beginTransaction();
        try {
            // Llamar al servicio de cancelación
            $resultado = $this->pacService->cancelarFactura(
                $factura->uuid,
                $validated['motivo_cancelacion']
            );

            if (!$resultado['success']) {
                throw new \Exception($resultado['message']);
            }

            // Actualizar factura
            $factura->update([
                'estado' => 'cancelada',
                'motivo_cancelacion' => $validated['motivo_cancelacion'],
                'fecha_cancelacion' => now(),
                'acuse_cancelacion' => $resultado['acuse'] ?? null,
            ]);

            // Devolver productos al inventario (trazabilidad: devolucion_factura)
            foreach ($factura->detalles as $detalle) {
                if ($detalle->producto && $detalle->producto->controla_inventario) {
                    InventarioMovimiento::registrar(
                        $detalle->producto,
                        InventarioMovimiento::TIPO_DEVOLUCION_FACTURA,
                        (float) $detalle->cantidad,
                        auth()->id(),
                        $factura->id,
                        null,
                        null,
                        'Factura cancelada'
                    );
                }
            }

            // Si tiene cuenta por cobrar, cancelarla
            if ($factura->cuentaPorCobrar) {
                $factura->cuentaPorCobrar->update(['estado' => 'cancelada']);
                $factura->cliente->actualizarSaldo();
            }

            DB::commit();

            return redirect()->route('facturas.show', $factura->id)
                ->with('success', 'Factura cancelada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al cancelar: ' . $e->getMessage());
        }
    }

    /**
     * Generar PDF manualmente
     */
    public function generarPDF(Factura $factura)
    {
        try {
            $pdfPath = $this->pdfService->generarFacturaPDF($factura);
            $factura->update(['pdf_path' => $pdfPath]);

            return redirect()->route('facturas.show', $factura->id)
                ->with('success', 'PDF generado exitosamente');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al generar PDF: ' . $e->getMessage());
        }
    }

    /**
     * Descargar XML
     */
    public function descargarXML(Factura $factura)
    {
        if (!$factura->xml_path) {
            return back()->with('error', 'XML no disponible');
        }

        $filepath = storage_path('app/' . $factura->xml_path);
        
        if (!file_exists($filepath)) {
            return back()->with('error', 'Archivo XML no encontrado');
        }

        return response()->download($filepath, $factura->folio_completo . '.xml');
    }

    /**
     * Descargar PDF
     */
    public function descargarPDF(Factura $factura)
    {
        if (!$factura->pdf_path) {
            return back()->with('error', 'PDF no disponible');
        }

        return $this->pdfService->descargarPDF($factura->pdf_path, $factura->folio_completo . '.pdf');
    }

    /**
     * Guardar XML
     */
    protected function guardarXML(Factura $factura, string $xml): string
    {
        $directory = storage_path('app/facturas/' . now()->format('Y/m'));
        
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = $factura->folio_completo . '.xml';
        $filepath = $directory . '/' . $filename;
        
        file_put_contents($filepath, $xml);

        return 'facturas/' . now()->format('Y/m') . '/' . $filename;
    }
}