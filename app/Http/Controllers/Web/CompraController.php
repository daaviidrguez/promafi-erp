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
use App\Models\CuentaPorPagar;
use App\Models\InventarioMovimiento;
use App\Models\ProductoProveedor;
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
        $folio = FacturaCompra::generarFolioInterno();

        return view('compras.create', compact('empresa', 'folio'));
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

            $folioInterno = FacturaCompra::generarFolioInterno();
            $fc = FacturaCompra::create([
                'serie' => '',
                'folio' => $folioInterno,
                'folio_interno' => $folioInterno,
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
        $usoCfdi = $this->extraerUsoCfdiDeXml($compra->xml_content);
        return view('compras.show', compact('compra', 'usoCfdi'));
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
            if (!$loaded) {
                return null;
            }

            $xpath = new \DOMXPath($dom);
            $nodos = $xpath->query('//*[local-name()="Receptor"]');
            if (!$nodos || $nodos->length === 0) {
                return null;
            }

            foreach ($nodos as $node) {
                if (!($node instanceof \DOMElement)) {
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
        if (!$compra->puedeRecibirse()) {
            return back()->with('error', 'Solo se puede recibir mercancía en compras registradas');
        }
        DB::beginTransaction();
        try {
            foreach ($compra->detalles as $detalle) {
                if (!$detalle->producto_id || !$detalle->producto || !$detalle->producto->controla_inventario) {
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

    public function uploadCfdi(Request $request)
    {
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
                        if ($ext !== 'xml' && !in_array($mime, $xmlMimes, true)) {
                            $fail('El archivo debe ser XML (.xml).');
                        }
                    },
                ],
            ]);
            $content = file_get_contents($request->file('xml_file')->getRealPath());
            $service = app(FacturaCompraCfdiService::class);
            $result = $service->parsear($content);
            if ($result['success']) {
                $request->session()->put('compras_cfdi_precarga', $result['datos']);
                $request->session()->forget('compras_cfdi_linea_producto');
                return redirect()->route('compras.crear-desde-cfdi');
            }
            return back()->with('error', $result['message']);
        }
        return view('compras.upload-cfdi');
    }

    /**
     * Formulario de compra precargado desde CFDI (sin guardar aún). Permite vincular productos al detalle.
     */
    public function crearDesdeCfdi(Request $request)
    {
        $datos = $request->session()->get('compras_cfdi_precarga');
        if (!$datos) {
            return redirect()->route('compras.upload-cfdi')->with('error', 'No hay datos de CFDI. Sube el XML de nuevo.');
        }
        $empresa = Empresa::principal();
        $proveedor = !empty($datos['rfc_emisor'])
            ? Proveedor::whereRaw('UPPER(rfc) = UPPER(?)', [$datos['rfc_emisor']])->first()
            : null;

        // Mapeo: codigo de proveedor (NoIdentificacion) -> producto_id
        $productoProveedorMap = [];
        if ($proveedor) {
            $productoProveedorMap = ProductoProveedor::with('producto')
                ->where('proveedor_id', $proveedor->id)
                ->get()
                ->filter(fn ($pp) => !empty($pp->codigo) && $pp->producto)
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

        return view('compras.crear-desde-cfdi', compact(
            'datos',
            'empresa',
            'proveedor',
            'productoProveedorMap',
            'productosPorLinea',
            'descripcionPorIndiceLineaCfdi',
            'descripcionesConNoIdentCfdi',
            'folioInterno'
        ));
    }

    /**
     * Comprueba si la descripción del CFDI es muy similar (>80%) y casi idéntica al nombre o descripción de algún producto activo
     * (no bloquea variantes con varias diferencias p. ej. otra talla, aunque similar_text sea alto).
     */
    public function verificarSimilitudDescripcionCfdi(Request $request)
    {
        if ($request->filled('descripciones') && is_array($request->input('descripciones'))) {
            $list = $request->input('descripciones');
            if (!is_array($list)) {
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

    private function mensajeSimilitudDescripcionProducto(string $nombreProductoCoincidente): string
    {
        $n = mb_substr(trim($nombreProductoCoincidente), 0, 200);

        return 'El texto de la descripción coincide en más de un 80% con un producto en la base («' . $n . '»). Por favor busque en la lupita si el producto existe.';
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
                if (!is_string($campo) || trim($campo) === '') {
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
     * Guardar compra desde formulario precargado por CFDI (con producto_id en cada línea para inventario).
     */
    public function storeDesdeCfdi(Request $request)
    {
        $datos = $request->session()->get('compras_cfdi_precarga');
        if (!$datos) {
            return redirect()->route('compras.upload-cfdi')->with('error', 'Sesión de CFDI expirada. Sube el XML de nuevo.');
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
            if (!isset($conceptos[$idx])) {
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
                'fecha_timbrado' => !empty($datos['fecha_timbrado']) ? $datos['fecha_timbrado'] : null,
                'no_certificado_sat' => $datos['no_certificado_sat'] ?? null,
                'xml_content' => $datos['xml_content'] ?? null,
                'usuario_id' => auth()->id(),
            ]);

            foreach ($validated['productos'] as $index => $p) {
                $concepto = $conceptos[(int) $p['concepto_index']];
                $producto = !empty($p['producto_id']) ? Producto::find($p['producto_id']) : null;
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
            if (($validated['metodo_pago'] ?? '') === 'PPD' && $diasCredito > 0) {
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

            $request->session()->forget(['compras_cfdi_precarga', 'compras_cfdi_linea_producto']);
            DB::commit();
            return redirect()->route('compras.show', $fc->id)->with('success', 'Compra guardada. Use "Recibir mercancía" para registrar la entrada en inventario.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Error al guardar: ' . $e->getMessage());
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
            if (!empty($parts)) {
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
        if (!$datos) {
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
        $datos = $request->session()->get('compras_cfdi_precarga');
        if (!$datos) {
            return redirect()->route('compras.upload-cfdi')->with('error', 'No hay datos de CFDI. Sube el XML de nuevo.');
        }

        $validated = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'concepto_index' => 'required|integer|min:0',
            'forzar_sin_validacion_similitud' => 'nullable|boolean',
        ]);

        $conceptos = $datos['conceptos'] ?? [];
        $idx = (int) $validated['concepto_index'];
        if (!isset($conceptos[$idx]) || !is_array($conceptos[$idx])) {
            return redirect()->route('compras.crear-desde-cfdi')->with('error', 'Partida del CFDI no válida.');
        }

        $linea = (array) $request->session()->get('compras_cfdi_linea_producto', []);
        if (!empty($linea[$idx])) {
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
            $codigoPsi = 'PSI-' . $psiNum;
            while (Producto::where('codigo', $codigoPsi)->exists()) {
                $psiNum++;
                $codigoPsi = 'PSI-' . $psiNum;
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

            $msgOk = 'Producto creado: ' . $producto->codigo . '. Ya puede guardar la compra si todas las líneas están vinculadas.';
            if ($forzar) {
                $msgOk .= ' (Creación autorizada omitiendo aviso de similitud con el catálogo.)';
            }

            return redirect()->route('compras.crear-desde-cfdi')->with('success', $msgOk);
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->route('compras.crear-desde-cfdi')->with('error', 'Error al crear producto: ' . $e->getMessage());
        }
    }

    /**
     * Crea productos y relaciones (producto_proveedores) faltantes usando NoIdentificacion del CFDI.
     */
    public function crearProductosDesdeCfdi(Request $request)
    {
        $datos = $request->session()->get('compras_cfdi_precarga');
        if (!$datos) {
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
                ->filter(fn ($pp) => !empty($pp->codigo) && $pp->producto)
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

                $codigoPsi = 'PSI-' . $psiNum;
                // Evita colisiones inesperadas: si ya existe, avanzamos.
                while (Producto::where('codigo', $codigoPsi)->exists()) {
                    $psiNum++;
                    $codigoPsi = 'PSI-' . $psiNum;
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

            $msgOk = 'Productos creados y relacionados desde el CFDI: ' . $creados . '.';
            if ($forzar && $creados > 0) {
                $msgOk .= ' (Creación autorizada omitiendo aviso de similitud con el catálogo.)';
            }

            return redirect()->route('compras.crear-desde-cfdi')->with('success', $msgOk);
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->route('compras.crear-desde-cfdi')->with('error', 'Error al crear productos: ' . $e->getMessage());
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
                if (!is_string($codigo)) {
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
            $nombreArchivo = 'Compra_' . preg_replace('/[^a-zA-Z0-9._-]+/', '_', $compra->folio_completo) . '.pdf';

            return response()->download(
                storage_path('app/' . $pdfPath),
                $nombreArchivo
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
