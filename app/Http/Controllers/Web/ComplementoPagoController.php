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
                
                // Control
                'usuario_id' => auth()->id(),
            ]);

            // Crear pago recibido
            $pago = PagoRecibido::create([
                'complemento_pago_id' => $complemento->id,
                'fecha_pago' => $validated['fecha_pago'],
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

            // Actualizar complemento
            $complemento->update([
                'estado' => 'timbrado',
                'uuid' => $resultado['uuid'],
                'fecha_timbrado' => $resultado['fecha_timbrado'] ?? now(),
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
        if (!$complemento->xml_path) {
            return back()->with('error', 'XML no disponible');
        }

        $filepath = storage_path('app/' . $complemento->xml_path);
        
        if (!file_exists($filepath)) {
            return back()->with('error', 'Archivo XML no encontrado');
        }

        return response()->download($filepath, $complemento->folio_completo . '.xml');
    }

    /**
     * Ver PDF en el navegador
     */
    public function verPDF(ComplementoPago $complemento)
    {
        if (!$complemento->pdf_path || !file_exists(storage_path('app/' . $complemento->pdf_path))) {
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
        if (!$complemento->pdf_path || !file_exists(storage_path('app/' . $complemento->pdf_path))) {
            $pdfPath = $this->pdfService->generarComplementoPDF($complemento);
            $complemento->update(['pdf_path' => $pdfPath]);
        }
        return response()->download(
            storage_path('app/' . $complemento->pdf_path),
            $complemento->folio_completo . '.pdf'
        );
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

        $cuentas = CuentaPorCobrar::with('factura')
            ->excluirFacturaBorrador()
            ->where('cliente_id', $clienteId)
            ->whereIn('estado', ['pendiente', 'parcial', 'vencida'])
            ->where('monto_pendiente', '>', 0)
            ->whereHas('factura', function ($q) {
                $q->whereNotNull('uuid'); // SOLO facturas timbradas
            })
            ->get();

        return response()->json($cuentas->map(function ($cuenta) {
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
        })->values());
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