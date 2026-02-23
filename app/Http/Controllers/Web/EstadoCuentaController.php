<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Services\EstadoCuentaService;
use App\Services\PDFService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;

class EstadoCuentaController extends Controller
{
    public function __construct(
        protected EstadoCuentaService $estadoCuentaService,
        protected PDFService $pdfService
    ) {}

    /**
     * Formulario: selección de cliente y filtros.
     */
    public function index(Request $request)
    {
        $clientes = Cliente::activos()->orderBy('nombre')->get();
        $clienteId = $request->get('cliente_id');
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');
        $tipoReporte = $request->get('tipo_reporte', 'estado_cuenta'); // estado_cuenta | reporte_cobranza

        return view('estado-cuenta.index', compact('clientes', 'clienteId', 'fechaDesde', 'fechaHasta', 'tipoReporte'));
    }

    /**
     * Ver estado de cuenta (reporte en pantalla).
     */
    public function ver(Request $request)
    {
        $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
            'tipo_reporte' => 'nullable|in:estado_cuenta,reporte_cobranza',
        ]);

        $cliente = Cliente::findOrFail($request->cliente_id);
        $soloCobranza = ($request->get('tipo_reporte') === 'reporte_cobranza');

        $datos = $this->estadoCuentaService->movimientosCliente(
            $cliente,
            $request->get('fecha_desde'),
            $request->get('fecha_hasta'),
            $soloCobranza
        );

        $empresa = Empresa::principal();

        return view('estado-cuenta.ver', [
            'movimientos' => $datos['movimientos'],
            'total_cargos' => $datos['total_cargos'],
            'total_abonos' => $datos['total_abonos'],
            'saldo_final' => $datos['saldo_final'],
            'cliente' => $datos['cliente'],
            'empresa' => $empresa,
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
            'tipo_reporte' => $request->get('tipo_reporte', 'estado_cuenta'),
            'es_reporte_cobranza' => $soloCobranza,
        ]);
    }

    /**
     * Descargar estado de cuenta en PDF.
     */
    public function pdf(Request $request)
    {
        $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
            'tipo_reporte' => 'nullable|in:estado_cuenta,reporte_cobranza',
        ]);

        $cliente = Cliente::findOrFail($request->cliente_id);
        $soloCobranza = ($request->get('tipo_reporte') === 'reporte_cobranza');

        $datos = $this->estadoCuentaService->movimientosCliente(
            $cliente,
            $request->get('fecha_desde'),
            $request->get('fecha_hasta'),
            $soloCobranza
        );

        $empresa = Empresa::principal();

        $html = view('pdf.estado-cuenta', [
            'movimientos' => $datos['movimientos'],
            'total_cargos' => $datos['total_cargos'],
            'total_abonos' => $datos['total_abonos'],
            'saldo_final' => $datos['saldo_final'],
            'cliente' => $datos['cliente'],
            'empresa' => $empresa,
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
            'es_reporte_cobranza' => $soloCobranza,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $nombreArchivo = 'EstadoCuenta_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $cliente->nombre) . '_' . now()->format('Y-m-d') . '.pdf';
        return response()->streamDownload(function () use ($dompdf) {
            echo $dompdf->output();
        }, $nombreArchivo, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
