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
        $clienteId = $request->integer('cliente_id', 0);

        $inicioMes = Carbon::create($anio, $mes, 1)->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();

        $clientesMeta = $metaService->clientesConMetaEnAnio($anio);
        $metaVentas = $metaService->build(
            $inicioMes,
            $finMes,
            $clienteId > 0 ? $clienteId : null,
        );

        $data = $service->build($anio, $mes);
        $mesLabel = $inicioMes->locale('es')->translatedFormat('F Y');

        return view('ventas.dashboard', array_merge($data, [
            'anio' => $anio,
            'mes' => $mes,
            'mesLabel' => $mesLabel,
            'clienteId' => $clienteId,
            'metaVentas' => $metaVentas,
            'clientesMeta' => $clientesMeta,
        ]));
    }
}
