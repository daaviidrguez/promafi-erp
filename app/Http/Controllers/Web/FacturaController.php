<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/FacturaController.php
// REEMPLAZA el contenido actual con este

use App\Helpers\IsrResicoHelper;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\CuentaPorCobrar;
use App\Models\Empresa;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\FacturaImpuesto;
use App\Models\FormaPago;
use App\Models\InventarioMovimiento;
use App\Models\LogisticaEnvio;
use App\Models\LogisticaEnvioHistorial;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Remision;
use App\Models\UsoCfdi;
use App\Services\FacturamaService;
use App\Services\PACServiceInterface;
use App\Services\PDFService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            ->when($search, function ($query) use ($search) {
                $query->buscar($search);
            })
            ->when($estado, function ($query) use ($estado) {
                $query->where('estado', $estado);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('facturas.index', compact('facturas', 'search', 'estado'));
    }

    /**
     * Listado de facturas para selección en modal (UUID sustituto en cancelación motivo 01,
     * o CFDI a sustituir al crear factura con relación): timbradas y canceladas solo en ERP (pendiente PAC).
     */
    public function listarParaRelacion(Request $request)
    {
        $query = Factura::with('cliente:id,nombre')
            ->whereNotNull('uuid')
            ->where(function ($q) {
                $q->where('estado', 'timbrada')
                    ->orWhere(function ($q2) {
                        $q2->where('estado', 'cancelada')
                            ->where('cancelacion_administrativa', true)
                            ->where('codigo_estatus_cancelacion', 'ADM');
                    });
            })
            ->orderBy('fecha_emision', 'desc')
            ->limit(200);

        if ($request->filled('excluir_id')) {
            $query->where('id', '!=', (int) $request->excluir_id);
        }
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', (int) $request->cliente_id);
        }
        if ($request->filled('keyword')) {
            $keyword = trim($request->keyword);
            $query->where(function ($q) use ($keyword) {
                $q->where('uuid', 'like', '%'.$keyword.'%')
                    ->orWhere('folio', 'like', '%'.$keyword.'%')
                    ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', '%'.$keyword.'%'));
            });
        }

        $facturas = $query->get(['id', 'uuid', 'serie', 'folio', 'cliente_id', 'fecha_emision', 'total']);

        return response()->json([
            'facturas' => $facturas->map(fn ($f) => [
                'id' => $f->id,
                'uuid' => $f->uuid,
                'serie' => $f->serie,
                'folio' => $f->folio,
                'cliente_nombre' => $f->cliente?->nombre,
                'fecha_emision' => $f->fecha_emision?->format('d/m/Y'),
                'total' => (float) $f->total,
            ]),
        ]);
    }

    /**
     * Formulario crear factura
     */
    public function create(Request $request)
    {
        $empresa = Empresa::principal();

        if (! $empresa) {
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

        $remisionId = null;
        $remisionLineasJson = null;
        $observacionesPre = null;
        $ordenCompraPreseleccionado = null;
        if ($request->filled('remision_id')) {
            $rem = Remision::with(['detalles.producto', 'cliente'])
                ->where('estado', 'entregada')
                ->with('factura')
                ->find($request->remision_id);
            if (! $rem) {
                return redirect()->route('remisiones.index')
                    ->with('error', 'La remisión no está disponible para facturar (debe estar entregada y sin factura activa).');
            }

            if ($rem->factura_id !== null) {
                $facturaActiva = $rem->factura;
                if ($facturaActiva && $facturaActiva->estado === 'borrador') {
                    return redirect()->route('facturas.edit', $facturaActiva->id)
                        ->with('info', 'Esta remisión ya tiene una factura en borrador. Continúe editando ese borrador.');
                }
                if (! $facturaActiva || $facturaActiva->estado !== 'cancelada') {
                    return redirect()->route('remisiones.index', ['estado' => 'entregada'])
                        ->with('error', 'La remisión no está disponible para facturar (la factura vinculada debe estar cancelada o inexistente).');
                }
            }

            foreach ($rem->detalles as $det) {
                if (! $det->producto_id || ! $det->producto) {
                    return redirect()->route('remisiones.show', $rem)
                        ->with('error', 'Para facturar desde remisión, todas las partidas deben tener un producto del catálogo asignado.');
                }
            }
            $remisionId = $rem->id;
            $clientePreseleccionado = $rem->cliente;
            $ordenCompraPreseleccionado = $rem->orden_compra;
            $observacionesPre = 'Documento de origen: Remisión #'.($rem->folio ?? $rem->id);
            $remisionLineasJson = $rem->detalles->map(function ($d) {
                $p = $d->producto;
                $tasa = $d->tasa_iva !== null
                    ? (float) $d->tasa_iva
                    : ((($p?->tipo_factor ?? 'Tasa') === 'Exento') ? 0.0 : (float) ($p?->tasa_iva ?? 0));

                return [
                    'producto_id' => (int) $d->producto_id,
                    'descripcion' => (string) $d->descripcion,
                    'cantidad' => (float) $d->cantidad,
                    'valor_unitario' => (float) ($d->precio_unitario ?? $p?->precio_venta ?? 0),
                    'tasa_iva' => $tasa,
                ];
            })->values()->all();
        }

        $folioContado = $empresa ? $empresa->obtenerSiguienteFolioFactura() : 'FA-0001';
        $folioCredito = $empresa ? $empresa->obtenerSiguienteFolioFacturaCredito() : 'FB-0001';

        return view('facturas.create', compact(
            'empresa',
            'clientes',
            'productos',
            'clientePreseleccionado',
            'formasPago',
            'metodosPago',
            'usosCfdi',
            'folioContado',
            'folioCredito',
            'remisionId',
            'remisionLineasJson',
            'observacionesPre',
            'ordenCompraPreseleccionado',
        ));
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
            // Puede contener uno o más UUID separados por coma (Relación CFDI tipo 04)
            // Puede contener uno o más UUID separados por coma (Relación CFDI tipo 04)
            'uuid_referencia' => 'nullable|string|max:500',
            'tipo_relacion' => 'nullable|string|in:01,02,03,04',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'nullable|exists:productos,id',
            'productos.*.descripcion' => 'required|string',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.valor_unitario' => 'required|numeric|min:0',
            'productos.*.descuento' => 'nullable|numeric|min:0',
            'remision_id' => 'nullable|exists:remisiones,id',
            'orden_compra' => 'nullable|string|max:200',
        ]);

        // Normalizar y limitar uuid_referencia para evitar overflow en DB
        if (! empty($validated['uuid_referencia'])) {
            $uuids = preg_split('/[,;\s]+/', $validated['uuid_referencia']) ?: [];
            $uuids = array_values(array_unique(array_filter(array_map('trim', $uuids))));
            $normalized = implode(', ', $uuids);
            $validated['uuid_referencia'] = Str::limit($normalized, 500, '');
        }

        $empresa = Empresa::principal();
        $cliente = Cliente::findOrFail($validated['cliente_id']);

        if (empty($empresa->codigo_postal) || strlen(preg_replace('/\D/', '', $empresa->codigo_postal)) < 5) {
            return back()->withInput()->with('error', 'La empresa debe tener un código postal de 5 dígitos (Configuración → Domicilio Fiscal) para emitir facturas.');
        }
        if (empty($cliente->codigo_postal) || strlen(preg_replace('/\D/', '', $cliente->codigo_postal)) < 5) {
            return back()->withInput()->with('error', 'El cliente debe tener un código postal de 5 dígitos para facturar. Edita el cliente y completa su domicilio fiscal.');
        }
        if (empty($cliente->regimen_fiscal) || ! preg_match('/^\d{3}$/', $cliente->regimen_fiscal)) {
            return back()->withInput()->with('error', 'El cliente debe tener un régimen fiscal (clave de 3 dígitos del SAT). Edita el cliente.');
        }

        DB::beginTransaction();
        try {
            $remisionVincular = null;
            if (! empty($validated['remision_id'])) {
                $remisionVincular = Remision::lockForUpdate()->find($validated['remision_id']);
                if (! $remisionVincular
                    || $remisionVincular->estado !== 'entregada'
                    || (int) $remisionVincular->cliente_id !== (int) $validated['cliente_id']) {
                    throw new \Exception('La remisión no es válida para vincular a esta factura.');
                }

                if ($remisionVincular->factura_id !== null) {
                    $facturaVinculada = Factura::find($remisionVincular->factura_id);
                    if (! $facturaVinculada || $facturaVinculada->estado !== 'cancelada') {
                        throw new \Exception('La remisión no es válida para facturar: la factura vinculada debe estar cancelada.');
                    }
                }
            }

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
                    $ivaTotal += round($baseImpuesto * (float) $producto->tasa_iva, 2);
                }
            }

            // Retención ISR: cuando emisor es PF RESICO y receptor es persona moral (SAT 2026)
            $retencionISR = 0.0;
            if (IsrResicoHelper::aplicaRetencionIsrPm($empresa, $cliente)) {
                $retencionISR = IsrResicoHelper::calcularRetencionIsrPm($subtotal, $descuentoTotal);
            }
            $total = $subtotal - $descuentoTotal + $ivaTotal - $retencionISR;

            // Serie y folio según método de pago: PPD = crédito (FB), PUE = contado (FA)
            $esCredito = ($validated['metodo_pago'] ?? '') === 'PPD';
            if ($esCredito) {
                $serie = $empresa->serie_factura_credito ?? 'FB';
                $folio = (int) ($empresa->folio_factura_credito ?? 1);
            } else {
                $serie = $empresa->serie_factura ?? 'FA';
                $folio = (int) ($empresa->folio_factura ?? 1);
            }

            // Crear factura
            $factura = Factura::create([
                'serie' => $serie,
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
                'uuid_referencia' => ! empty(trim($validated['uuid_referencia'] ?? '')) ? trim($validated['uuid_referencia']) : null,
                'tipo_relacion' => ! empty($validated['uuid_referencia']) ? ($validated['tipo_relacion'] ?? '04') : null,
                'usuario_id' => auth()->id(),
                'orden_compra' => $validated['orden_compra'] ?? $remisionVincular?->orden_compra ?? null,
            ]);

            // Base gravable total para prorratear retención ISR
            $baseGravableTotal = max(0.01, $subtotal - $descuentoTotal);

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
                    $tasa = (float) ($producto->tasa_iva ?? 0);
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

                // Retención ISR: emisor PF RESICO + receptor PM (SAT 2026, LISR Art. 152)
                if ($retencionISR > 0 && $baseImpuesto > 0) {
                    $retencionLinea = round($retencionISR * ($baseImpuesto / $baseGravableTotal), 2);
                    if ($retencionLinea > 0) {
                        FacturaImpuesto::create([
                            'factura_detalle_id' => $detalle->id,
                            'tipo' => 'retencion',
                            'impuesto' => '001',
                            'tipo_factor' => 'Tasa',
                            'tasa_o_cuota' => config('isr_resico.tasa_retencion_pm_a_resico', 0.0125),
                            'base' => $baseImpuesto,
                            'importe' => $retencionLinea,
                        ]);
                    }
                }
            }

            // Incrementar folio según tipo (contado o crédito)
            if ($esCredito) {
                $empresa->incrementarFolioFacturaCredito();
            } else {
                $empresa->incrementarFolioFactura();
            }

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

            if ($remisionVincular) {
                // Si existe una factura anterior cancelada, se conserva para trazabilidad.
                if ($remisionVincular->factura_id !== null) {
                    $facturaAnterior = Factura::find($remisionVincular->factura_id);
                    if ($facturaAnterior && $facturaAnterior->estado === 'cancelada') {
                        $update = ['factura_id' => $factura->id];

                        // Solo se preserva una factura cancelada en el campo adicional.
                        if ($remisionVincular->factura_id_cancelada === null) {
                            $update['factura_id_cancelada'] = $remisionVincular->factura_id;
                        }

                        $remisionVincular->update($update);
                    } else {
                        // Por seguridad: el flujo debería impedir llegar aquí.
                        $remisionVincular->update(['factura_id' => $factura->id]);
                    }
                } else {
                    $remisionVincular->update(['factura_id' => $factura->id]);
                }
            }

            DB::commit();

            return redirect()->route('facturas.show', $factura->id)
                ->with('success', 'Factura creada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()
                ->with('error', 'Error al crear factura: '.$e->getMessage());
        }
    }

    /**
     * Ver detalle de factura
     */
    public function show(Factura $factura)
    {
        $factura->load(['cliente', 'detalles.producto', 'detalles.impuestos', 'cuentaPorCobrar', 'usuario', 'cancelacionAdministrativaUsuario']);
        $ncBorrador = \App\Models\NotaCredito::where('factura_id', $factura->id)->where('estado', 'borrador')->first();
        $complementoBorrador = $factura->cliente_id ? \App\Models\ComplementoPago::where('cliente_id', $factura->cliente_id)->where('estado', 'borrador')->first() : null;

        $remisionIds = Remision::query()->where('factura_id', $factura->id)->pluck('id');
        $envioIds = LogisticaEnvio::query()
            ->where(function ($q) use ($factura, $remisionIds) {
                $q->where('factura_id', $factura->id);
                if ($remisionIds->isNotEmpty()) {
                    $q->orWhereIn('remision_id', $remisionIds);
                }
            })
            ->pluck('id');
        $historialEnviosFactura = $envioIds->isEmpty()
            ? collect()
            : LogisticaEnvioHistorial::query()
                ->whereIn('logistica_envio_id', $envioIds)
                ->with(['envio:id,folio', 'user:id,name'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();

        return view('facturas.show', compact('factura', 'ncBorrador', 'complementoBorrador', 'historialEnviosFactura'));
    }

    /**
     * Formulario editar factura (solo borrador)
     */
    public function edit(Factura $factura)
    {
        if (! $factura->esBorrador()) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'Solo se pueden editar facturas en borrador.');
        }

        $factura->load(['cliente', 'detalles.producto', 'detalles.impuestos']);
        $empresa = Empresa::principal();
        $clientes = Cliente::activos()->orderBy('nombre')->get();
        $productos = Producto::activos()->with('categoria')->orderBy('nombre')->get();
        $formasPago = FormaPago::activos()->get();
        $metodosPago = MetodoPago::activos()->get();
        $usosCfdi = UsoCfdi::activos()->get();

        return view('facturas.edit', compact('factura', 'empresa', 'clientes', 'productos', 'formasPago', 'metodosPago', 'usosCfdi'));
    }

    /**
     * Borrar factura (solo borrador).
     */
    public function destroy(Factura $factura)
    {
        if (! $factura->esBorrador()) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'Solo se pueden borrar facturas en borrador.');
        }

        DB::beginTransaction();
        try {
            Remision::where(function ($q) use ($factura) {
                $q->where('factura_id', $factura->id)
                    ->orWhere('factura_id_cancelada', $factura->id);
            })->update([
                'factura_id' => null,
                'factura_id_cancelada' => null,
            ]);
            if ($factura->cuentaPorCobrar) {
                $factura->cuentaPorCobrar->delete();
            }
            foreach ($factura->detalles as $detalle) {
                $detalle->impuestos()->delete();
            }
            $factura->detalles()->delete();
            $factura->forceDelete();

            DB::commit();

            return redirect()->route('facturas.index')
                ->with('success', 'Factura en borrador eliminada correctamente.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->route('facturas.show', $factura)
                ->with('error', 'No se pudo eliminar la factura: '.$e->getMessage());
        }
    }

    /**
     * Actualizar factura (solo borrador)
     */
    public function update(Request $request, Factura $factura)
    {
        if (! $factura->esBorrador()) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'Solo se pueden editar facturas en borrador.');
        }

        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'fecha_emision' => 'required|date',
            'forma_pago' => 'required|string|exists:formas_pago,clave',
            'metodo_pago' => 'required|string|exists:metodos_pago,clave',
            'uso_cfdi' => 'required|string|exists:usos_cfdi,clave',
            'observaciones' => 'nullable|string',
            'orden_compra' => 'nullable|string|max:200',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'nullable|exists:productos,id',
            'productos.*.descripcion' => 'required|string',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.valor_unitario' => 'required|numeric|min:0',
            'productos.*.descuento' => 'nullable|numeric|min:0',
        ]);

        $empresa = Empresa::principal();
        $cliente = Cliente::findOrFail($validated['cliente_id']);

        if (empty($empresa->codigo_postal) || strlen(preg_replace('/\D/', '', $empresa->codigo_postal)) < 5) {
            return back()->withInput()->with('error', 'La empresa debe tener un código postal de 5 dígitos.');
        }
        if (empty($cliente->codigo_postal) || strlen(preg_replace('/\D/', '', $cliente->codigo_postal)) < 5) {
            return back()->withInput()->with('error', 'El cliente debe tener un código postal de 5 dígitos.');
        }
        if (empty($cliente->regimen_fiscal) || ! preg_match('/^\d{3}$/', $cliente->regimen_fiscal)) {
            return back()->withInput()->with('error', 'El cliente debe tener un régimen fiscal.');
        }

        DB::beginTransaction();
        try {
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
                    $ivaTotal += round($baseImpuesto * (float) $producto->tasa_iva, 2);
                }
            }

            // Retención ISR: cuando emisor es PF RESICO y receptor es persona moral (SAT 2026)
            $retencionISR = 0.0;
            if (IsrResicoHelper::aplicaRetencionIsrPm($empresa, $cliente)) {
                $retencionISR = IsrResicoHelper::calcularRetencionIsrPm($subtotal, $descuentoTotal);
            }
            $total = $subtotal - $descuentoTotal + $ivaTotal - $retencionISR;

            // Base gravable total para prorratear retención ISR
            $baseGravableTotal = max(0.01, $subtotal - $descuentoTotal);

            // Actualizar factura
            $factura->update([
                'cliente_id' => $cliente->id,
                'rfc_receptor' => $cliente->rfc,
                'nombre_receptor' => $cliente->nombre,
                'regimen_fiscal_receptor' => $cliente->regimen_fiscal,
                'domicilio_fiscal_receptor' => $cliente->codigo_postal,
                'uso_cfdi' => $validated['uso_cfdi'],
                'forma_pago' => $validated['forma_pago'],
                'metodo_pago' => $validated['metodo_pago'],
                'fecha_emision' => $validated['fecha_emision'],
                'subtotal' => $subtotal,
                'descuento' => $descuentoTotal,
                'total' => $total,
                'observaciones' => $validated['observaciones'],
                'orden_compra' => $validated['orden_compra'] ?? $factura->orden_compra,
            ]);

            // Eliminar detalles e impuestos anteriores
            foreach ($factura->detalles as $d) {
                $d->impuestos()->delete();
            }
            $factura->detalles()->delete();

            // Crear nuevos detalles e impuestos
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

                if ($producto && in_array($objetoImpuesto, ['02', '03'], true)) {
                    $tipoFactor = $producto->tipo_factor ?? 'Tasa';
                    $tasa = (float) ($producto->tasa_iva ?? 0);
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

                // Retención ISR: emisor PF RESICO + receptor PM (SAT 2026)
                if ($retencionISR > 0 && $baseImpuesto > 0) {
                    $retencionLinea = round($retencionISR * ($baseImpuesto / $baseGravableTotal), 2);
                    if ($retencionLinea > 0) {
                        FacturaImpuesto::create([
                            'factura_detalle_id' => $detalle->id,
                            'tipo' => 'retencion',
                            'impuesto' => '001',
                            'tipo_factor' => 'Tasa',
                            'tasa_o_cuota' => config('isr_resico.tasa_retencion_pm_a_resico', 0.0125),
                            'base' => $baseImpuesto,
                            'importe' => $retencionLinea,
                        ]);
                    }
                }
            }

            // Cuenta por cobrar: actualizar o crear/eliminar según método de pago
            $cuentaExistente = $factura->cuentaPorCobrar;
            $nuevoEsCredito = ($validated['metodo_pago'] ?? '') === 'PPD';

            if ($cuentaExistente && ! $nuevoEsCredito) {
                $cuentaExistente->delete();
                $cliente->actualizarSaldo();
            } elseif (! $cuentaExistente && $nuevoEsCredito) {
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
                $cliente->actualizarSaldo();
            } elseif ($cuentaExistente && $nuevoEsCredito) {
                $cuentaExistente->update([
                    'monto_total' => $total,
                    'monto_pendiente' => $total,
                    'fecha_emision' => $validated['fecha_emision'],
                    'fecha_vencimiento' => now()->addDays($cliente->dias_credito),
                ]);
                $cliente->actualizarSaldo();
            }

            DB::commit();

            return redirect()->route('facturas.show', $factura)
                ->with('success', 'Factura actualizada correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Error al actualizar: '.$e->getMessage());
        }
    }

    /**
     * Timbrar factura
     */
    public function timbrar(Factura $factura)
    {
        if (! $factura->puedeTimbrar()) {
            return back()->with('error', 'Esta factura no puede ser timbrada');
        }

        DB::beginTransaction();
        try {
            // Llamar al servicio de timbrado
            $resultado = $this->pacService->timbrarFactura($factura);

            if (! $resultado['success']) {
                throw new \Exception($resultado['message']);
            }

            // Actualizar factura con datos del timbrado
            $factura->update([
                'estado' => 'timbrada',
                'uuid' => $resultado['uuid'],
                'pac_cfdi_id' => $resultado['pac_cfdi_id'] ?? null,
                'fecha_timbrado' => $resultado['fecha_timbrado'] ?? now(),
                'no_certificado_sat' => $resultado['no_certificado_sat'] ?? null,
                'sello_cfdi' => $resultado['sello_cfdi'] ?? null,
                'sello_sat' => $resultado['sello_sat'] ?? null,
                'cadena_original' => $resultado['cadena_original'] ?? null,
                'xml_content' => $resultado['xml'] ?? null,
            ]);

            $factura->load(['detalles.producto', 'remisionVinculada']);
            foreach ($factura->detalles as $detalle) {
                $detalle->aplicarSnapshotCostoAlTimbrado();
            }

            // Descontar inventario al timbrar (no en borrador), salvo si la mercancía ya salió por remisión
            if (! $factura->inventarioDescontadoEnRemision()) {
                foreach ($factura->detalles as $detalle) {
                    $producto = $detalle->producto;
                    if ($producto && $producto->controla_inventario) {
                        InventarioMovimiento::registrar(
                            $producto,
                            InventarioMovimiento::TIPO_SALIDA_FACTURA,
                            (float) $detalle->cantidad,
                            auth()->id(),
                            $factura->id,
                            null,
                            null,
                            null
                        );
                    }
                }
            }

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
                ->with('success', $resultado['message'].' - PDF generado automáticamente');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Error al timbrar: '.$e->getMessage());
        }
    }

    /**
     * Cancelar factura
     */
    public function cancelar(Request $request, Factura $factura)
    {
        if (! $factura->puedeCancelar()) {
            $msg = $factura->tieneDocumentosRelacionados()
                ? 'No se puede cancelar: esta factura tiene documentos relacionados (complementos de pago, notas de crédito o devoluciones). Use el flujo castada.'
                : 'Esta factura no puede ser cancelada';

            return back()->with('error', $msg);
        }

        $rules = [
            'motivo_cancelacion' => 'required|string|in:01,02,03,04',
        ];
        if ($request->input('motivo_cancelacion') === '01') {
            $rules['uuid_sustituto'] = 'required|string|size:36';
        }
        $validated = $request->validate($rules);

        $uuidSustituto = ($validated['motivo_cancelacion'] ?? '') === '01'
            ? trim($validated['uuid_sustituto'] ?? '')
            : null;

        DB::beginTransaction();
        try {
            // Llamar al servicio de cancelación (motivo 01 exige UUID del CFDI que sustituye - SAT/Facturama)
            $resultado = $this->pacService->cancelarFactura(
                $factura->uuid,
                $validated['motivo_cancelacion'],
                $uuidSustituto
            );

            if (! $resultado['success']) {
                throw new \Exception($resultado['message']);
            }

            $fueCancelacionAdministrativaPrev = (bool) $factura->cancelacion_administrativa;

            // Actualizar factura (incluye código SAT del acuse). No se toca timbrado ni XML originales.
            $updates = [
                'estado' => 'cancelada',
                'motivo_cancelacion' => $validated['motivo_cancelacion'],
                'acuse_cancelacion' => $resultado['acuse'] ?? null,
                'codigo_estatus_cancelacion' => $resultado['codigo_estatus'] ?? '201',
            ];
            if (! $fueCancelacionAdministrativaPrev) {
                $updates['fecha_cancelacion'] = now();
            } else {
                $updates['fecha_cancelacion_pac'] = now();
            }
            $factura->update($updates);

            // Regenerar PDF para que muestre "CANCELADA"
            $pdfPath = $this->pdfService->generarFacturaPDF($factura);
            $factura->update(['pdf_path' => $pdfPath]);

            // Inventario: si ya se revirtió en cancelación administrativa, no duplicar movimientos.
            if (! $fueCancelacionAdministrativaPrev) {
                $factura->load('remisionVinculada');
                if (! $factura->inventarioDescontadoEnRemision()) {
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
                                null,
                                'Factura cancelada'
                            );
                        }
                    }
                }
            }

            // Si tiene cuenta por cobrar, cancelarla (coherencia finanzas)
            if ($factura->cuentaPorCobrar) {
                $factura->cuentaPorCobrar->update([
                    'estado' => 'cancelada',
                    'monto_pendiente' => 0,
                ]);
                $factura->cliente->actualizarSaldo();
            }

            DB::commit();

            $mensajeExito = $fueCancelacionAdministrativaPrev
                ? 'CFDI cancelado ante el PAC/SAT correctamente (la factura ya estaba cancelada en el ERP).'
                : 'Factura cancelada exitosamente';

            return redirect()->route('facturas.show', $factura->id)
                ->with('success', $mensajeExito);

        } catch (\Exception $e) {
            DB::rollBack();
            $msg = $e->getMessage();
            $codigo = $this->extraerCodigoErrorCancelacion($msg);
            $factura->refresh();
            // No sobrescribir ADM si sigue pendiente la cancelación fiscal tras error del PAC.
            if (! $factura->pendienteCancelacionAntePac()) {
                $factura->update(['codigo_estatus_cancelacion' => $codigo]);
            }

            return back()->with('error', 'Error al cancelar: '.$msg);
        }
    }

    /**
     * Extrae código SAT o etiqueta de error de cancelación.
     */
    protected function extraerCodigoErrorCancelacion(string $mensaje): string
    {
        if (preg_match('/\b(201|202|203|204|205|206|301|302|401|601)\b/', $mensaje, $m)) {
            return 'R-'.$m[1];
        }
        if (stripos($mensaje, 'ya fue cancelad') !== false || stripos($mensaje, 'already cancelled') !== false) {
            return 'R-202';
        }
        if (stripos($mensaje, 'no se encontró') !== false || stripos($mensaje, 'not found') !== false) {
            return 'R-205';
        }
        if (stripos($mensaje, 'documentos relacionados') !== false || stripos($mensaje, 'no cancelable') !== false) {
            return 'R-601';
        }

        return 'R';
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
            return back()->with('error', 'Error al generar PDF: '.$e->getMessage());
        }
    }

    /**
     * Descargar XML
     */
    public function descargarXML(Factura $factura)
    {
        if (! $factura->xml_path && empty($factura->xml_content)) {
            return back()->with('error', 'XML no disponible');
        }

        $filepath = storage_path('app/'.$factura->xml_path);
        if ($factura->xml_path && file_exists($filepath)) {
            return response()->download($filepath, $factura->folio_completo.'.xml');
        }
        $filepathPrivate = storage_path('app/private/'.$factura->xml_path);
        if ($factura->xml_path && file_exists($filepathPrivate)) {
            return response()->download($filepathPrivate, $factura->folio_completo.'.xml');
        }
        if (! empty($factura->xml_content)) {
            return response($factura->xml_content, 200, [
                'Content-Type' => 'application/xml',
                'Content-Disposition' => 'attachment; filename="'.$factura->folio_completo.'.xml"',
            ]);
        }

        return back()->with('error', 'Archivo XML no encontrado');
    }

    /**
     * Obtener y guardar el acuse de cancelación desde Facturama (para facturas ya canceladas sin acuse).
     * Útil cuando la cancelación en producción no devolvió el XML en la respuesta.
     */
    public function obtenerAcuseCancelacion(Factura $factura)
    {
        if ($factura->estado !== 'cancelada') {
            return back()->with('error', 'Solo aplica a facturas canceladas.');
        }
        if (! empty($factura->acuse_cancelacion)) {
            return back()->with('info', 'La factura ya tiene el acuse de cancelación guardado.');
        }
        $empresa = $factura->empresa ?? Empresa::principal();
        if (! $empresa) {
            return back()->with('error', 'No hay empresa configurada.');
        }
        try {
            $facturama = new FacturamaService($empresa);
            $acuse = $facturama->obtenerAcuseCancelacionPorFactura($factura);
            if (empty($acuse)) {
                return back()->with('error', 'No se pudo obtener el acuse de cancelación desde Facturama. Verifica que el CFDI esté cancelado en el PAC.');
            }
            $factura->update(['acuse_cancelacion' => $acuse]);

            return back()->with('success', 'Acuse de cancelación guardado. Ya puedes descargar el XML cancelado.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al obtener acuse: '.$e->getMessage());
        }
    }

    /**
     * Actualizar estatus de cancelación desde el SAT/PAC (solo facturas canceladas).
     * Obtiene el acuse actualizado y el código de estatus para reflejar la respuesta final del SAT.
     */
    public function actualizarEstatusCancelacion(Factura $factura)
    {
        if ($factura->estado !== 'cancelada') {
            return back()->with('error', 'Solo se puede actualizar el estatus de facturas canceladas.');
        }
        $empresa = $factura->empresa ?? Empresa::principal();
        if (! $empresa) {
            return back()->with('error', 'No hay empresa configurada.');
        }
        try {
            $facturama = new FacturamaService($empresa);
            $acuse = $facturama->obtenerAcuseCancelacionPorFactura($factura);
            if (empty($acuse)) {
                return back()->with('error', 'No se pudo obtener la respuesta del SAT. Intente más tarde o verifique la factura en Facturama.');
            }
            $codigoEstatus = FacturamaService::extraerCodigoEstatusDelAcuse($acuse);
            $factura->update([
                'acuse_cancelacion' => $acuse,
                'codigo_estatus_cancelacion' => $codigoEstatus,
            ]);

            return back()->with('success', 'Estatus actualizado: '.Factura::descripcionCodigoCancelacion($codigoEstatus).' (código '.$codigoEstatus.').');
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al actualizar estatus: '.$e->getMessage());
        }
    }

    /**
     * Descargar XML del acuse de cancelación (SAT / Facturama).
     * Solo disponible cuando la factura está cancelada y se guardó el acuse.
     *
     * @see https://apisandbox.facturama.mx/Docs - Obtener acuse de cancelación
     */
    public function descargarXmlCancelacion(Factura $factura)
    {
        if ($factura->estado !== 'cancelada') {
            return back()->with('error', 'Solo se puede descargar el XML de cancelación de facturas canceladas.');
        }

        $acuseBase64 = $factura->acuse_cancelacion;
        if (empty($acuseBase64)) {
            return back()->with('error', 'No se tiene guardado el acuse de cancelación para esta factura.');
        }

        $xml = base64_decode($acuseBase64, true);
        if ($xml === false || trim($xml) === '') {
            return back()->with('error', 'El acuse de cancelación guardado no es válido.');
        }

        $filename = 'AcuseCancelacion_'.($factura->uuid ?? $factura->folio_completo).'.xml';
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Ver PDF en el navegador (como en cotizaciones).
     * En borrador siempre se regenera el PDF para reflejar las últimas ediciones.
     */
    public function verPDF(Factura $factura)
    {
        $regenerar = $factura->esBorrador()
            || ! $factura->pdf_path
            || ! file_exists(storage_path('app/'.$factura->pdf_path));

        if ($regenerar) {
            try {
                $pdfPath = $this->pdfService->generarFacturaPDF($factura);
                $factura->update(['pdf_path' => $pdfPath]);
            } catch (\Exception $e) {
                return back()->with('error', 'Error al generar PDF: '.$e->getMessage());
            }
        }

        return response()->file(storage_path('app/'.$factura->pdf_path));
    }

    /**
     * Descargar PDF
     */
    public function descargarPDF(Factura $factura)
    {
        if (! $factura->pdf_path) {
            return back()->with('error', 'PDF no disponible');
        }

        return $this->pdfService->descargarPDF($factura->pdf_path, $factura->folio_completo.'.pdf');
    }

    /**
     * Guardar XML
     */
    protected function guardarXML(Factura $factura, string $xml): string
    {
        $directory = storage_path('app/facturas/'.now()->format('Y/m'));

        if (! file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = $factura->folio_completo.'.xml';
        $filepath = $directory.'/'.$filename;

        file_put_contents($filepath, $xml);

        return 'facturas/'.now()->format('Y/m').'/'.$filename;
    }
}
