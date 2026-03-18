<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/ComplementoPagoController.php
// REEMPLAZA el contenido actual con este

use App\Http\Controllers\Controller;
use App\Models\ComplementoPago;
use App\Models\PagoRecibido;
use App\Models\DocumentoRelacionadoPago;
use App\Models\CuentaPorCobrar;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\FormaPago;
use App\Models\NotaCredito;
use App\Services\FacturamaService;
use App\Services\PACServiceInterface;
use App\Services\PDFService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplementoPagoController extends Controller
{
    public function __construct(
        protected PACServiceInterface $pacService,
        protected PDFService $pdfService
    ) {}

    /**
     * Listado de complementos
     */
    public function index(Request $request)
    {
        $estado = $request->get('estado');
        $cliente_id = $request->get('cliente_id');

        $complementos = ComplementoPago::with(['cliente', 'usuario'])
            ->when($estado, function($query) use ($estado) {
                $query->where('estado', $estado);
            })
            ->when($cliente_id, function($query) use ($cliente_id) {
                $query->where('cliente_id', $cliente_id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $clientes = Cliente::activos()->orderBy('nombre')->get();

        return view('complementos.index', compact('complementos', 'clientes', 'estado', 'cliente_id'));
    }

    /**
     * Formulario crear complemento
     */
    public function create(Request $request)
    {
        $empresa = Empresa::principal();
        
        if (!$empresa) {
            return redirect()->route('dashboard')
                ->with('error', 'Debes configurar los datos de la empresa primero');
        }

        $clientes = Cliente::activos()->orderBy('nombre')->get();
        $formasPago = FormaPago::activos()->get();

        // Si viene de una cuenta por cobrar específica
        $cuentaPreseleccionada = null;
        if ($request->has('cuenta_id')) {
            $cuentaPreseleccionada = CuentaPorCobrar::with('factura')->find($request->cuenta_id);
        }

        // Si el cliente ya tiene complemento en borrador, redirigir al show (evitar duplicados)
        $clienteId = $cuentaPreseleccionada?->cliente_id ?? $request->get('cliente_id');
        if ($clienteId) {
            $complementoBorrador = ComplementoPago::where('cliente_id', $clienteId)->where('estado', 'borrador')->first();
            if ($complementoBorrador) {
                return redirect()->route('complementos.show', $complementoBorrador->id)
                    ->with('info', 'Este cliente ya tiene un complemento de pago en borrador. Complétalo, edítalo o elimínalo antes de crear otro.');
            }
        }

        return view('complementos.create', compact('empresa', 'clientes', 'cuentaPreseleccionada', 'formasPago'));
    }

    /**
     * Guardar complemento
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'fecha_pago' => 'required|date',
            'forma_pago' => 'required|string|exists:formas_pago,clave',
            'monto_total' => 'required|numeric|min:0.01',
            'moneda' => 'required|string|max:3',
            'num_operacion' => 'nullable|string|max:100',
            'facturas' => 'required|array|min:1',
            'facturas.*.factura_id' => 'required|exists:facturas,id',
            'facturas.*.monto_pagado' => 'required|numeric|min:0',
            'uuid_referencia' => 'nullable|string|size:36',
            'tipo_relacion' => 'nullable|string|in:01,02,03,04',
        ], [
            'facturas.required' => 'Debes aplicar el pago al menos a una factura.',
            'facturas.min' => 'Debes aplicar el pago al menos a una factura.',
        ]);

        $montoTotal = (float) $validated['monto_total'];
        $sumaAplicada = collect($validated['facturas'])->sum(fn ($f) => (float) ($f['monto_pagado'] ?? 0));
        if (abs($sumaAplicada - $montoTotal) >= 0.01) {
            return back()->withInput()->withErrors([
                'monto_total' => 'La suma de montos aplicados a las facturas (' . number_format($sumaAplicada, 2) . ') debe coincidir con el monto total del pago (' . number_format($montoTotal, 2) . ').',
            ]);
        }
        $conMonto = array_filter($validated['facturas'], fn ($f) => ((float) ($f['monto_pagado'] ?? 0)) >= 0.01);
        if (empty($conMonto)) {
            return back()->withInput()->withErrors([
                'facturas' => ['Indica al menos un monto mayor a 0 en alguna factura.'],
            ]);
        }

        // No permitir crear otro complemento si el cliente ya tiene uno en borrador
        $complementoBorradorExistente = ComplementoPago::where('cliente_id', $validated['cliente_id'])->where('estado', 'borrador')->first();
        if ($complementoBorradorExistente) {
            return redirect()->route('complementos.show', $complementoBorradorExistente->id)
                ->with('error', 'Este cliente ya tiene un complemento de pago en borrador. No se puede crear otro hasta completarlo o eliminarlo.');
        }

        // No permitir registrar pago por montos ya cubiertos por Notas de Crédito (coherencia SAT)
        foreach ($validated['facturas'] as $facturaData) {
            $montoPagado = (float) ($facturaData['monto_pagado'] ?? 0);
            if ($montoPagado < 0.01) {
                continue;
            }
            $facturaId = (int) $facturaData['factura_id'];
            $cuenta = CuentaPorCobrar::where('factura_id', $facturaId)->first();
            if (!$cuenta) {
                continue;
            }
            $montoCubiertoPorNC = (float) NotaCredito::where('factura_id', $facturaId)->where('estado', 'timbrada')->sum('total');
            $maxPermitido = max(0, (float) $cuenta->monto_pendiente - $montoCubiertoPorNC);
            if ($montoPagado > $maxPermitido + 0.01) {
                $factura = $cuenta->factura;
                $folio = $factura ? $factura->folio_completo : $facturaId;
                return back()->withInput()->withErrors([
                    'facturas' => ["No se puede registrar un Complemento de Pago por un monto mayor al disponible en la factura {$folio}. El monto ya cubierto por Notas de Crédito no puede pagarse con un complemento (máximo permitido: " . number_format($maxPermitido, 2) . ")."],
                ]);
            }
        }

        DB::beginTransaction();
        try {
            $empresa = Empresa::principal();
            $cliente = Cliente::findOrFail($validated['cliente_id']);

            // Obtener siguiente folio
            $folio = $empresa->folio_complemento ?? 1;

            // Relación de CFDI (SAT 2026): sustitución de complemento emitido con errores
            $uuidReferencia = !empty(trim($validated['uuid_referencia'] ?? '')) ? trim($validated['uuid_referencia']) : null;
            $tipoRelacion = $uuidReferencia ? ($validated['tipo_relacion'] ?? '04') : null;

            // Crear complemento CON TODOS LOS CAMPOS REQUERIDOS
            $complemento = ComplementoPago::create([
                // Identificación
                'serie' => $empresa->serie_complemento ?? 'P',
                'folio' => $folio,
                'estado' => 'borrador',
                
                // Relaciones
                'cliente_id' => $cliente->id,
                'empresa_id' => $empresa->id,
                
                // Datos del emisor (OBLIGATORIOS - AGREGADOS)
                'rfc_emisor' => $empresa->rfc,
                'nombre_emisor' => $empresa->razon_social,
                
                // Datos del receptor
                'rfc_receptor' => $cliente->rfc,
                'nombre_receptor' => $cliente->nombre,
                
                // Datos fiscales (OBLIGATORIOS - AGREGADOS)
                'fecha_emision' => now(),
                'lugar_expedicion' => $empresa->codigo_postal,
                'monto_total' => $validated['monto_total'],
                'uuid_referencia' => $uuidReferencia,
                'tipo_relacion' => $tipoRelacion,
                
                // Control
                'usuario_id' => auth()->id(),
            ]);

            // Fecha de pago: mismo día → hora actual; días anteriores → 12:00:00
            $fechaPago = \Carbon\Carbon::parse($validated['fecha_pago'])->startOfDay();
            $fechaPago = $fechaPago->isToday() ? now() : $fechaPago->setTime(12, 0, 0);

            // Crear pago recibido
            $pago = PagoRecibido::create([
                'complemento_pago_id' => $complemento->id,
                'fecha_pago' => $fechaPago,
                'forma_pago' => $validated['forma_pago'],
                'moneda' => $validated['moneda'],
                'monto' => $validated['monto_total'],
                'num_operacion' => $validated['num_operacion'] ?? null,
                // Campos opcionales de banco
                'rfc_banco_ordenante' => $request->input('rfc_banco_ordenante'),
                'cuenta_ordenante' => $request->input('cuenta_ordenante'),
                'rfc_banco_beneficiario' => $request->input('rfc_banco_beneficiario'),
                'cuenta_beneficiario' => $request->input('cuenta_beneficiario'),
            ]);

            // Crear documentos relacionados (solo facturas con monto aplicado > 0)
            foreach ($validated['facturas'] as $index => $facturaData) {
                $montoPagado = (float) ($facturaData['monto_pagado'] ?? 0);
                if ($montoPagado < 0.01) {
                    continue;
                }

                $factura = \App\Models\Factura::findOrFail($facturaData['factura_id']);
                $cuentaPorCobrar = $factura->cuentaPorCobrar;

                if (!$cuentaPorCobrar) {
                    throw new \Exception("La factura {$factura->folio_completo} no tiene cuenta por cobrar asociada");
                }

                $uuidFactura = trim((string) ($factura->uuid ?? ''));
                if ($uuidFactura === '' || !preg_match('/^[a-f0-9\-]{36}$/i', $uuidFactura)) {
                    throw new \Exception("La factura {$factura->folio_completo} no tiene UUID válido (debe estar timbrada). Solo se pueden incluir facturas timbradas en complementos de pago.");
                }

                // Calcular parcialidad
                $parcialidad = DocumentoRelacionadoPago::where('factura_uuid', $factura->uuid)->count() + 1;
                
                // Calcular saldo anterior (saldo_pendiente_real considera notas de crédito)
                $saldoAnterior = (float) $cuentaPorCobrar->saldo_pendiente_real;
                $saldoInsoluto = $saldoAnterior - $montoPagado;

                DocumentoRelacionadoPago::create([
                    'pago_recibido_id' => $pago->id,
                    'factura_id' => $factura->id,
                    'factura_uuid' => $factura->uuid,
                    'serie' => $factura->serie,
                    'folio' => $factura->folio,
                    'moneda' => $factura->moneda,
                    'monto_total' => $factura->total,
                    'parcialidad' => $parcialidad,
                    'saldo_anterior' => $saldoAnterior,
                    'monto_pagado' => $montoPagado,
                    'saldo_insoluto' => $saldoInsoluto,
                ]);

                // El pago se aplica a la cuenta por cobrar solo al TIMBRAR el complemento (flujo fiscal correcto)
            }

            // Incrementar folio del complemento
            if (!isset($empresa->folio_complemento)) {
                $empresa->folio_complemento = 2;
            } else {
                $empresa->folio_complemento++;
            }
            $empresa->save();

            DB::commit();

            return redirect()->route('complementos.show', $complemento->id)
                ->with('success', 'Complemento de pago creado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Error al crear complemento: ' . $e->getMessage());
        }
    }

    /**
     * Ver detalle de complemento
     */
    public function show(ComplementoPago $complemento)
    {
        $complemento->load([
            'cliente',
            'pagosRecibidos.documentosRelacionados.factura.cuentaPorCobrar',
            'usuario'
        ]);

        return view('complementos.show', compact('complemento'));
    }

    /**
     * Editar complemento (solo borrador)
     */
    public function edit(ComplementoPago $complemento)
    {
        if ($complemento->estado !== 'borrador') {
            return redirect()->route('complementos.show', $complemento->id)
                ->with('error', 'Solo se pueden editar complementos en borrador.');
        }
        $complemento->load(['pagosRecibidos.documentosRelacionados.factura.cuentaPorCobrar', 'cliente']);
        $pago = $complemento->pagosRecibidos->first();
        if (!$pago) {
            return redirect()->route('complementos.show', $complemento->id)
                ->with('error', 'El complemento no tiene pago asociado.');
        }

        $cuentas = CuentaPorCobrar::with('factura')
            ->excluirFacturaBorrador()
            ->where('cliente_id', $complemento->cliente_id)
            ->whereIn('estado', ['pendiente', 'parcial', 'vencida'])
            ->where('monto_pendiente', '>', 0)
            ->whereHas('factura', fn ($q) => $q->whereNotNull('uuid'))
            ->get();

        $montosPorFactura = $pago->documentosRelacionados->pluck('monto_pagado', 'factura_id')->map(fn ($v) => (float) $v);

        $facturasDisponibles = $cuentas->map(function ($cuenta) use ($montosPorFactura) {
            $montoCubiertoPorNC = (float) NotaCredito::where('factura_id', $cuenta->factura_id)->where('estado', 'timbrada')->sum('total');
            $pendiente = max(0, (float) $cuenta->monto_pendiente - $montoCubiertoPorNC);
            return [
                'id' => $cuenta->factura_id,
                'folio' => $cuenta->factura->folio_completo,
                'uuid' => $cuenta->factura->uuid,
                'fecha' => $cuenta->fecha_emision->format('d/m/Y'),
                'total' => $cuenta->monto_total,
                'pendiente' => round($pendiente, 2),
                'monto_pagado' => round($montosPorFactura->get($cuenta->factura_id, 0), 2),
            ];
        })->filter(fn ($f) => $f['pendiente'] > 0 || $f['monto_pagado'] > 0)->values();

        $empresa = Empresa::principal();
        $formasPago = FormaPago::activos()->get();
        return view('complementos.edit', compact('complemento', 'pago', 'facturasDisponibles', 'empresa', 'formasPago'));
    }

    /**
     * Actualizar complemento (solo borrador)
     */
    public function update(Request $request, ComplementoPago $complemento)
    {
        if ($complemento->estado !== 'borrador') {
            return redirect()->route('complementos.show', $complemento->id)
                ->with('error', 'Solo se pueden editar complementos en borrador.');
        }

        $validated = $request->validate([
            'fecha_pago' => 'required|date',
            'forma_pago' => 'required|string|exists:formas_pago,clave',
            'monto_total' => 'required|numeric|min:0.01',
            'moneda' => 'required|string|max:3',
            'num_operacion' => 'nullable|string|max:100',
            'facturas' => 'required|array|min:1',
            'facturas.*.factura_id' => 'required|exists:facturas,id',
            'facturas.*.monto_pagado' => 'required|numeric|min:0',
        ]);

        $montoTotal = (float) $validated['monto_total'];
        $sumaAplicada = collect($validated['facturas'])->sum(fn ($f) => (float) ($f['monto_pagado'] ?? 0));
        if (abs($sumaAplicada - $montoTotal) >= 0.01) {
            return back()->withInput()->withErrors([
                'monto_total' => 'La suma de montos aplicados debe coincidir con el monto total del pago.',
            ]);
        }
        $conMonto = array_filter($validated['facturas'], fn ($f) => ((float) ($f['monto_pagado'] ?? 0)) >= 0.01);
        if (empty($conMonto)) {
            return back()->withInput()->withErrors(['facturas' => ['Indica al menos un monto mayor a 0.']]);
        }

        foreach ($validated['facturas'] as $facturaData) {
            $montoPagado = (float) ($facturaData['monto_pagado'] ?? 0);
            if ($montoPagado < 0.01) continue;
            $facturaId = (int) $facturaData['factura_id'];
            $cuenta = CuentaPorCobrar::where('factura_id', $facturaId)->first();
            if (!$cuenta) continue;
            $montoCubiertoPorNC = (float) NotaCredito::where('factura_id', $facturaId)->where('estado', 'timbrada')->sum('total');
            $maxPermitido = max(0, (float) $cuenta->monto_pendiente - $montoCubiertoPorNC);
            if ($montoPagado > $maxPermitido + 0.01) {
                $factura = $cuenta->factura;
                return back()->withInput()->withErrors([
                    'facturas' => ["Monto mayor al disponible en factura " . ($factura->folio_completo ?? $facturaId) . ". Máximo: " . number_format($maxPermitido, 2) . "."],
                ]);
            }
        }

        DB::beginTransaction();
        try {
            $pago = $complemento->pagosRecibidos->first();
            if (!$pago) {
                throw new \Exception('El complemento no tiene pago asociado.');
            }

            DocumentoRelacionadoPago::where('pago_recibido_id', $pago->id)->delete();

            $fechaPago = \Carbon\Carbon::parse($validated['fecha_pago'])->startOfDay();
            $fechaPago = $fechaPago->isToday() ? now() : $fechaPago->setTime(12, 0, 0);

            $pago->update([
                'fecha_pago' => $fechaPago,
                'forma_pago' => $validated['forma_pago'],
                'moneda' => $validated['moneda'],
                'monto' => $validated['monto_total'],
                'num_operacion' => $validated['num_operacion'] ?? null,
            ]);

            $complemento->update(['monto_total' => $validated['monto_total']]);

            foreach ($validated['facturas'] as $facturaData) {
                $montoPagado = (float) ($facturaData['monto_pagado'] ?? 0);
                if ($montoPagado < 0.01) continue;

                $factura = \App\Models\Factura::findOrFail($facturaData['factura_id']);
                $cuentaPorCobrar = $factura->cuentaPorCobrar;
                if (!$cuentaPorCobrar) continue;
                $uuidFactura = trim((string) ($factura->uuid ?? ''));
                if ($uuidFactura === '' || !preg_match('/^[a-f0-9\-]{36}$/i', $uuidFactura)) continue;

                $parcialidad = DocumentoRelacionadoPago::where('factura_uuid', $factura->uuid)->count() + 1;
                $saldoAnterior = (float) $cuentaPorCobrar->saldo_pendiente_real;
                $saldoInsoluto = $saldoAnterior - $montoPagado;

                DocumentoRelacionadoPago::create([
                    'pago_recibido_id' => $pago->id,
                    'factura_id' => $factura->id,
                    'factura_uuid' => $factura->uuid,
                    'serie' => $factura->serie,
                    'folio' => $factura->folio,
                    'moneda' => $factura->moneda,
                    'monto_total' => $factura->total,
                    'parcialidad' => $parcialidad,
                    'saldo_anterior' => $saldoAnterior,
                    'monto_pagado' => $montoPagado,
                    'saldo_insoluto' => $saldoInsoluto,
                ]);
            }

            DB::commit();
            return redirect()->route('complementos.show', $complemento->id)
                ->with('success', 'Complemento de pago actualizado.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar complemento (solo borrador)
     */
    public function destroy(ComplementoPago $complemento)
    {
        if ($complemento->estado !== 'borrador') {
            return redirect()->route('complementos.show', $complemento->id)
                ->with('error', 'Solo se pueden eliminar complementos en borrador.');
        }
        DB::beginTransaction();
        try {
            foreach ($complemento->pagosRecibidos as $pago) {
                DocumentoRelacionadoPago::where('pago_recibido_id', $pago->id)->delete();
            }
            PagoRecibido::where('complemento_pago_id', $complemento->id)->delete();
            $complemento->forceDelete();
            DB::commit();
            return redirect()->route('complementos.index')
                ->with('success', 'Complemento de pago en borrador eliminado correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('complementos.show', $complemento->id)
                ->with('error', 'Error al eliminar: ' . $e->getMessage());
        }
    }

    /**
     * Timbrar complemento
     */
    public function timbrar(ComplementoPago $complemento)
    {
        if ($complemento->estado !== 'borrador') {
            return back()->with('error', 'Este complemento no puede ser timbrado');
        }

        DB::beginTransaction();
        try {
            // Llamar al servicio de timbrado
            $resultado = $this->pacService->timbrarComplemento($complemento);

            if (!$resultado['success']) {
                throw new \Exception($resultado['message']);
            }

            // Actualizar complemento (incluye sellos, cadena y pac_cfdi_id para cancelación)
            $complemento->update([
                'estado' => 'timbrado',
                'uuid' => $resultado['uuid'],
                'pac_cfdi_id' => $resultado['pac_cfdi_id'] ?? null,
                'fecha_timbrado' => $resultado['fecha_timbrado'] ?? now(),
                'no_certificado_sat' => $resultado['no_certificado_sat'] ?? null,
                'sello_cfdi' => $resultado['sello_cfdi'] ?? null,
                'sello_sat' => $resultado['sello_sat'] ?? null,
                'cadena_original' => $resultado['cadena_original'] ?? null,
                'xml_content' => $resultado['xml'] ?? null,
            ]);

            // Guardar XML
            if (isset($resultado['xml'])) {
                $xmlPath = $this->guardarXML($complemento, $resultado['xml']);
                $complemento->update(['xml_path' => $xmlPath]);
            }

            // Aplicar pagos a Cuentas por Cobrar (solo al timbrar, flujo fiscal correcto)
            $complemento->load('pagosRecibidos.documentosRelacionados.factura');
            foreach ($complemento->pagosRecibidos as $pagoRecibido) {
                foreach ($pagoRecibido->documentosRelacionados as $doc) {
                    $cuenta = $doc->factura->cuentaPorCobrar;
                    if ($cuenta) {
                        $cuenta->registrarPago((float) $doc->monto_pagado);
                    }
                }
            }

            // Generar PDF del complemento
            $pdfPath = $this->pdfService->generarComplementoPDF($complemento);
            $complemento->update(['pdf_path' => $pdfPath]);

            DB::commit();

            return redirect()->route('complementos.show', $complemento->id)
                ->with('success', $resultado['message'] . ' - PDF generado.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al timbrar: ' . $e->getMessage());
        }
    }

    /**
     * Descargar XML
     */
    public function descargarXML(ComplementoPago $complemento)
    {
        if (!$complemento->xml_path && empty($complemento->xml_content)) {
            return back()->with('error', 'XML no disponible');
        }

        $filepath = storage_path('app/' . $complemento->xml_path);
        if ($complemento->xml_path && file_exists($filepath)) {
            return response()->download($filepath, $complemento->folio_completo . '.xml');
        }
        $filepathPrivate = storage_path('app/private/' . $complemento->xml_path);
        if ($complemento->xml_path && file_exists($filepathPrivate)) {
            return response()->download($filepathPrivate, $complemento->folio_completo . '.xml');
        }
        if (!empty($complemento->xml_content)) {
            return response($complemento->xml_content, 200, [
                'Content-Type' => 'application/xml',
                'Content-Disposition' => 'attachment; filename="' . $complemento->folio_completo . '.xml"',
            ]);
        }
        return back()->with('error', 'Archivo XML no encontrado');
    }

    /**
     * Ver PDF en el navegador
     */
    public function verPDF(ComplementoPago $complemento)
    {
        $regenerar = $complemento->esBorrador()
            || !$complemento->pdf_path
            || !file_exists(storage_path('app/' . $complemento->pdf_path));

        if ($regenerar) {
            $pdfPath = $this->pdfService->generarComplementoPDF($complemento);
            $complemento->update(['pdf_path' => $pdfPath]);
        }
        return response()->file(storage_path('app/' . $complemento->pdf_path));
    }

    /**
     * Descargar PDF
     */
    public function descargarPDF(ComplementoPago $complemento)
    {
        $regenerar = $complemento->esBorrador()
            || !$complemento->pdf_path
            || !file_exists(storage_path('app/' . $complemento->pdf_path));

        if ($regenerar) {
            $pdfPath = $this->pdfService->generarComplementoPDF($complemento);
            $complemento->update(['pdf_path' => $pdfPath]);
        }
        return response()->download(
            storage_path('app/' . $complemento->pdf_path),
            $complemento->folio_completo . '.pdf'
        );
    }

    /**
     * Cancelar complemento de pago (SAT 2026 / Facturama).
     */
    public function cancelar(Request $request, ComplementoPago $complemento)
    {
        if (!$complemento->puedeCancelar()) {
            return back()->with('error', 'Solo se pueden cancelar complementos timbrados.');
        }

        $rules = ['motivo_cancelacion' => 'required|string|in:01,02,03,04'];
        if ($request->input('motivo_cancelacion') === '01') {
            $rules['uuid_sustituto'] = 'required|string|size:36';
        }
        $validated = $request->validate($rules);

        $uuidSustituto = ($validated['motivo_cancelacion'] ?? '') === '01'
            ? trim($validated['uuid_sustituto'] ?? '')
            : null;

        DB::beginTransaction();
        try {
            $resultado = $this->pacService->cancelarComplementoPago(
                $complemento,
                $validated['motivo_cancelacion'],
                $uuidSustituto
            );

            if (!$resultado['success']) {
                throw new \Exception($resultado['message']);
            }

            $complemento->update([
                'estado' => 'cancelado',
                'motivo_cancelacion' => $validated['motivo_cancelacion'],
                'fecha_cancelacion' => now(),
                'acuse_cancelacion' => $resultado['acuse'] ?? null,
                'codigo_estatus_cancelacion' => $resultado['codigo_estatus'] ?? '201',
            ]);

            // Revertir aplicación de pagos en cuentas por cobrar
            $complemento->load('pagosRecibidos.documentosRelacionados.factura');
            foreach ($complemento->pagosRecibidos as $pagoRecibido) {
                foreach ($pagoRecibido->documentosRelacionados as $doc) {
                    $cuenta = $doc->factura->cuentaPorCobrar;
                    if ($cuenta) {
                        $cuenta->revertirPago((float) $doc->monto_pagado);
                    }
                }
            }

            DB::commit();
            return redirect()->route('complementos.show', $complemento->id)
                ->with('success', 'Complemento cancelado en el SAT correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al cancelar: ' . $e->getMessage());
        }
    }

    /**
     * Listar complementos para modal Relación de CFDI (sustitución).
     * Incluye: timbrados y cancelados con motivo 02 (errores sin relación).
     */
    public function listarParaRelacion(Request $request)
    {
        $query = ComplementoPago::whereNotNull('uuid')
            ->with('cliente:id,nombre')
            ->where(function ($q) {
                $q->where('estado', 'timbrado')
                    ->orWhere(function ($q2) {
                        $q2->where('estado', 'cancelado')->where('motivo_cancelacion', '02');
                    });
            })
            ->orderByRaw("CASE WHEN estado = 'timbrado' THEN 0 ELSE 1 END ASC, COALESCE(fecha_timbrado, fecha_emision) DESC")
            ->limit(200);
        $excluirId = $request->get('excluir_id');
        if ($excluirId) {
            $query->where('id', '!=', $excluirId);
        }
        $lista = $query->get(['id', 'serie', 'folio', 'uuid', 'fecha_emision', 'fecha_timbrado', 'estado', 'motivo_cancelacion', 'cliente_id', 'monto_total']);

        return response()->json($lista->map(function ($c) {
            return [
                'id' => $c->id,
                'folio_completo' => $c->folio_completo,
                'uuid' => $c->uuid,
                'fecha' => $c->fecha_timbrado ? $c->fecha_timbrado->format('d/m/Y H:i') : $c->fecha_emision->format('d/m/Y'),
                'cliente' => $c->cliente ? $c->cliente->nombre : '',
                'monto_total' => (float) $c->monto_total,
                'estado' => $c->estado,
                'motivo_cancelacion' => $c->motivo_cancelacion,
            ];
        }));
    }

    /**
     * Descargar XML de acuse de cancelación del complemento.
     */
    public function descargarXmlCancelacion(ComplementoPago $complemento)
    {
        if ($complemento->estado !== 'cancelado' || empty($complemento->acuse_cancelacion)) {
            return back()->with('error', 'XML de cancelación no disponible.');
        }
        $decoded = base64_decode($complemento->acuse_cancelacion, true);
        if ($decoded === false) {
            return back()->with('error', 'Contenido del acuse no válido.');
        }
        return response($decoded, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="AcuseCancelacion_' . $complemento->folio_completo . '.xml"',
        ]);
    }

    /**
     * Obtener y guardar acuse de cancelación desde Facturama (complementos ya cancelados sin acuse).
     */
    public function obtenerAcuseCancelacion(ComplementoPago $complemento)
    {
        if ($complemento->estado !== 'cancelado') {
            return back()->with('error', 'Solo aplica a complementos cancelados.');
        }
        if (!empty($complemento->acuse_cancelacion)) {
            return back()->with('info', 'El acuse ya está guardado.');
        }
        $acuse = $this->pacService->obtenerAcuseCancelacionPorComplemento($complemento);
        if ($acuse) {
            $complemento->update(['acuse_cancelacion' => $acuse]);
            return back()->with('success', 'Acuse de cancelación guardado.');
        }
        return back()->with('error', 'No se pudo obtener el acuse desde Facturama.');
    }

    /**
     * Actualizar estatus de cancelación desde el SAT/PAC (solo complementos cancelados).
     * Obtiene el acuse actualizado y el código de estatus para reflejar la respuesta final del SAT.
     */
    public function actualizarEstatusCancelacion(ComplementoPago $complemento)
    {
        if ($complemento->estado !== 'cancelado') {
            return back()->with('error', 'Solo se puede actualizar el estatus de complementos cancelados.');
        }
        $acuse = $this->pacService->obtenerAcuseCancelacionPorComplemento($complemento);
        if (empty($acuse)) {
            return back()->with('error', 'No se pudo obtener la respuesta del SAT. Intente más tarde o verifique el complemento en Facturama.');
        }
        $codigoEstatus = FacturamaService::extraerCodigoEstatusDelAcuse($acuse);
        $complemento->update([
            'acuse_cancelacion' => $acuse,
            'codigo_estatus_cancelacion' => $codigoEstatus,
        ]);
        return back()->with('success', 'Estatus actualizado: ' . ComplementoPago::descripcionCodigoCancelacion($codigoEstatus) . ' (código ' . $codigoEstatus . ').');
    }

    /**
     * Obtener facturas pendientes de un cliente
     */
    public function facturasPendientes(Request $request)
    {
        $clienteId = $request->get('cliente_id');
        
        if (!$clienteId) {
            return response()->json([]);
        }

        // Si el cliente tiene complemento en borrador, indicarlo para redirección
        $complementoBorrador = ComplementoPago::where('cliente_id', $clienteId)->where('estado', 'borrador')->first();
        if ($complementoBorrador) {
            return response()->json([
                'facturas' => [],
                'complemento_borrador_id' => $complementoBorrador->id,
            ]);
        }

        $cuentas = CuentaPorCobrar::with('factura')
            ->excluirFacturaBorrador()
            ->where('cliente_id', $clienteId)
            ->whereIn('estado', ['pendiente', 'parcial', 'vencida'])
            ->where('monto_pendiente', '>', 0)
            ->whereHas('factura', function ($q) {
                $q->whereNotNull('uuid'); // SOLO facturas timbradas
            })
            ->get();

        $facturas = $cuentas->map(function ($cuenta) {
            $montoCubiertoPorNC = (float) NotaCredito::where('factura_id', $cuenta->factura_id)
                ->where('estado', 'timbrada')
                ->sum('total');
            $pendienteDisponible = max(0, (float) $cuenta->monto_pendiente - $montoCubiertoPorNC);
            return [
                'id' => $cuenta->factura_id,
                'folio' => $cuenta->factura->folio_completo,
                'uuid' => $cuenta->factura->uuid,
                'fecha' => $cuenta->fecha_emision->format('d/m/Y'),
                'total' => $cuenta->monto_total,
                'pendiente' => round($pendienteDisponible, 2),
                'pendiente_sin_nc' => round((float) $cuenta->monto_pendiente, 2),
            ];
        })->filter(function ($item) {
            return $item['pendiente'] > 0;
        })->values();

        return response()->json(['facturas' => $facturas, 'complemento_borrador_id' => null]);
    }

    /**
     * Guardar XML
     */
    protected function guardarXML(ComplementoPago $complemento, string $xml): string
    {
        $directory = storage_path('app/complementos/' . now()->format('Y/m'));
        
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = $complemento->folio_completo . '.xml';
        $filepath = $directory . '/' . $filename;
        
        file_put_contents($filepath, $xml);

        return 'complementos/' . now()->format('Y/m') . '/' . $filename;
    }
}