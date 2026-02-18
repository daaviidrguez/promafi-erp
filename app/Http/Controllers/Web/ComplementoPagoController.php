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
use App\Services\PACServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComplementoPagoController extends Controller
{
    public function __construct(
        protected PACServiceInterface $pacService
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
        
        // Si viene de una cuenta por cobrar específica
        $cuentaPreseleccionada = null;
        if ($request->has('cuenta_id')) {
            $cuentaPreseleccionada = CuentaPorCobrar::with('factura')->find($request->cuenta_id);
        }

        return view('complementos.create', compact('empresa', 'clientes', 'cuentaPreseleccionada'));
    }

    /**
     * Guardar complemento
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'fecha_pago' => 'required|date',
            'forma_pago' => 'required|string|max:2',
            'monto_total' => 'required|numeric|min:0.01',
            'moneda' => 'required|string|max:3',
            'num_operacion' => 'nullable|string|max:100',
            'facturas' => 'required|array|min:1',
            'facturas.*.factura_id' => 'required|exists:facturas,id',
            'facturas.*.monto_pagado' => 'required|numeric|min:0.01',
        ]);

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

            // Crear documentos relacionados (facturas pagadas)
            foreach ($validated['facturas'] as $index => $facturaData) {
                $factura = \App\Models\Factura::findOrFail($facturaData['factura_id']);
                $cuentaPorCobrar = $factura->cuentaPorCobrar;

                if (!$cuentaPorCobrar) {
                    throw new \Exception("La factura {$factura->folio_completo} no tiene cuenta por cobrar asociada");
                }

                // Calcular parcialidad
                $parcialidad = DocumentoRelacionadoPago::where('factura_uuid', $factura->uuid)->count() + 1;
                
                // Calcular saldo anterior
                $saldoAnterior = $cuentaPorCobrar->monto_pendiente;
                $saldoInsoluto = $saldoAnterior - $facturaData['monto_pagado'];

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
                    'monto_pagado' => $facturaData['monto_pagado'],
                    'saldo_insoluto' => $saldoInsoluto,
                ]);

                // Registrar pago en cuenta por cobrar
                $cuentaPorCobrar->registrarPago($facturaData['monto_pagado']);
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
            'pagosRecibidos.documentosRelacionados.factura',
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

            DB::commit();

            return redirect()->route('complementos.show', $complemento->id)
                ->with('success', $resultado['message']);

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
     * Obtener facturas pendientes de un cliente
     */
    public function facturasPendientes(Request $request)
    {
        $clienteId = $request->get('cliente_id');
        
        if (!$clienteId) {
            return response()->json([]);
        }

        $cuentas = CuentaPorCobrar::with('factura')
            ->where('cliente_id', $clienteId)
            ->whereIn('estado', ['pendiente', 'parcial', 'vencida'])
            ->where('monto_pendiente', '>', 0)
            ->whereHas('factura', function ($q) {
                $q->whereNotNull('uuid'); // 🔥 SOLO TIMBRADAS
            })
            ->get();

        return response()->json($cuentas->map(function($cuenta) {
            return [
                'id' => $cuenta->factura_id,
                'folio' => $cuenta->factura->folio_completo,
                'uuid' => $cuenta->factura->uuid,
                'fecha' => $cuenta->fecha_emision->format('d/m/Y'),
                'total' => $cuenta->monto_total,
                'pendiente' => $cuenta->monto_pendiente,
            ];
        }));
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