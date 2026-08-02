<?php

namespace App\Services;

use App\Models\Factura;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * KPIs, gráficos y ranking del Dashboard de Ventas (montos sin IVA = subtotal).
 */
class VentasDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(int $anio, int $mes): array
    {
        $inicioMes = Carbon::create($anio, $mes, 1)->startOfMonth();
        $finMes = $inicioMes->copy()->endOfMonth();

        $inicioMesAnterior = $inicioMes->copy()->subMonth()->startOfMonth();
        $finMesAnterior = $inicioMesAnterior->copy()->endOfMonth();

        $facturasHistorial = Factura::query()
            ->where('estado', 'timbrada')
            ->where('fecha_emision', '>=', now()->copy()->subMonths(5)->startOfMonth())
            ->where('fecha_emision', '<=', $finMes->copy()->endOfDay())
            ->with(['usuario:id,name', 'cliente:id,nombre,nombre_comercial'])
            ->get(['id', 'fecha_emision', 'subtotal', 'total', 'usuario_id', 'cliente_id']);

        $facturasMes = $facturasHistorial->filter(
            fn (Factura $f) => $f->fecha_emision?->between($inicioMes, $finMes)
        );

        $facturasMesAnterior = $facturasHistorial->filter(
            fn (Factura $f) => $f->fecha_emision?->between($inicioMesAnterior, $finMesAnterior)
        );

        $ranking = $this->rankingVendedores($facturasMes, $facturasMesAnterior);
        $totalMes = (float) $ranking->sum('monto');
        $top = $ranking->first();

        return [
            'kpis' => [
                'facturado_mes' => $totalMes,
                'num_facturas' => $facturasMes->count(),
                'num_vendedores' => $ranking->where('monto', '>', 0)->count(),
                'ticket_promedio' => $facturasMes->count() > 0
                    ? round($totalMes / $facturasMes->count(), 2)
                    : 0.0,
                'top_vendedor' => $top['nombre'] ?? '—',
                'top_vendedor_monto' => (float) ($top['monto'] ?? 0),
            ],
            'ranking_vendedores' => $ranking->values()->all(),
            'chart_por_vendedor' => $this->chartPorVendedor($ranking),
            'chart_participacion' => $this->chartParticipacion($ranking, $totalMes),
            'chart_tendencia' => $this->chartTendenciaMensual($facturasHistorial, $finMes),
            'chart_top_vendedores_tendencia' => $this->chartTopVendedoresTendencia($facturasHistorial, $finMes, $ranking),
            'chart_top_clientes' => $this->topClientesFacturado($facturasMes),
        ];
    }

    /**
     * @param  Collection<int, Factura>  $facturasMes
     * @param  Collection<int, Factura>  $facturasMesAnterior
     * @return Collection<int, array<string, mixed>>
     */
    private function rankingVendedores(Collection $facturasMes, Collection $facturasMesAnterior): Collection
    {
        $montosAnterior = $this->montosPorUsuario($facturasMesAnterior);
        $agrupado = $this->montosPorUsuario($facturasMes);

        $usuarioIds = $agrupado->keys()
            ->merge($montosAnterior->keys())
            ->unique();

        $nombres = User::query()
            ->whereIn('id', $usuarioIds->filter(fn ($id) => $id > 0))
            ->pluck('name', 'id');

        $filas = $usuarioIds->map(function ($usuarioId) use ($agrupado, $montosAnterior, $nombres, $facturasMes) {
            $id = $usuarioId === null ? 0 : (int) $usuarioId;
            $monto = (float) ($agrupado->get($usuarioId) ?? 0);
            $montoAnterior = (float) ($montosAnterior->get($usuarioId) ?? 0);
            $numFacturas = $facturasMes->filter(
                fn (Factura $f) => ($f->usuario_id ?? null) === $usuarioId
            )->count();

            $variacion = $montoAnterior > 0
                ? round((($monto - $montoAnterior) / $montoAnterior) * 100, 1)
                : ($monto > 0 ? 100.0 : 0.0);

            return [
                'usuario_id' => $id > 0 ? $id : null,
                'nombre' => $id > 0 ? ($nombres[$id] ?? 'Usuario #'.$id) : 'Sin usuario asignado',
                'monto' => round($monto, 2),
                'monto_anterior' => round($montoAnterior, 2),
                'num_facturas' => $numFacturas,
                'variacion_pct' => $variacion,
            ];
        });

        return $filas->sortByDesc('monto')->values();
    }

    /**
     * @param  Collection<int, Factura>  $facturas
     * @return Collection<int|null, float>
     */
    private function montosPorUsuario(Collection $facturas): Collection
    {
        return $facturas->groupBy('usuario_id')->map(
            fn (Collection $grupo) => $grupo->sum(fn (Factura $f) => (float) $f->subtotal)
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $ranking
     * @return array{labels: list<string>, montos: list<float>}
     */
    private function chartPorVendedor(Collection $ranking): array
    {
        $top = $ranking->where('monto', '>', 0)->take(12);

        return [
            'labels' => $top->map(fn (array $r) => $this->truncarLabel($r['nombre']))->values()->all(),
            'montos' => $top->pluck('monto')->map(fn ($m) => round((float) $m, 2))->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $ranking
     * @return array{labels: list<string>, montos: list<float>}
     */
    private function chartParticipacion(Collection $ranking, float $totalMes): array
    {
        $conVenta = $ranking->where('monto', '>', 0);

        if ($totalMes <= 0 || $conVenta->isEmpty()) {
            return ['labels' => [], 'montos' => []];
        }

        return [
            'labels' => $conVenta->map(fn (array $r) => $this->truncarLabel($r['nombre'], 22))->values()->all(),
            'montos' => $conVenta->pluck('monto')->map(fn ($m) => round((float) $m, 2))->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, Factura>  $facturas
     * @return array{labels: list<string>, montos: list<float>}
     */
    private function chartTendenciaMensual(Collection $facturas, Carbon $hasta): array
    {
        $labels = [];
        $montos = [];

        for ($i = 5; $i >= 0; $i--) {
            $inicio = $hasta->copy()->subMonths($i)->startOfMonth();
            $fin = $inicio->copy()->endOfMonth();
            $labels[] = $inicio->locale('es')->translatedFormat('M Y');

            $monto = $facturas
                ->filter(fn (Factura $f) => $f->fecha_emision?->between($inicio, $fin))
                ->sum(fn (Factura $f) => (float) $f->subtotal);

            $montos[] = round((float) $monto, 2);
        }

        return compact('labels', 'montos');
    }

    /**
     * @param  Collection<int, Factura>  $facturas
     * @param  Collection<int, array<string, mixed>>  $ranking
     * @return array{labels: list<string>, datasets: list<array{label: string, data: list<float>, color: string}>}
     */
    private function chartTopVendedoresTendencia(Collection $facturas, Carbon $hasta, Collection $ranking): array
    {
        $topIds = $ranking
            ->where('monto', '>', 0)
            ->take(5)
            ->pluck('usuario_id')
            ->all();

        $labels = [];
        $meses = [];

        for ($i = 5; $i >= 0; $i--) {
            $inicio = $hasta->copy()->subMonths($i)->startOfMonth();
            $fin = $inicio->copy()->endOfMonth();
            $labels[] = $inicio->locale('es')->translatedFormat('M Y');
            $meses[] = [$inicio, $fin];
        }

        $colores = ['#0B3C5D', '#1F5F8B', '#10B981', '#F59E0B', '#EF4444'];
        $datasets = [];

        foreach ($topIds as $idx => $usuarioId) {
            $nombre = $ranking->firstWhere('usuario_id', $usuarioId)['nombre'] ?? 'Usuario';
            $data = [];

            foreach ($meses as [$inicio, $fin]) {
                $monto = $facturas
                    ->filter(function (Factura $f) use ($usuarioId, $inicio, $fin) {
                        return ($f->usuario_id ?? null) === $usuarioId
                            && $f->fecha_emision?->between($inicio, $fin);
                    })
                    ->sum(fn (Factura $f) => (float) $f->subtotal);

                $data[] = round((float) $monto, 2);
            }

            $datasets[] = [
                'label' => $this->truncarLabel($nombre, 24),
                'data' => $data,
                'color' => $colores[$idx % count($colores)],
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * @param  Collection<int, Factura>  $facturasMes
     * @return array{labels: list<string>, montos: list<float>}
     */
    private function topClientesFacturado(Collection $facturasMes): array
    {
        $facturadoPorCliente = [];

        foreach ($facturasMes as $f) {
            $cid = (int) $f->cliente_id;
            $facturadoPorCliente[$cid] = ($facturadoPorCliente[$cid] ?? 0) + (float) $f->subtotal;
        }

        arsort($facturadoPorCliente);
        $top = array_slice($facturadoPorCliente, 0, 10, true);

        $clientes = $facturasMes->keyBy('cliente_id');
        $labels = [];
        $montos = [];

        foreach ($top as $cid => $monto) {
            $f = $clientes->get($cid);
            $nombre = $f?->cliente?->nombre_comercial
                ?: ($f?->cliente?->nombre ?? 'Cliente #'.$cid);
            $labels[] = $this->truncarLabel($nombre);
            $montos[] = round($monto, 2);
        }

        return ['labels' => $labels, 'montos' => $montos];
    }

    private function truncarLabel(string $nombre, int $max = 28): string
    {
        return mb_strlen($nombre) > $max ? mb_substr($nombre, 0, $max - 1).'…' : $nombre;
    }
}
