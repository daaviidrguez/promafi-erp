<?php

namespace App\Http\Controllers\Web;

use App\Events\FacturaCompraDesdeCfdiRegistrada;
use App\Http\Controllers\Controller;
use App\Models\CotizacionCompraDetalle;
use App\Models\CuentaPorPagar;
use App\Models\Empresa;
use App\Models\EntradaAnticipada;
use App\Models\EntradaAnticipadaDetalle;
use App\Models\FacturaCompra;
use App\Models\FacturaCompraDetalle;
use App\Models\FacturaCompraImpuesto;
use App\Models\InventarioMovimiento;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\ProductoProveedor;
use App\Models\Proveedor;
use App\Services\FacturaCompraCfdiService;
use App\Services\FacturaCompraDesdeEntradaAnticipadaService;
use App\Services\FacturaCompraDesdeOrdenCompraService;
use App\Services\EntradaAnticipadaService;
use App\Services\PDFService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    public function index(Request $request)
    {
        $query = FacturaCompra::with(['proveedor', 'usuario', 'ordenCompra']);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('folio', 'like', "%{$s}%")
                    ->orWhere('folio_interno', 'like', "%{$s}%")
                    ->orWhere('uuid', 'like', "%{$s}%")
                    ->orWhere('nombre_emisor', 'like', "%{$s}%")
                    ->orWhere('rfc_emisor', 'like', "%{$s}%")
                    ->orWhere('serie', 'like', "%{$s}%")
                    ->orWhere(function ($q2) use ($s) {
                        $driver = DB::connection()->getDriverName();
                        if ($driver === 'mysql') {
                            $q2->whereRaw(
                                "TRIM(CONCAT(COALESCE(serie, ''), '/', COALESCE(folio, ''))) LIKE ?",
                                ["%{$s}%"]
                            );
                        } else {
                            $q2->whereRaw(
                                "(TRIM(COALESCE(serie, '')) || '/' || TRIM(COALESCE(folio, ''))) LIKE ?",
                                ["%{$s}%"]
                            );
                        }
                    })
                    ->orWhereHas('ordenCompra', fn ($qo) => $qo->where('folio', 'like', "%{$s}%"))
                    ->orWhereHas('proveedor', fn ($qp) => $qp->where('codigo', 'like', "%{$s}%")
                        ->orWhere('nombre', 'like', "%{$s}%")
                        ->orWhere('rfc', 'like', "%{$s}%"));
            });
        }
        $compras = $query->orderBy('fecha_emision', 'desc')->paginate(20);

        return view('compras.index', compact('compras'));
    }

    public function create(Request $request)
    {
        $empresa = Empresa::principal();
        if (! $empresa) {
            return redirect()->route('dashboard')->with('error', 'Configura la empresa primero');
        }

        $folio = FacturaCompra::generarFolioInterno();
        $entradaAnticipada = null;
        $productosPrecargados = [];
        $proveedorPrecargado = null;

        if ($request->filled('entrada_anticipada_id')) {
            $ea = EntradaAnticipada::with(['detalles.producto', 'proveedor'])->find($request->integer('entrada_anticipada_id'));
            if (! $ea || ! $ea->puedeFacturarse()) {
                return redirect()->route('entradas-anticipadas.index')
                    ->with('error', 'La entrada anticipada no admite registrar compra manual.');
            }

            app(EntradaAnticipadaService::class)->normalizarImportesDetalle($ea);
            $ea->refresh()->load(['detalles.producto', 'proveedor']);

            $request->session()->put('compras_desde_entrada_anticipada_id', $ea->id);
            $entradaAnticipada = $ea;
            $proveedorPrecargado = $ea->proveedor?->only(['id', 'codigo', 'nombre', 'rfc', 'dias_credito']);

            foreach ($ea->detalles as $d) {
                $pendiente = (float) $d->cantidad_recibida - (float) $d->cantidad_facturada;
                if ($pendiente <= 0) {
                    continue;
                }
                $productosPrecargados[] = [
                    'entrada_detalle_id' => $d->id,
                    'id' => $d->producto_id,
                    'codigo' => $d->producto?->codigo,
                    'codigo_proveedor' => $d->codigo_proveedor,
                    'nombre' => $d->descripcion,
                    'cantidad' => $pendiente,
                    'cantidad_max' => $pendiente,
                    'precio' => (float) $d->precio_unitario_estimado,
                    'descuento' => (float) ($d->descuento_porcentaje ?? 0),
                    'tasa_iva' => EntradaAnticipadaDetalle::resolverTasaIva($d->producto, $d->tasa_iva),
                ];
            }

            if (empty($productosPrecargados)) {
                return redirect()->route('entradas-anticipadas.show', $ea->id)
                    ->with('error', 'No hay líneas pendientes de facturar en esta entrada.');
            }
        } else {
            $request->session()->forget('compras_desde_entrada_anticipada_id');
        }

        return view('compras.create', compact(
            'empresa',
            'folio',
            'entradaAnticipada',
            'productosPrecargados',
            'proveedorPrecargado'
        ));
    }

    public function descartarVinculoEntradaAnticipada(Request $request)
    {
        $eaId = (int) $request->session()->get('compras_desde_entrada_anticipada_id', 0);
        $this->limpiarPdfTempCfdiSesion($request);
        $request->session()->forget([
            'compras_desde_entrada_anticipada_id',
            'compras_cfdi_precarga',
            'compras_cfdi_linea_producto',
        ]);

        if ($eaId > 0) {
            return redirect()->route('entradas-anticipadas.facturar', $eaId)
                ->with('info', 'Se descartó el CFDI en curso.');
        }

        return redirect()->route('compras.index')
            ->with('info', 'Se descartó el vínculo con la entrada anticipada.');
    }

    public function store(Request $request)
    {
        $eaIdSesion = (int) $request->session()->get('compras_desde_entrada_anticipada_id', 0);
        $desdeEntradaAnticipada = $eaIdSesion > 0;

        $validated = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'fecha_emision' => 'required|date',
            'forma_pago' => 'nullable|string|max:2',
            'metodo_pago' => 'nullable|string|max:3',
            'observaciones' => 'nullable|string',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'nullable|exists:productos,id',
            'productos.*.entrada_detalle_id' => 'nullable|exists:entradas_anticipadas_detalle,id',
            'productos.*.descripcion' => 'required|string',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.precio_unitario' => 'required|numeric|min:0',
            'productos.*.descuento_porcentaje' => 'nullable|numeric|min:0|max:100',
            'productos.*.tasa_iva' => 'nullable|numeric',
            'productos.*.es_producto_manual' => 'nullable|boolean',
            'pdf_file' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        if ($desdeEntradaAnticipada) {
            return $this->storeDesdeEntradaAnticipada($request, $validated, $eaIdSesion);
        }

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

            $folioInterno = FacturaCompra::generarFolioInterno();
            $fc = FacturaCompra::create([
                'serie' => '',
                'folio' => $folioInterno,
                'folio_interno' => $folioInterno,
                'tipo_comprobante' => 'E',
                'estado' => 'registrada',
                'origen' => 'directa',
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
                $producto = ! empty($item['producto_id']) ? Producto::find($item['producto_id']) : null;
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

            if ($request->hasFile('pdf_file')) {
                $fc->update(['pdf_path' => $request->file('pdf_file')->store('compras/pdf/'.$fc->id, 'local')]);
            }

            DB::commit();

            return redirect()->route('compras.show', $fc->id)->with('success', 'Compra registrada correctamente');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Guarda compra desde CFDI vinculada a entrada anticipada (inventario ya aplicado).
     *
     * @param  array<string, mixed>  $datos
     */
    private function storeDesdeEntradaAnticipadaCfdi(Request $request, array $datos, int $eaIdSesion)
    {
        $productos = $request->input('productos', []);
        foreach ($productos as $k => $p) {
            if (isset($p['producto_id']) && $p['producto_id'] === '') {
                $productos[$k]['producto_id'] = null;
            }
        }
        $request->merge(['productos' => $productos]);

        $validated = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'fecha_emision' => 'required|date',
            'forma_pago' => 'nullable|string|max:2',
            'metodo_pago' => 'nullable|string|max:3',
            'observaciones' => 'nullable|string',
            'productos' => 'required|array|min:1',
            'productos.*.concepto_index' => 'required|integer|min:0',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.entrada_detalle_id' => 'nullable|exists:entradas_anticipadas_detalle,id',
            'confirmar_desfase_totales' => 'nullable|boolean',
        ]);

        $conceptos = $datos['conceptos'] ?? [];
        foreach ($validated['productos'] as $p) {
            if (! isset($conceptos[(int) $p['concepto_index']])) {
                return back()->withInput()->with('error', 'Datos de detalle inválidos.');
            }
        }

        $ea = EntradaAnticipada::with(['detalles.producto', 'proveedor'])->find($eaIdSesion);
        if (! $ea || ! $ea->puedeFacturarse()) {
            $request->session()->forget('compras_desde_entrada_anticipada_id');

            return redirect()->route('compras.upload-cfdi')
                ->with('error', 'La entrada anticipada ya no admite facturación por CFDI.');
        }

        $proveedor = Proveedor::findOrFail((int) $validated['proveedor_id']);
        $rfcXml = strtoupper(preg_replace('/\s+/', '', (string) ($datos['rfc_emisor'] ?? '')));
        $rfcProv = strtoupper(preg_replace('/\s+/', '', (string) ($proveedor->rfc ?? '')));
        if ($rfcXml === '' || $rfcProv === '' || $rfcXml !== $rfcProv) {
            return back()->withInput()->with(
                'error',
                'El RFC del proveedor seleccionado debe coincidir con el RFC emisor del CFDI'
                .($rfcXml !== '' ? ' ('.$rfcXml.')' : '')
                .'.'
            );
        }

        $productosForm = $validated['productos'];
        foreach ($productosForm as $idx => $p) {
            if (empty($p['entrada_detalle_id'])) {
                $det = $ea->detalles->firstWhere('producto_id', (int) $p['producto_id']);
                if ($det) {
                    $productosForm[$idx]['entrada_detalle_id'] = $det->id;
                }
            }
        }

        try {
            app(EntradaAnticipadaService::class)->normalizarImportesDetalle($ea);
            $ea->refresh()->load(['detalles.producto', 'proveedor']);

            $conceptos = $datos['conceptos'] ?? [];
            foreach ($productosForm as $p) {
                $idx = (int) ($p['concepto_index'] ?? -1);
                if (! isset($conceptos[$idx]) || empty($p['producto_id'])) {
                    continue;
                }
                $noIdent = strtoupper(trim((string) ($conceptos[$idx]['no_identificacion'] ?? '')));
                if ($noIdent === '') {
                    continue;
                }
                $producto = Producto::find((int) $p['producto_id']);
                if (! $producto) {
                    continue;
                }
                $this->sincronizarCodigoProveedorProducto($producto, $proveedor, $noIdent);
                $eaDet = $ea->detalles->firstWhere('producto_id', $producto->id);
                if ($eaDet) {
                    $eaDet->update(['codigo_proveedor' => $noIdent]);
                }
            }

            $confirmarDesfase = $request->boolean('confirmar_desfase_totales');

            $service = app(FacturaCompraDesdeEntradaAnticipadaService::class);
            $fc = $service->crearCompraDesdeCfdi(
                $ea,
                $datos,
                $productosForm,
                $validated,
                $this->pdfSubidoDesdeTempSesion($request),
                $confirmarDesfase
            );

            $this->limpiarPdfTempCfdiSesion($request);
            $request->session()->forget([
                'compras_cfdi_precarga',
                'compras_cfdi_linea_producto',
                'compras_desde_entrada_anticipada_id',
            ]);

            $msg = 'CFDI registrado y vinculado a la entrada anticipada '.$ea->folio.'.';
            if (is_string($fc->observaciones) && str_contains($fc->observaciones, 'Proveedor reasignado al vincular CFDI')) {
                $msg .= ' Se actualizó el proveedor de la entrada al de la factura ('.$proveedor->nombre.').';
            }
            if ($confirmarDesfase) {
                $msg .= ' Se actualizaron los costos de producto con los precios fiscales del CFDI.';
            }
            $corr = $service->ultimoResumenCorreccionUtilidad;
            if (($corr['lineas'] ?? 0) > 0) {
                $folios = implode(', ', array_slice($corr['folios'] ?? [], 0, 8));
                $msg .= ' Reporte de utilidad: se corrigió el costo unitario en '.$corr['lineas'].' partida(s)';
                if ($folios !== '') {
                    $msg .= ' ('.$folios.(count($corr['folios']) > 8 ? '…' : '').')';
                }
                $msg .= '.';
            }

            return redirect()->route('compras.show', $fc->id)->with('success', $msg);
        } catch (\App\Exceptions\TotalesEaCfdiRequierenConfirmacionException $e) {
            return back()->withInput()
                ->with('error', $e->getMessage())
                ->with('ea_cfdi_requiere_confirmacion_desfase', true)
                ->with('ea_cfdi_desfase', $e->toArray());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Guarda compra manual vinculada a entrada anticipada (inventario ya aplicado).
     */
    private function storeDesdeEntradaAnticipada(Request $request, array $validated, int $eaIdSesion)
    {
        $ea = EntradaAnticipada::with(['detalles.producto', 'proveedor'])->find($eaIdSesion);
        if (! $ea || ! $ea->puedeFacturarse()) {
            $request->session()->forget('compras_desde_entrada_anticipada_id');

            return redirect()->route('compras.create')
                ->with('error', 'La entrada anticipada ya no admite facturación manual.');
        }

        if ((int) $validated['proveedor_id'] !== (int) $ea->proveedor_id) {
            return back()->withInput()->with('error', 'El proveedor debe coincidir con el de la entrada anticipada.');
        }

        app(EntradaAnticipadaService::class)->normalizarImportesDetalle($ea);
        $ea->refresh()->load('detalles');

        $lineas = [];
        foreach ($validated['productos'] as $item) {
            if (empty($item['producto_id'])) {
                return back()->withInput()->with('error', 'Todas las líneas deben tener producto del catálogo.');
            }

            $eaDetalleId = (int) ($item['entrada_detalle_id'] ?? 0);
            $det = $eaDetalleId > 0 ? $ea->detalles->firstWhere('id', $eaDetalleId) : null;
            if (! $det || (int) $det->producto_id !== (int) $item['producto_id']) {
                return back()->withInput()->with('error', 'Línea de producto no válida para esta entrada anticipada.');
            }

            $tasaIva = $item['tasa_iva'] ?? null;
            if ($tasaIva === '') {
                $tasaIva = null;
            }

            $lineas[] = [
                'entrada_detalle_id' => $det->id,
                'producto_id' => (int) $item['producto_id'],
                'descripcion' => $item['descripcion'],
                'cantidad' => (float) $item['cantidad'],
                'precio_unitario' => (float) $item['precio_unitario'],
                'descuento_porcentaje' => (float) ($item['descuento_porcentaje'] ?? 0),
                'tasa_iva' => $tasaIva !== null ? (float) $tasaIva : null,
                'codigo_proveedor' => $det->codigo_proveedor,
            ];
        }

        try {
            $service = app(FacturaCompraDesdeEntradaAnticipadaService::class);
            $fc = $service->crearCompraManual(
                $ea,
                $validated,
                $lineas,
                $request->file('pdf_file')
            );

            $request->session()->forget('compras_desde_entrada_anticipada_id');

            $msg = 'Compra registrada y vinculada a la entrada anticipada '.$ea->folio.'.';
            $corr = $service->ultimoResumenCorreccionUtilidad;
            if (($corr['lineas'] ?? 0) > 0) {
                $folios = implode(', ', array_slice($corr['folios'] ?? [], 0, 8));
                $msg .= ' Reporte de utilidad: se corrigió el costo unitario en '.$corr['lineas'].' partida(s)';
                if ($folios !== '') {
                    $msg .= ' ('.$folios.(count($corr['folios']) > 8 ? '…' : '').')';
                }
                $msg .= '.';
            }

            return redirect()->route('compras.show', $fc->id)->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(FacturaCompra $compra)
    {
        $compra->load(['proveedor', 'detalles.producto', 'detalles.impuestos', 'cuentaPorPagar', 'usuario', 'ordenCompra.cotizacionCompra', 'entradaAnticipada']);
        $usoCfdi = $this->extraerUsoCfdiDeXml($compra->xml_content);

        $revisionPreciosBanner = null;
        $revisionPreciosAccionCount = 0;
        $sessionRevision = session('revision_precio_post_compra');
        if (is_array($sessionRevision)
            && (int) ($sessionRevision['factura_compra_id'] ?? 0) === (int) $compra->id
            && (int) ($sessionRevision['count'] ?? 0) > 0) {
            $revisionPreciosAccionCount = (int) $sessionRevision['count'];
            if (empty($sessionRevision['banner_dismissed'])) {
                $revisionPreciosBanner = $revisionPreciosAccionCount;
            }
        }

        return view('compras.show', compact('compra', 'usoCfdi', 'revisionPreciosBanner', 'revisionPreciosAccionCount'));
    }

    /**
     * Oculta el aviso de revisión de precios en la compra (no borra la sesión de trabajo en la pantalla de revisión).
     */
    public function dismissRevisionPrecios(Request $request, FacturaCompra $compra)
    {
        $payload = session('revision_precio_post_compra');
        if (is_array($payload) && (int) ($payload['factura_compra_id'] ?? 0) === (int) $compra->id) {
            $payload['banner_dismissed'] = true;
            session(['revision_precio_post_compra' => $payload]);
        }

        return redirect()->route('compras.show', $compra->id)
            ->with('info', 'De acuerdo. Use «Revisión de precios» en la tarjeta Acciones cuando quiera; los datos siguen disponibles mientras la sesión esté activa.');
    }

    /**
     * Extrae el atributo UsoCFDI desde el XML guardado del CFDI de compra.
     */
    private function extraerUsoCfdiDeXml(?string $xmlContent): ?string
    {
        if (empty($xmlContent)) {
            return null;
        }

        $prev = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $loaded = $dom->loadXML($xmlContent);
            libxml_clear_errors();
            if (! $loaded) {
                return null;
            }

            $xpath = new \DOMXPath($dom);
            $nodos = $xpath->query('//*[local-name()="Receptor"]');
            if (! $nodos || $nodos->length === 0) {
                return null;
            }

            foreach ($nodos as $node) {
                if (! ($node instanceof \DOMElement)) {
                    continue;
                }
                $val = trim((string) $node->getAttribute('UsoCFDI'));
                if ($val !== '') {
                    return $val;
                }
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        } finally {
            libxml_use_internal_errors($prev);
        }
    }

    public function recibir(FacturaCompra $compra)
    {
        if (! $compra->puedeRecibirse()) {
            return back()->with('error', 'Solo se puede recibir mercancía en compras registradas');
        }
        DB::beginTransaction();
        try {
            foreach ($compra->detalles as $detalle) {
                if (! $detalle->producto_id || ! $detalle->producto || ! $detalle->producto->controla_inventario) {
                    continue;
                }
                $producto = $detalle->producto;
                $cantidad = (float) $detalle->cantidad;
                $costoUnitario = (float) $detalle->valor_unitario;
                $stockAnterior = (float) $producto->stock;
                $costoActual = (float) ($producto->costo_promedio ?? $producto->costo ?? 0);
                $denominador = $stockAnterior + $cantidad;
                if ($denominador > 0) {
                    $nuevoCostoPromedio = round(($stockAnterior * $costoActual + $cantidad * $costoUnitario) / $denominador, 2);
                    $producto->update(['costo_promedio' => $nuevoCostoPromedio]);
                }
                InventarioMovimiento::registrar(
                    $producto,
                    InventarioMovimiento::TIPO_ENTRADA_COMPRA,
                    $cantidad,
                    auth()->id(),
                    null,
                    null,
                    null,
                    $compra->id,
                    null
                );
            }
            $compra->update(['estado' => 'recibida', 'fecha_recepcion' => now()]);
            DB::commit();

            return back()->with('success', 'Mercancía recibida. Se registró la entrada de inventario y el costo promedio por producto.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Cancela una compra registrada o recibida: revierte inventario (si aplica),
     * cancela la cuenta por pagar y restaura la orden de compra de origen.
     */
    public function cancelar(FacturaCompra $compra)
    {
        $compra->load(['detalles.producto', 'cuentaPorPagar', 'ordenCompra', 'entradaAnticipada']);

        $motivo = $compra->motivoNoCancelable();
        if ($motivo !== null) {
            return back()->with('error', $motivo);
        }

        $desdeEntradaAnticipada = $compra->inventarioDesdeEntradaAnticipada();

        DB::beginTransaction();
        try {
            $eraRecibida = $compra->estaRecibida();

            if ($desdeEntradaAnticipada) {
                app(FacturaCompraDesdeEntradaAnticipadaService::class)->revertirFacturacionCompra($compra);
            } elseif ($eraRecibida) {
                foreach ($compra->detalles as $detalle) {
                    if (! $detalle->producto_id || ! $detalle->producto || ! $detalle->producto->controla_inventario) {
                        continue;
                    }
                    $producto = $detalle->producto;
                    $cantidad = (float) $detalle->cantidad;
                    $costoUnitario = (float) $detalle->valor_unitario;
                    $stockActual = (float) $producto->stock;
                    $costoActual = (float) ($producto->costo_promedio ?? $producto->costo ?? 0);
                    $stockDespuesReversa = $stockActual - $cantidad;

                    if ($stockDespuesReversa < 0) {
                        throw new \InvalidArgumentException(
                            "Stock insuficiente para revertir «{$producto->nombre}». Disponible: {$stockActual}, se requieren {$cantidad}."
                        );
                    }

                    if ($stockDespuesReversa > 0) {
                        $nuevoCostoPromedio = round(
                            ($stockActual * $costoActual - $cantidad * $costoUnitario) / $stockDespuesReversa,
                            2
                        );
                        $producto->update(['costo_promedio' => max(0, $nuevoCostoPromedio)]);
                    } else {
                        $producto->update(['costo_promedio' => (float) ($producto->costo ?? 0)]);
                    }

                    InventarioMovimiento::registrar(
                        $producto,
                        InventarioMovimiento::TIPO_SALIDA_COMPRA,
                        $cantidad,
                        auth()->id(),
                        null,
                        null,
                        null,
                        $compra->id,
                        'Reversa por cancelación de compra'
                    );
                }
            }

            if ($compra->cuentaPorPagar) {
                $compra->cuentaPorPagar->update([
                    'estado' => 'cancelada',
                    'monto_pendiente' => 0,
                ]);
            }

            if (! $desdeEntradaAnticipada && $compra->ordenCompra && $compra->ordenCompra->estado === 'convertida_compra') {
                $compra->ordenCompra->update(['estado' => 'aceptada']);
            }

            $compra->update([
                'estado' => 'cancelada',
                'fecha_recepcion' => null,
            ]);

            DB::commit();

            if ($desdeEntradaAnticipada) {
                $folioEa = $compra->entradaAnticipada?->folio ?? 'entrada anticipada';

                return back()->with('success', "Compra cancelada. Se desvinculó la {$folioEa}; el inventario de la entrada se mantiene. Se canceló la cuenta por pagar si existía.");
            }

            $mensaje = $eraRecibida
                ? 'Compra cancelada. Se revirtió el inventario y se canceló la cuenta por pagar vinculada.'
                : 'Compra cancelada. Se canceló la cuenta por pagar vinculada (sin movimiento de inventario).';

            return back()->with('success', $mensaje);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Error al cancelar la compra: '.$e->getMessage());
        }
    }

    /**
     * Limpia la sesión de conversión OC → CFDI (flujo botón B) y redirige al listado de compras.
     */
    public function descartarVinculoOrdenOcCfdi(Request $request)
    {
        $request->session()->forget('compras_desde_orden_compra_id');

        return redirect()->route('compras.index')
            ->with('info', 'Se descartó el vínculo con la orden de compra. Para volver a intentarlo, abra la orden y use «Convertir a compra».');
    }

    public function uploadCfdi(Request $request)
    {
        $eaIdSesion = (int) $request->session()->get('compras_desde_entrada_anticipada_id', 0);

        if ($request->isMethod('post')) {
            $request->validate([
                'xml_file' => [
                    'required',
                    'file',
                    'max:5120',
                    function (string $attr, $value, \Closure $fail): void {
                        $ext = strtolower($value->getClientOriginalExtension());
                        $mime = $value->getMimeType();
                        $xmlMimes = ['text/xml', 'application/xml', 'application/x-xml', 'text/plain'];
                        if ($ext !== 'xml' && ! in_array($mime, $xmlMimes, true)) {
                            $fail('El archivo debe ser XML (.xml).');
                        }
                    },
                ],
                'pdf_file' => 'nullable|file|mimes:pdf|max:10240',
            ]);
            $content = file_get_contents($request->file('xml_file')->getRealPath());
            $service = app(FacturaCompraCfdiService::class);
            $result = $service->parsear($content);
            if ($result['success']) {
                if ($eaIdSesion > 0) {
                    $ea = EntradaAnticipada::with('proveedor')->find($eaIdSesion);
                    if (! $ea || ! $ea->puedeFacturarse()) {
                        $request->session()->forget('compras_desde_entrada_anticipada_id');

                        return redirect()->route('entradas-anticipadas.index')
                            ->with('error', 'La entrada anticipada ya no admite facturación por CFDI.');
                    }
                    $rfcXml = strtoupper(preg_replace('/\s+/', '', (string) ($result['datos']['rfc_emisor'] ?? '')));
                    $rfcProv = strtoupper(preg_replace('/\s+/', '', (string) ($ea->proveedor?->rfc ?? '')));
                    if ($rfcProv !== '' && $rfcXml !== '' && $rfcProv !== $rfcXml) {
                        // No bloquear: en el formulario se podrá elegir el proveedor correcto (RFC del emisor).
                        $request->session()->flash(
                            'warning',
                            'El RFC del CFDI ('.$rfcXml.') no coincide con el proveedor actual de la entrada ('
                            .($ea->proveedor?->nombre ?? '—').' · '.$rfcProv
                            .'). Seleccione el proveedor correcto al vincular; debe coincidir con el RFC del emisor.'
                        );
                    }
                } else {
                    $idOrd = (int) $request->session()->get('compras_desde_orden_compra_id', 0);
                    if ($idOrd > 0) {
                        $oc = OrdenCompra::find($idOrd);
                        $rfcXml = strtoupper(trim((string) ($result['datos']['rfc_emisor'] ?? '')));
                        $rfcOrd = strtoupper(trim((string) ($oc->proveedor_rfc ?? '')));
                        if (! $oc || $rfcOrd === '' || $rfcXml === '' || $rfcOrd !== $rfcXml) {
                            $request->session()->forget('compras_desde_orden_compra_id');
                        }
                    }
                }

                $this->limpiarPdfTempCfdiSesion($request);
                if ($request->hasFile('pdf_file')) {
                    $request->session()->put(
                        'compras_cfdi_pdf_temp',
                        $request->file('pdf_file')->store('temp/compras-cfdi-pdf', 'local')
                    );
                }

                $request->session()->put('compras_cfdi_precarga', $result['datos']);
                $request->session()->forget('compras_cfdi_linea_producto');

                return redirect()->route('compras.crear-desde-cfdi');
            }

            return back()->with('error', $result['message']);
        }

        $ordenOrigenConversion = null;
        $entradaAnticipada = null;
        $svcOrden = app(FacturaCompraDesdeOrdenCompraService::class);

        if ($request->filled('entrada_anticipada_id')) {
            $ea = EntradaAnticipada::with(['detalles.producto', 'proveedor', 'ordenCompra'])->find($request->integer('entrada_anticipada_id'));
            if (! $ea || ! $ea->puedeFacturarse()) {
                return redirect()->route('entradas-anticipadas.index')
                    ->with('error', 'La entrada anticipada no admite facturación por CFDI.');
            }

            app(EntradaAnticipadaService::class)->normalizarImportesDetalle($ea);
            $ea->refresh()->load(['detalles.producto', 'proveedor', 'ordenCompra']);

            $request->session()->put('compras_desde_entrada_anticipada_id', $ea->id);
            $request->session()->forget('compras_desde_orden_compra_id');
            $entradaAnticipada = $ea;
        } elseif (! $request->filled('orden_compra_id')) {
            if (! $request->session()->has('compras_cfdi_precarga')) {
                $request->session()->forget('compras_desde_entrada_anticipada_id');
                $this->limpiarPdfTempCfdiSesion($request);
            }
            $request->session()->forget('compras_desde_orden_compra_id');
        } else {
            $request->session()->forget('compras_desde_entrada_anticipada_id');
            $this->limpiarPdfTempCfdiSesion($request);
            $oc = OrdenCompra::find($request->integer('orden_compra_id'));
            if ($oc && $svcOrden->ordenPuedeConvertirse($oc)) {
                $request->session()->put('compras_desde_orden_compra_id', $oc->id);
                $ordenOrigenConversion = $oc;
            }
        }

        return view('compras.upload-cfdi', compact('ordenOrigenConversion', 'entradaAnticipada'));
    }

    /**
     * Formulario de compra precargado desde CFDI (sin guardar aún). Permite vincular productos al detalle.
     */
    public function crearDesdeCfdi(Request $request)
    {
        $datos = $request->session()->get('compras_cfdi_precarga');
        if (! $datos) {
            return redirect()->route('compras.upload-cfdi')->with('error', 'No hay datos de CFDI. Sube el XML de nuevo.');
        }

        $entradaAnticipada = null;
        $mapEaProductoIds = [];
        $mapEaDetallePorProducto = [];
        $eaIdSesion = (int) $request->session()->get('compras_desde_entrada_anticipada_id', 0);
        if ($eaIdSesion > 0) {
            $ea = EntradaAnticipada::with(['detalles.producto', 'proveedor', 'ordenCompra'])->find($eaIdSesion);
            if (! $ea || ! $ea->puedeFacturarse()) {
                $request->session()->forget('compras_desde_entrada_anticipada_id');

                return redirect()->route('compras.upload-cfdi')
                    ->with('error', 'La entrada anticipada ya no admite facturación por CFDI.');
            }

            app(EntradaAnticipadaService::class)->normalizarImportesDetalle($ea);
            $ea->refresh()->load(['detalles.producto', 'proveedor', 'ordenCompra']);
            $entradaAnticipada = $ea;
            $mapEaProductoIds = $ea->detalles
                ->filter(fn ($d) => $d->producto_id)
                ->mapWithKeys(fn ($d) => [(int) $d->producto_id => (int) $d->id])
                ->all();
            $mapEaDetallePorProducto = $ea->detalles
                ->filter(fn ($d) => $d->producto_id)
                ->mapWithKeys(fn ($d) => [
                    (int) $d->producto_id => [
                        'detalle_id' => (int) $d->id,
                        'descripcion_ea' => (string) $d->descripcion,
                    ],
                ])
                ->all();
        }

        $ordenConversionCfdi = null;
        if (! $entradaAnticipada) {
            $idOrdConv = (int) $request->session()->get('compras_desde_orden_compra_id', 0);
            if ($idOrdConv > 0) {
                $oc = OrdenCompra::find($idOrdConv);
                if ($oc && app(FacturaCompraDesdeOrdenCompraService::class)->ordenPuedeConvertirse($oc)) {
                    $ordenConversionCfdi = $oc;
                } else {
                    $request->session()->forget('compras_desde_orden_compra_id');
                }
            }
        }

        $empresa = Empresa::principal();
        $proveedorEaOriginal = $entradaAnticipada?->proveedor;
        $rfcXmlNorm = strtoupper(preg_replace('/\s+/', '', (string) ($datos['rfc_emisor'] ?? '')));
        $proveedor = $this->resolverProveedorParaCfdi($datos, $entradaAnticipada);

        // Mapeo: codigo de proveedor (NoIdentificacion) -> producto_id
        $productoProveedorMap = [];
        if ($proveedor) {
            $productoProveedorMap = ProductoProveedor::with('producto')
                ->where('proveedor_id', $proveedor->id)
                ->get()
                ->filter(fn ($pp) => ! empty($pp->codigo) && $pp->producto)
                ->mapWithKeys(function ($pp) {
                    return [strtoupper(trim((string) $pp->codigo)) => $pp->producto];
                })
                ->all();
        }

        $lineaProductoRaw = (array) $request->session()->get('compras_cfdi_linea_producto', []);
        $productosPorLinea = [];
        foreach ($lineaProductoRaw as $idx => $pid) {
            $idx = (int) $idx;
            if ($pid && ($p = Producto::find((int) $pid))) {
                $productosPorLinea[$idx] = $p;
            }
        }

        $descripcionPorIndiceLineaCfdi = [];
        $descripcionesConNoIdentCfdi = [];
        foreach (($datos['conceptos'] ?? []) as $i => $c) {
            $descripcionPorIndiceLineaCfdi[$i] = (string) ($c['descripcion'] ?? '');
            if (trim((string) ($c['no_identificacion'] ?? '')) !== '') {
                $descripcionesConNoIdentCfdi[] = (string) ($c['descripcion'] ?? '');
            }
        }

        $folioInterno = FacturaCompra::generarFolioInterno();
        $previewCorreccionUtilidad = ['lineas' => 0, 'folios' => []];
        if ($entradaAnticipada) {
            $previewCorreccionUtilidad = app(FacturaCompraDesdeEntradaAnticipadaService::class)
                ->previsualizarCorreccionCostoTimbradoParaEa($entradaAnticipada);
        }

        return view('compras.crear-desde-cfdi', compact(
            'datos',
            'empresa',
            'proveedor',
            'proveedorEaOriginal',
            'rfcXmlNorm',
            'productoProveedorMap',
            'productosPorLinea',
            'descripcionPorIndiceLineaCfdi',
            'descripcionesConNoIdentCfdi',
            'folioInterno',
            'ordenConversionCfdi',
            'entradaAnticipada',
            'mapEaProductoIds',
            'mapEaDetallePorProducto',
            'previewCorreccionUtilidad'
        ));
    }

    /**
     * Resuelve proveedor para el formulario CFDI: prioriza RFC del emisor;
     * en EA, si el de la entrada ya calza por RFC se conserva; si no, sugiere el del RFC.
     */
    private function resolverProveedorParaCfdi(array $datos, ?EntradaAnticipada $entradaAnticipada): ?Proveedor
    {
        $rfcXml = strtoupper(preg_replace('/\s+/', '', (string) ($datos['rfc_emisor'] ?? '')));
        $proveedorEa = $entradaAnticipada?->proveedor;

        if ($entradaAnticipada) {
            $rfcEa = strtoupper(preg_replace('/\s+/', '', (string) ($proveedorEa?->rfc ?? '')));
            if ($proveedorEa && $rfcXml !== '' && $rfcEa === $rfcXml) {
                return $proveedorEa;
            }
            if ($rfcXml !== '') {
                $porRfc = Proveedor::query()
                    ->whereRaw('UPPER(TRIM(rfc)) = ?', [$rfcXml])
                    ->orderBy('id')
                    ->first();
                if ($porRfc) {
                    return $porRfc;
                }
            }

            // Si el RFC no calza, no fijar el de la EA: el usuario debe elegir / crear el correcto.
            return null;
        }

        if ($rfcXml === '') {
            return null;
        }

        return Proveedor::query()
            ->whereRaw('UPPER(TRIM(rfc)) = ?', [$rfcXml])
            ->orderBy('id')
            ->first();
    }

    /**
     * Comprueba si la descripción del CFDI es muy similar (>80%) y casi idéntica al nombre o descripción de algún producto activo
     * (no bloquea variantes con varias diferencias p. ej. otra talla, aunque similar_text sea alto).
     */
    public function verificarSimilitudDescripcionCfdi(Request $request)
    {
        if ($request->filled('descripciones') && is_array($request->input('descripciones'))) {
            $list = $request->input('descripciones');
            if (! is_array($list)) {
                return response()->json(['similar' => false]);
            }
            foreach ($list as $d) {
                $nombre = $this->nombreProductoActivoSiDescripcionSuperaSimilitud((string) $d);
                if ($nombre !== null) {
                    return response()->json([
                        'similar' => true,
                        'message' => $this->mensajeSimilitudDescripcionProducto($nombre),
                    ]);
                }
            }

            return response()->json(['similar' => false]);
        }

        $desc = mb_substr(trim((string) $request->input('descripcion', '')), 0, 2000);
        if (mb_strlen($desc) < 15) {
            return response()->json(['similar' => false]);
        }
        $nombre = $this->nombreProductoActivoSiDescripcionSuperaSimilitud($desc);
        if ($nombre !== null) {
            return response()->json([
                'similar' => true,
                'message' => $this->mensajeSimilitudDescripcionProducto($nombre),
            ]);
        }

        return response()->json(['similar' => false]);
    }

    /**
     * Evalúa advertencias al vincular un producto del catálogo en CFDI + entrada anticipada.
     */
    public function evaluarVinculoProductoCfdiEa(Request $request)
    {
        if ((int) $request->session()->get('compras_desde_entrada_anticipada_id', 0) <= 0) {
            return response()->json(['error' => 'Sesión de entrada anticipada no válida.'], 403);
        }

        $validated = $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'concepto_index' => 'required|integer|min:0',
        ]);

        $datos = $request->session()->get('compras_cfdi_precarga');
        if (! $datos) {
            return response()->json(['error' => 'Sesión de CFDI expirada.'], 422);
        }

        $conceptos = $datos['conceptos'] ?? [];
        $idx = (int) $validated['concepto_index'];
        if (! isset($conceptos[$idx])) {
            return response()->json(['error' => 'Partida del CFDI no válida.'], 422);
        }

        $producto = Producto::findOrFail((int) $validated['producto_id']);
        $descripcionCfdi = (string) ($conceptos[$idx]['descripcion'] ?? '');

        $eaId = (int) $request->session()->get('compras_desde_entrada_anticipada_id');
        $ea = EntradaAnticipada::with('detalles')->find($eaId);
        $eaDet = $ea?->detalles->firstWhere('producto_id', $producto->id);
        $enEa = $eaDet !== null;

        $evalProducto = $this->evaluarDiferenciaDescripcionProducto($descripcionCfdi, (string) $producto->nombre);
        $evalEa = $enEa
            ? $this->evaluarDiferenciaDescripcionProducto($descripcionCfdi, (string) $eaDet->descripcion)
            : ['diferencia_considerable' => false, 'nombres_diferentes' => false, 'casi_identicas' => true];

        $advertencias = [];
        if (! $enEa) {
            $advertencias[] = 'Este producto no está en la entrada anticipada. Verifique que sea el correcto.';
        }
        if ($evalProducto['diferencia_considerable']) {
            $advertencias[] = 'La descripción del CFDI difiere considerablemente del nombre en catálogo («'.mb_substr(trim($producto->nombre), 0, 80).'»).';
        }
        if ($enEa && $evalEa['diferencia_considerable']) {
            $advertencias[] = 'La descripción del CFDI difiere de la registrada en la entrada («'.mb_substr(trim((string) $eaDet->descripcion), 0, 80).'»).';
        }

        return response()->json([
            'en_ea' => $enEa,
            'ea_detalle_id' => $enEa ? (int) $eaDet->id : null,
            'advertencias' => $advertencias,
            'requiere_confirmacion' => ! empty($advertencias),
            'sugerir_actualizar_nombre' => ! $evalProducto['casi_identicas'] && trim($descripcionCfdi) !== '',
            'nombre_actual' => (string) $producto->nombre,
            'descripcion_cfdi' => $descripcionCfdi,
            'no_identificacion' => trim((string) ($conceptos[$idx]['no_identificacion'] ?? '')),
        ]);
    }

    /**
     * Vincula producto desde lupa en CFDI + EA: actualiza nombre (opcional) y código proveedor.
     */
    public function vincularProductoLineaCfdiEa(Request $request)
    {
        $eaId = (int) $request->session()->get('compras_desde_entrada_anticipada_id', 0);
        if ($eaId <= 0) {
            return response()->json(['error' => 'Sesión de entrada anticipada no válida.'], 403);
        }

        $datos = $request->session()->get('compras_cfdi_precarga');
        if (! $datos) {
            return response()->json(['error' => 'Sesión de CFDI expirada.'], 422);
        }

        $validated = $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'concepto_index' => 'required|integer|min:0',
            'actualizar_nombre' => 'nullable|boolean',
        ]);

        $conceptos = $datos['conceptos'] ?? [];
        $idx = (int) $validated['concepto_index'];
        if (! isset($conceptos[$idx])) {
            return response()->json(['error' => 'Partida del CFDI no válida.'], 422);
        }

        $ea = EntradaAnticipada::with(['detalles', 'proveedor'])->findOrFail($eaId);
        if (! $ea->puedeFacturarse()) {
            return response()->json(['error' => 'La entrada anticipada ya no admite facturación.'], 422);
        }

        $producto = Producto::findOrFail((int) $validated['producto_id']);
        $concepto = $conceptos[$idx];
        $noIdent = strtoupper(trim((string) ($concepto['no_identificacion'] ?? '')));
        $codigoProveedorActualizado = false;

        DB::beginTransaction();
        try {
            if ($request->boolean('actualizar_nombre')) {
                $nuevoNombre = mb_substr(trim((string) ($concepto['descripcion'] ?? '')), 0, 255);
                if ($nuevoNombre !== '') {
                    $producto->update(['nombre' => $nuevoNombre]);
                    $producto->refresh();
                }
            }

            if ($noIdent !== '' && $ea->proveedor_id) {
                $this->sincronizarCodigoProveedorProducto($producto, $ea->proveedor, $noIdent);
                $codigoProveedorActualizado = true;

                $eaDet = $ea->detalles->firstWhere('producto_id', $producto->id);
                if ($eaDet) {
                    $eaDet->update(['codigo_proveedor' => $noIdent]);
                }
            }

            $linea = (array) $request->session()->get('compras_cfdi_linea_producto', []);
            $linea[$idx] = $producto->id;
            $request->session()->put('compras_cfdi_linea_producto', $linea);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['error' => $e->getMessage()], 422);
        }

        $eaDetalleId = $ea->detalles->firstWhere('producto_id', $producto->id)?->id;

        return response()->json([
            'ok' => true,
            'producto_id' => $producto->id,
            'codigo' => $producto->codigo,
            'nombre' => $producto->nombre,
            'ea_detalle_id' => $eaDetalleId ? (int) $eaDetalleId : null,
            'codigo_proveedor_actualizado' => $codigoProveedorActualizado,
            'no_identificacion' => $noIdent,
        ]);
    }

    private function mensajeSimilitudDescripcionProducto(string $nombreProductoCoincidente): string
    {
        $n = mb_substr(trim($nombreProductoCoincidente), 0, 200);

        return 'El texto de la descripción coincide en más de un 80% con un producto en la base («'.$n.'»). Por favor busque en la lupita si el producto existe.';
    }

    /**
     * @return string|null nombre del producto activo si similar_text > 80% y además el texto es casi el mismo (no aplica a variantes tipo otra talla/medida).
     */
    private function nombreProductoActivoSiDescripcionSuperaSimilitud(string $descripcionCfdi): ?string
    {
        $desc = mb_strtoupper(trim($descripcionCfdi));
        if (mb_strlen($desc) < 15) {
            return null;
        }

        foreach (Producto::query()->where('activo', true)->select(['id', 'nombre', 'descripcion'])->cursor() as $p) {
            foreach ([$p->nombre, $p->descripcion] as $campo) {
                if (! is_string($campo) || trim($campo) === '') {
                    continue;
                }
                $cmp = mb_strtoupper(trim($campo));
                if (mb_strlen($cmp) < 10) {
                    continue;
                }
                $percent = 0.0;
                similar_text($desc, $cmp, $percent);
                if ($percent > 80 && $this->sonDescripcionesCasiIdenticasParaBloqueoSimilitud($desc, $cmp)) {
                    return (string) $p->nombre;
                }
            }
        }

        return null;
    }

    /**
     * Misma referencia comercial salvo errores mínimos de tecleo (pocas ediciones vs longitud).
     * Si difiere más (p. ej. otra talla), aunque similar_text sea alto, no bloquea la creación desde CFDI.
     */
    private function sonDescripcionesCasiIdenticasParaBloqueoSimilitud(string $desc, string $cmp): bool
    {
        $a = mb_strtoupper(preg_replace('/\s+/u', ' ', trim($desc)));
        $b = mb_strtoupper(preg_replace('/\s+/u', ' ', trim($cmp)));
        if ($a === $b) {
            return true;
        }

        $aNorm = $this->asciiParaDistanciaEdicion($a);
        $bNorm = $this->asciiParaDistanciaEdicion($b);
        $maxLen = max(strlen($aNorm), strlen($bNorm));
        if ($maxLen < 10) {
            return $aNorm === $bNorm;
        }
        if ($maxLen > 255) {
            $aNorm = substr($aNorm, 0, 255);
            $bNorm = substr($bNorm, 0, 255);
            $maxLen = 255;
        }

        $dist = levenshtein($aNorm, $bNorm);
        if ($dist < 0) {
            return false;
        }

        // Máximo de ediciones permitidas para considerar "el mismo" texto: ~2% de la longitud (mín. 1).
        $maxEdiciones = max(1, (int) floor($maxLen * 0.02));

        return $dist <= $maxEdiciones;
    }

    private function asciiParaDistanciaEdicion(string $s): string
    {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);

        return (is_string($t) && $t !== '') ? $t : preg_replace('/[^\x20-\x7E]/u', '', $s);
    }

    /**
     * @return array{casi_identicas: bool, diferencia_considerable: bool, nombres_diferentes: bool}
     */
    private function evaluarDiferenciaDescripcionProducto(string $descripcionCfdi, string $textoComparar): array
    {
        $desc = mb_strtoupper(trim($descripcionCfdi));
        $cmp = mb_strtoupper(trim($textoComparar));

        if ($desc === '' || $cmp === '') {
            return [
                'casi_identicas' => false,
                'diferencia_considerable' => false,
                'nombres_diferentes' => false,
            ];
        }

        $casiIdenticas = $this->sonDescripcionesCasiIdenticasParaBloqueoSimilitud($desc, $cmp);
        if ($casiIdenticas) {
            return [
                'casi_identicas' => true,
                'diferencia_considerable' => false,
                'nombres_diferentes' => false,
            ];
        }

        $percent = 0.0;
        similar_text($desc, $cmp, $percent);

        $aNorm = $this->asciiParaDistanciaEdicion($desc);
        $bNorm = $this->asciiParaDistanciaEdicion($cmp);
        $maxLen = max(strlen($aNorm), strlen($bNorm));
        $dist = -1;
        if ($maxLen > 0) {
            $dist = levenshtein(
                substr($aNorm, 0, 255),
                substr($bNorm, 0, 255)
            );
        }
        $umbralEdiciones = max(3, (int) floor($maxLen * 0.08));
        $diferenciaConsiderable = $percent < 45 || ($dist >= 0 && $dist > $umbralEdiciones);

        return [
            'casi_identicas' => false,
            'diferencia_considerable' => $diferenciaConsiderable,
            'nombres_diferentes' => $desc !== $cmp,
        ];
    }

    private function sincronizarCodigoProveedorProducto(Producto $producto, Proveedor $proveedor, string $codigo): void
    {
        $codigoNorm = strtoupper(trim($codigo));
        if ($codigoNorm === '') {
            return;
        }

        $conflicto = ProductoProveedor::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('producto_id', '!=', $producto->id)
            ->get()
            ->contains(fn ($pp) => strtoupper(trim((string) ($pp->codigo ?? ''))) === $codigoNorm);

        if ($conflicto) {
            throw new \RuntimeException("El código de proveedor «{$codigoNorm}» ya está asignado a otro producto.");
        }

        ProductoProveedor::updateOrCreate(
            [
                'producto_id' => $producto->id,
                'proveedor_id' => $proveedor->id,
            ],
            ['codigo' => $codigoNorm]
        );
    }

    private function bloquearCreacionProductoDesdeCfdiSiEntradaAnticipada(Request $request): ?\Illuminate\Http\RedirectResponse
    {
        if ((int) $request->session()->get('compras_desde_entrada_anticipada_id', 0) > 0) {
            return redirect()->route('compras.crear-desde-cfdi')
                ->with('error', 'En facturación por entrada anticipada no se pueden crear productos. Use la lupa para relacionar con el catálogo.');
        }

        return null;
    }

    /**
     * Guardar compra desde formulario precargado por CFDI (con producto_id en cada línea para inventario).
     */
    public function storeDesdeCfdi(Request $request)
    {
        $datos = $request->session()->get('compras_cfdi_precarga');
        if (! $datos) {
            return redirect()->route('compras.upload-cfdi')->with('error', 'Sesión de CFDI expirada. Sube el XML de nuevo.');
        }

        $eaIdSesion = (int) $request->session()->get('compras_desde_entrada_anticipada_id', 0);
        if ($eaIdSesion > 0) {
            return $this->storeDesdeEntradaAnticipadaCfdi($request, $datos, $eaIdSesion);
        }

        $productos = $request->input('productos', []);
        foreach ($productos as $k => $p) {
            if (isset($p['producto_id']) && $p['producto_id'] === '') {
                $productos[$k]['producto_id'] = null;
            }
        }
        $request->merge(['productos' => $productos]);

        $validated = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'fecha_emision' => 'required|date',
            'forma_pago' => 'nullable|string|max:2',
            'metodo_pago' => 'nullable|string|max:3',
            'productos' => 'required|array|min:1',
            'productos.*.concepto_index' => 'required|integer|min:0',
            'productos.*.producto_id' => 'nullable|exists:productos,id',
        ]);

        $conceptos = $datos['conceptos'] ?? [];
        foreach ($validated['productos'] as $p) {
            $idx = (int) $p['concepto_index'];
            if (! isset($conceptos[$idx])) {
                return back()->with('error', 'Datos de detalle inválidos.');
            }
        }

        // Evitar guardado con líneas sin producto vinculado (siempre se necesita para recibir/inventario).
        foreach ($validated['productos'] as $p) {
            if (empty($p['producto_id'])) {
                return back()->withInput()->with('error', 'Faltan productos por vincular en el detalle. Usa la lupa o crea los productos faltantes.');
            }
        }

        $empresa = Empresa::principal();
        $proveedor = Proveedor::findOrFail($validated['proveedor_id']);

        $ordenCompraDesdeSesionId = (int) $request->session()->get('compras_desde_orden_compra_id', 0);
        $ordenCtx = null;
        if ($ordenCompraDesdeSesionId > 0) {
            $ordenCtx = OrdenCompra::with('cuentaPorPagar')->find($ordenCompraDesdeSesionId);
            if (! $ordenCtx || ! app(FacturaCompraDesdeOrdenCompraService::class)->ordenPuedeConvertirse($ordenCtx)) {
                $request->session()->forget('compras_desde_orden_compra_id');

                return back()->with('error', 'La orden de compra ya no admite vincular este CFDI.');
            }
            if ((int) $ordenCtx->proveedor_id !== (int) $proveedor->id) {
                return back()->with('error', 'El proveedor debe coincidir con la orden de compra de origen.');
            }
        }

        $subtotal = (float) ($datos['subtotal'] ?? 0);
        $descuento = (float) ($datos['descuento'] ?? 0);
        $total = (float) ($datos['total'] ?? 0);
        $serie = $this->normalizarSerieFacturaCompra((string) ($datos['serie'] ?? ''));

        DB::beginTransaction();
        try {
            $folioInterno = FacturaCompra::generarFolioInterno();
            $fc = FacturaCompra::create([
                'serie' => $serie,
                'folio' => $datos['folio'] ?? '0',
                'folio_interno' => $folioInterno,
                'tipo_comprobante' => $datos['tipo_comprobante'] ?? 'E',
                'estado' => 'registrada',
                'origen' => $ordenCtx ? 'orden_compra' : 'cfdi_directo',
                'proveedor_id' => $proveedor->id,
                'empresa_id' => $empresa->id,
                'rfc_emisor' => $datos['rfc_emisor'] ?? $proveedor->rfc,
                'nombre_emisor' => $datos['nombre_emisor'] ?? $proveedor->nombre,
                'regimen_fiscal_emisor' => $datos['regimen_fiscal_emisor'] ?? $proveedor->regimen_fiscal,
                'rfc_receptor' => $datos['rfc_receptor'] ?? $empresa->rfc,
                'nombre_receptor' => $datos['nombre_receptor'] ?? $empresa->razon_social,
                'regimen_fiscal_receptor' => $datos['regimen_fiscal_receptor'] ?? $empresa->regimen_fiscal,
                'lugar_expedicion' => $datos['lugar_expedicion'] ?? null,
                'fecha_emision' => $validated['fecha_emision'],
                'forma_pago' => $validated['forma_pago'] ?? null,
                'metodo_pago' => $validated['metodo_pago'] ?? 'PUE',
                'moneda' => $datos['moneda'] ?? 'MXN',
                'tipo_cambio' => (float) ($datos['tipo_cambio'] ?? 1),
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'total' => $total,
                'uuid' => $datos['uuid'] ?? null,
                'fecha_timbrado' => ! empty($datos['fecha_timbrado']) ? $datos['fecha_timbrado'] : null,
                'no_certificado_sat' => $datos['no_certificado_sat'] ?? null,
                'xml_content' => $datos['xml_content'] ?? null,
                'usuario_id' => auth()->id(),
            ]);

            foreach ($validated['productos'] as $index => $p) {
                $concepto = $conceptos[(int) $p['concepto_index']];
                $producto = ! empty($p['producto_id']) ? Producto::find($p['producto_id']) : null;
                $detalle = FacturaCompraDetalle::create([
                    'factura_compra_id' => $fc->id,
                    'producto_id' => $producto?->id,
                    'clave_prod_serv' => $producto?->clave_sat ?? $concepto['clave_prod_serv'] ?? '01010101',
                    'clave_unidad' => $producto?->clave_unidad_sat ?? $concepto['clave_unidad'] ?? 'H87',
                    'unidad' => $producto?->unidad ?? $concepto['unidad'] ?? 'Pieza',
                    // En compra desde CFDI conservamos el NoIdentificacion original (código del proveedor).
                    'no_identificacion' => $concepto['no_identificacion'] ?? $producto?->codigo,
                    'descripcion' => $concepto['descripcion'] ?? '',
                    'cantidad' => $concepto['cantidad'],
                    'valor_unitario' => $concepto['valor_unitario'],
                    'importe' => $concepto['importe'],
                    'descuento' => $concepto['descuento'] ?? 0,
                    'base_impuesto' => $concepto['base_impuesto'] ?? $concepto['importe'],
                    'objeto_impuesto' => $producto && in_array($producto->objeto_impuesto ?? '02', ['02', '03']) ? '02' : ($concepto['objeto_impuesto'] ?? '02'),
                    'orden' => $index,
                ]);
                foreach ($concepto['impuestos'] ?? [] as $imp) {
                    FacturaCompraImpuesto::create([
                        'factura_compra_detalle_id' => $detalle->id,
                        'tipo' => $imp['tipo'],
                        'impuesto' => $imp['impuesto'],
                        'tipo_factor' => $imp['tipo_factor'] ?? 'Tasa',
                        'tasa_o_cuota' => $imp['tasa_o_cuota'] ?? null,
                        'base' => $imp['base'],
                        'importe' => $imp['importe'] ?? null,
                    ]);
                }
            }

            $diasCredito = (int) ($proveedor->dias_credito ?? 0);
            $omitirCuentaPorPagarNueva = $ordenCtx && $ordenCtx->cuentaPorPagar;
            if (($validated['metodo_pago'] ?? '') === 'PPD' && $diasCredito > 0 && ! $omitirCuentaPorPagarNueva) {
                $fechaEmision = \Carbon\Carbon::parse($fc->fecha_emision);
                $fechaVencimiento = $fechaEmision->copy()->addDays($diasCredito);
                CuentaPorPagar::create([
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

            if ($ordenCtx) {
                app(FacturaCompraDesdeOrdenCompraService::class)->vincularFacturaCreadaDesdeCfdi($ordenCtx, $fc);
            }

            $request->session()->forget(['compras_cfdi_precarga', 'compras_cfdi_linea_producto', 'compras_desde_orden_compra_id']);
            DB::commit();

            event(new FacturaCompraDesdeCfdiRegistrada($fc->fresh(['detalles.producto'])));

            return redirect()->route('compras.show', $fc->id)
                ->with('success', 'Compra guardada. En la ficha de la compra puede usar «Recibir mercancía» para registrar la entrada en inventario.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Error al guardar: '.$e->getMessage());
        }
    }

    /**
     * `facturas_compra.serie` es VARCHAR(5). Algunos CFDI traen "INV/2026/".
     * Normalizamos para que quepa (tomamos el segmento inicial y truncamos).
     */
    private function normalizarSerieFacturaCompra(string $serie): string
    {
        $s = trim($serie);
        if ($s === '') {
            return '';
        }

        // Si viene con slashes, tomamos el primer segmento.
        if (str_contains($s, '/')) {
            $parts = array_filter(explode('/', $s), fn ($p) => trim((string) $p) !== '');
            if (! empty($parts)) {
                $s = (string) $parts[0];
            }
        }

        // Eliminamos caracteres de separador por seguridad.
        $s = str_replace(['/', '\\'], '', $s);
        $s = trim($s);

        return mb_substr($s, 0, 5);
    }

    /**
     * Crea el proveedor faltante con datos precargados desde el CFDI.
     */
    public function agregarProveedorDesdeCfdi(Request $request)
    {
        $datos = $request->session()->get('compras_cfdi_precarga');
        if (! $datos) {
            return redirect()->route('compras.upload-cfdi')->with('error', 'No hay datos de CFDI. Sube el XML de nuevo.');
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'rfc' => 'required|string|max:13',
            'dias_credito' => 'nullable|integer|min:0',
        ]);

        $nombre = (string) $validated['nombre'];
        $rfc = strtoupper(preg_replace('/\s/', '', (string) $validated['rfc']));
        $diasCredito = (int) ($validated['dias_credito'] ?? 0);

        $proveedor = Proveedor::whereRaw('UPPER(rfc) = UPPER(?)', [$rfc])->first();
        if ($proveedor) {
            // Conservamos nombre actual si ya existe, pero actualizamos RFC (normalizado) y días si vienen desde el CFDI.
            $proveedor->update([
                'rfc' => $rfc,
                'dias_credito' => $diasCredito,
                'nombre' => $proveedor->nombre ?: $nombre,
            ]);
        } else {
            $proveedor = Proveedor::create([
                'nombre' => $nombre,
                'rfc' => $rfc,
                'dias_credito' => $diasCredito,
                'activo' => true,
            ]);
        }

        return redirect()->route('compras.crear-desde-cfdi')->with('success', 'Proveedor agregado desde el CFDI.');
    }

    /**
     * Crea un producto desde una sola partida del CFDI (➕ por línea), con o sin NoIdentificacion.
     */
    public function crearProductoLineaDesdeCfdi(Request $request)
    {
        if ($redirect = $this->bloquearCreacionProductoDesdeCfdiSiEntradaAnticipada($request)) {
            return $redirect;
        }

        $datos = $request->session()->get('compras_cfdi_precarga');
        if (! $datos) {
            return redirect()->route('compras.upload-cfdi')->with('error', 'No hay datos de CFDI. Sube el XML de nuevo.');
        }

        $validated = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'concepto_index' => 'required|integer|min:0',
            'forzar_sin_validacion_similitud' => 'nullable|boolean',
        ]);

        $conceptos = $datos['conceptos'] ?? [];
        $idx = (int) $validated['concepto_index'];
        if (! isset($conceptos[$idx]) || ! is_array($conceptos[$idx])) {
            return redirect()->route('compras.crear-desde-cfdi')->with('error', 'Partida del CFDI no válida.');
        }

        $linea = (array) $request->session()->get('compras_cfdi_linea_producto', []);
        if (! empty($linea[$idx])) {
            return redirect()->route('compras.crear-desde-cfdi')->with('error', 'Esta línea ya tiene un producto agregado. Use la lupa si desea cambiarlo.');
        }

        $proveedor = Proveedor::findOrFail($validated['proveedor_id']);
        $concepto = $conceptos[$idx];
        $noIdent = strtoupper(trim((string) ($concepto['no_identificacion'] ?? '')));

        if ($noIdent !== '') {
            $ya = ProductoProveedor::with('producto')
                ->where('proveedor_id', $proveedor->id)
                ->get()
                ->first(fn ($pp) => strtoupper(trim((string) ($pp->codigo ?? ''))) === $noIdent);
            if ($ya && $ya->producto) {
                $linea[$idx] = $ya->producto_id;
                $request->session()->put('compras_cfdi_linea_producto', $linea);

                return redirect()->route('compras.crear-desde-cfdi')->with('success', 'Producto ya estaba relacionado con ese código de proveedor.');
            }
        }

        $forzar = $request->boolean('forzar_sin_validacion_similitud');
        if (! $forzar) {
            $similar = $this->nombreProductoActivoSiDescripcionSuperaSimilitud((string) ($concepto['descripcion'] ?? ''));
            if ($similar !== null) {
                return redirect()->route('compras.crear-desde-cfdi')->with('error', $this->mensajeSimilitudDescripcionProducto($similar));
            }
        }

        DB::beginTransaction();
        try {
            $tipoFactor = 'Exento';
            $tasaIva = 0.0;
            foreach (($concepto['impuestos'] ?? []) as $imp) {
                if (($imp['tipo'] ?? null) === 'traslado' && (string) ($imp['impuesto'] ?? '') === '002') {
                    $tasaIva = isset($imp['tasa_o_cuota']) ? (float) $imp['tasa_o_cuota'] : 0.0;
                    if ($tasaIva > 1) {
                        $tasaIva = $tasaIva / 100;
                    }
                    if ($tasaIva > 0) {
                        $tipoFactor = 'Tasa';
                    } else {
                        $tipoFactor = 'Exento';
                        $tasaIva = 0.0;
                    }
                    break;
                }
            }

            $precioUnitarioSinIva = (float) ($concepto['valor_unitario'] ?? 0);
            $nombreProducto = (string) ($concepto['descripcion'] ?? '') ?: 'Concepto';

            $psiNum = $this->obtenerSiguientePsiNumDesde(1);
            $codigoPsi = 'PSI-'.$psiNum;
            while (Producto::where('codigo', $codigoPsi)->exists()) {
                $psiNum++;
                $codigoPsi = 'PSI-'.$psiNum;
            }

            $producto = Producto::create([
                'codigo' => $codigoPsi,
                'nombre' => mb_substr($nombreProducto, 0, 255),
                'descripcion' => null,
                'categoria_id' => null,
                'clave_sat' => (string) ($concepto['clave_prod_serv'] ?? '01010101'),
                'clave_unidad_sat' => (string) ($concepto['clave_unidad'] ?? 'H87'),
                'unidad' => (string) ($concepto['unidad'] ?? 'Pieza'),
                'objeto_impuesto' => (string) ($concepto['objeto_impuesto'] ?? '02'),
                'tipo_impuesto' => '002',
                'tipo_factor' => $tipoFactor,
                'tasa_iva' => $tasaIva,
                'precio_venta' => $precioUnitarioSinIva,
                'costo' => $precioUnitarioSinIva,
                'costo_promedio' => $precioUnitarioSinIva,
                'requiere_revision_precio' => true,
                'stock_minimo' => 0,
                'stock_maximo' => 0,
                'controla_inventario' => true,
                'aplica_iva' => $tipoFactor !== 'Exento',
                'tasa_ieps' => 0,
                'stock' => 0,
                'activo' => true,
            ]);

            if ($noIdent !== '') {
                ProductoProveedor::updateOrCreate(
                    ['producto_id' => $producto->id, 'proveedor_id' => $proveedor->id],
                    ['codigo' => $noIdent]
                );
            }

            $linea[$idx] = $producto->id;
            $request->session()->put('compras_cfdi_linea_producto', $linea);

            DB::commit();

            $msgOk = 'Producto creado: '.$producto->codigo.'. Ya puede guardar la compra si todas las líneas están vinculadas.';
            if ($forzar) {
                $msgOk .= ' (Creación autorizada omitiendo aviso de similitud con el catálogo.)';
            }

            return redirect()->route('compras.crear-desde-cfdi')->with('success', $msgOk);
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->route('compras.crear-desde-cfdi')->with('error', 'Error al crear producto: '.$e->getMessage());
        }
    }

    /**
     * Crea productos y relaciones (producto_proveedores) faltantes usando NoIdentificacion del CFDI.
     */
    public function crearProductosDesdeCfdi(Request $request)
    {
        if ($redirect = $this->bloquearCreacionProductoDesdeCfdiSiEntradaAnticipada($request)) {
            return $redirect;
        }

        $datos = $request->session()->get('compras_cfdi_precarga');
        if (! $datos) {
            return redirect()->route('compras.upload-cfdi')->with('error', 'No hay datos de CFDI. Sube el XML de nuevo.');
        }

        $validated = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'forzar_sin_validacion_similitud' => 'nullable|boolean',
        ]);

        $forzar = $request->boolean('forzar_sin_validacion_similitud');
        $proveedor = Proveedor::findOrFail($validated['proveedor_id']);
        $conceptos = $datos['conceptos'] ?? [];
        if (empty($conceptos)) {
            return redirect()->route('compras.crear-desde-cfdi')->with('error', 'El CFDI no tiene conceptos para crear productos.');
        }

        DB::beginTransaction();
        try {
            // Mapa existente: codigo proveedor -> producto existente
            $existentes = ProductoProveedor::with('producto')
                ->where('proveedor_id', $proveedor->id)
                ->get()
                ->filter(fn ($pp) => ! empty($pp->codigo) && $pp->producto)
                ->mapWithKeys(function ($pp) {
                    return [strtoupper(trim((string) $pp->codigo)) => $pp->producto];
                })
                ->all();

            $psiNum = $this->obtenerSiguientePsiNumDesde(1);

            $creados = 0;
            foreach ($conceptos as $idx => $concepto) {
                $noIdent = strtoupper(trim((string) ($concepto['no_identificacion'] ?? '')));
                if ($noIdent === '') {
                    continue; // Sin NoIdentificacion no podemos crear la relación proveedor-producto.
                }

                if (isset($existentes[$noIdent])) {
                    continue; // Ya existe el producto relacionado para este proveedor.
                }

                if (! $forzar) {
                    $similar = $this->nombreProductoActivoSiDescripcionSuperaSimilitud((string) ($concepto['descripcion'] ?? ''));
                    if ($similar !== null) {
                        DB::rollBack();

                        return redirect()->route('compras.crear-desde-cfdi')->with('error', $this->mensajeSimilitudDescripcionProducto($similar));
                    }
                }

                // Crear producto
                $tipoFactor = 'Exento';
                $tasaIva = 0.0;
                foreach (($concepto['impuestos'] ?? []) as $imp) {
                    if (($imp['tipo'] ?? null) === 'traslado' && (string) ($imp['impuesto'] ?? '') === '002') {
                        $tasaIva = isset($imp['tasa_o_cuota']) ? (float) $imp['tasa_o_cuota'] : (float) 0;
                        // CFDI puede venir como 0.16 o como 16 (porcentaje).
                        if ($tasaIva > 1) {
                            $tasaIva = $tasaIva / 100;
                        }
                        if ($tasaIva > 0) {
                            $tipoFactor = 'Tasa';
                        } else {
                            $tipoFactor = 'Exento';
                            $tasaIva = 0.0;
                        }
                        break;
                    }
                }

                $precioUnitarioSinIva = (float) ($concepto['valor_unitario'] ?? 0);
                $nombreProducto = (string) ($concepto['descripcion'] ?? '') ?: 'Concepto';

                $codigoPsi = 'PSI-'.$psiNum;
                // Evita colisiones inesperadas: si ya existe, avanzamos.
                while (Producto::where('codigo', $codigoPsi)->exists()) {
                    $psiNum++;
                    $codigoPsi = 'PSI-'.$psiNum;
                }

                $producto = Producto::create([
                    'codigo' => $codigoPsi,
                    'nombre' => mb_substr($nombreProducto, 0, 255),
                    'descripcion' => null,
                    'categoria_id' => null,
                    'clave_sat' => (string) ($concepto['clave_prod_serv'] ?? '01010101'),
                    'clave_unidad_sat' => (string) ($concepto['clave_unidad'] ?? 'H87'),
                    'unidad' => (string) ($concepto['unidad'] ?? 'Pieza'),
                    'objeto_impuesto' => (string) ($concepto['objeto_impuesto'] ?? '02'),
                    'tipo_impuesto' => '002',
                    'tipo_factor' => $tipoFactor,
                    'tasa_iva' => $tasaIva,
                    'precio_venta' => $precioUnitarioSinIva,
                    'costo' => $precioUnitarioSinIva,
                    'costo_promedio' => $precioUnitarioSinIva,
                    'requiere_revision_precio' => true,
                    'stock_minimo' => 0,
                    'stock_maximo' => 0,
                    'controla_inventario' => true,
                    'aplica_iva' => $tipoFactor !== 'Exento',
                    'tasa_ieps' => 0,
                    'stock' => 0,
                    'activo' => true,
                ]);

                ProductoProveedor::updateOrCreate(
                    ['producto_id' => $producto->id, 'proveedor_id' => $proveedor->id],
                    ['codigo' => $noIdent]
                );

                $existentes[$noIdent] = $producto;
                $psiNum++;
                $creados++;
            }

            DB::commit();

            $msgOk = 'Productos creados y relacionados desde el CFDI: '.$creados.'.';
            if ($forzar && $creados > 0) {
                $msgOk .= ' (Creación autorizada omitiendo aviso de similitud con el catálogo.)';
            }

            return redirect()->route('compras.crear-desde-cfdi')->with('success', $msgOk);
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->route('compras.crear-desde-cfdi')->with('error', 'Error al crear productos: '.$e->getMessage());
        }
    }

    /**
     * Devuelve el siguiente número PSI disponible desde un punto (rellena gaps).
     */
    private function obtenerSiguientePsiNumDesde(int $desde): int
    {
        $usados = Producto::withTrashed()
            ->where('codigo', 'like', 'PSI-%')
            ->pluck('codigo')
            ->map(function ($codigo) {
                if (! is_string($codigo)) {
                    return null;
                }
                if (preg_match('/^PSI-(\d+)$/', $codigo, $m)) {
                    return (int) $m[1];
                }

                return null;
            })
            ->filter(fn ($n) => $n !== null)
            ->unique()
            ->values()
            ->all();

        $set = array_flip($usados);

        $n = max(1, $desde);
        while (isset($set[$n])) {
            $n++;
        }

        return $n;
    }

    public function verPDF(FacturaCompra $compra)
    {
        try {
            $compra->load(['detalles.producto', 'proveedor', 'empresa']);
            $pdfPath = app(PDFService::class)->generarFacturaCompraPDF($compra);

            return response()->file(storage_path('app/'.$pdfPath));
        } catch (\Exception $e) {
            return back()->with('error', 'Error al generar PDF: '.$e->getMessage());
        }
    }

    public function descargarPDF(FacturaCompra $compra)
    {
        try {
            $compra->load(['detalles.producto', 'proveedor', 'empresa']);
            $pdfPath = app(PDFService::class)->generarFacturaCompraPDF($compra);
            $nombreArchivo = 'Compra_'.preg_replace('/[^a-zA-Z0-9._-]+/', '_', $compra->folio_completo).'.pdf';

            return response()->download(
                storage_path('app/'.$pdfPath),
                $nombreArchivo
            );
        } catch (\Exception $e) {
            return back()->with('error', 'Error al descargar PDF: '.$e->getMessage());
        }
    }

    public function verPdfSubido(FacturaCompra $compra)
    {
        $ruta = $compra->resolverRutaArchivoLocal($compra->pdf_path);
        if (! $ruta) {
            return back()->with('error', 'PDF del proveedor no disponible');
        }

        return response()->file($ruta);
    }

    public function descargarPdfSubido(FacturaCompra $compra)
    {
        $ruta = $compra->resolverRutaArchivoLocal($compra->pdf_path);
        if (! $ruta) {
            return back()->with('error', 'PDF del proveedor no disponible');
        }

        $nombreArchivo = 'Compra_'.preg_replace('/[^a-zA-Z0-9._-]+/', '_', $compra->folio_completo).'_proveedor.pdf';

        return response()->download($ruta, $nombreArchivo);
    }

    public function verXml(FacturaCompra $compra)
    {
        $contenido = $this->obtenerContenidoXmlCompra($compra);
        if ($contenido === null) {
            return back()->with('error', 'XML no disponible');
        }

        $nombreArchivo = $this->nombreArchivoXmlCompra($compra);

        return response($contenido, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'inline; filename="'.$nombreArchivo.'"',
        ]);
    }

    public function descargarXml(FacturaCompra $compra)
    {
        $contenido = $this->obtenerContenidoXmlCompra($compra);
        if ($contenido === null) {
            return back()->with('error', 'XML no disponible');
        }

        $nombreArchivo = $this->nombreArchivoXmlCompra($compra);

        return response($contenido, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$nombreArchivo.'"',
        ]);
    }

    private function obtenerContenidoXmlCompra(FacturaCompra $compra): ?string
    {
        if (! empty(trim((string) $compra->xml_content))) {
            return $compra->xml_content;
        }

        $ruta = $compra->resolverRutaArchivoLocal($compra->xml_path);
        if ($ruta) {
            return file_get_contents($ruta) ?: null;
        }

        return null;
    }

    private function nombreArchivoXmlCompra(FacturaCompra $compra): string
    {
        $base = $compra->uuid
            ? preg_replace('/[^a-zA-Z0-9._-]+/', '_', $compra->uuid)
            : preg_replace('/[^a-zA-Z0-9._-]+/', '_', $compra->folio_completo);

        return $base.'.xml';
    }

    public function buscarProveedores(Request $request)
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        return response()->json(
            Proveedor::activos()
                ->buscar($q)
                ->limit(15)
                ->get(['id', 'codigo', 'nombre', 'nombre_comercial', 'rfc', 'dias_credito'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'codigo' => $p->codigo,
                    'nombre' => $p->nombre,
                    'nombre_comercial' => $p->nombre_comercial ?? '',
                    'etiqueta' => $p->etiqueta_con_codigo,
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

    private function limpiarPdfTempCfdiSesion(Request $request): void
    {
        $pdfTemp = $request->session()->get('compras_cfdi_pdf_temp');
        if ($pdfTemp) {
            $ruta = (new FacturaCompra)->resolverRutaArchivoLocal($pdfTemp);
            if ($ruta) {
                @unlink($ruta);
            }
        }
        $request->session()->forget('compras_cfdi_pdf_temp');
    }

    private function pdfSubidoDesdeTempSesion(Request $request): ?UploadedFile
    {
        $pdfTemp = $request->session()->get('compras_cfdi_pdf_temp');
        if (! $pdfTemp) {
            return null;
        }

        $ruta = (new FacturaCompra)->resolverRutaArchivoLocal($pdfTemp);
        if (! $ruta) {
            return null;
        }

        return new UploadedFile($ruta, basename($pdfTemp), 'application/pdf', null, true);
    }
}
