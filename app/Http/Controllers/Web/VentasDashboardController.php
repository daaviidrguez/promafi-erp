<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\MetaComercialDashboardService;
use App\Services\VentasDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class VentasDashboardController extends Controller
{
    public function index(
        Request $request,
        VentasDashboardService $service,
        MetaComercialDashboardService $metaService,
    ): View {
        abort_unless($request->user()?->hasPermission('ventas.dashboard') || $request->user()?->isAdmin(), 403);

        $anio = max(2020, min(2035, (int) $request->input('anio', now()->year)));
        $mes = max(1, min(12, (int) $request->input('mes', now()->month)));
        $asesorId = $request->integer('asesor_id', 0);

        $inicioMes = Carbon::create($anio, $mes, 1)->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();

        $asesoresMeta = $metaService->asesoresActivos();
        $metaVentas = $metaService->buildEquipo(
            $inicioMes,
            $finMes,
            $asesorId > 0 ? $asesorId : null,
        );
        $avanceClientes = $metaService->avancePorClientes(
            $inicioMes,
            $finMes,
            $asesorId > 0 ? $asesorId : null,
        );

        $data = $service->build($anio, $mes);
        $mesLabel = $inicioMes->locale('es')->translatedFormat('F Y');

        return view('ventas.dashboard', array_merge($data, [
            'anio' => $anio,
            'mes' => $mes,
            'mesLabel' => $mesLabel,
            'asesorId' => $asesorId,
            'metaVentas' => $metaVentas,
            'asesoresMeta' => $asesoresMeta,
            'avanceClientes' => $avanceClientes,
        ]));
    }
}
