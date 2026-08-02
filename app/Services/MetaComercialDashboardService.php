<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ClienteMetaComercial;
use App\Models\Factura;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dashboard de ventas: meta del asesor (PSI) + avance por clientes con meta.
 * Montos = subtotal sin IVA. Facturas timbradas por fecha_emision.
 */
class MetaComercialDashboardService
{
    /**
     * Panel principal: meta del asesor/equipo vs TODAS sus facturas del mes.
     *
     * @return array<string, mixed>
     */
    public function buildEquipo(Carbon $inicio, Carbon $fin, ?int $asesorId = null): array
    {
        if ($asesorId > 0) {
            $asesor = $this->asesoresActivos()->firstWhere('id', $asesorId)
                ?? User::query()->with('role')->find($asesorId);

            if ($asesor && $asesor->puedeTenerMetaComercial()) {
                return $this->buildParaAsesor($asesor, $inicio, $fin);
            }
        }

        $asesores = $this->asesoresConMeta();
        $meta = round((float) $asesores->sum(fn (User $u) => $u->metaVentasMensual()), 2);
        $asesorIds = $asesores->pluck('id');

        $facturas = $asesorIds->isEmpty()
            ? collect()
            : $this->facturasTimbradas($inicio, $fin)
                ->whereIn('usuario_id', $asesorIds)
                ->get(['id', 'fecha_emision', 'subtotal', 'cliente_id', 'usuario_id']);

        return $this->armarMetricas($meta, $facturas, $inicio, $fin, [
            'modo' => 'equipo',
            'num_asesores' => $asesores->count(),
            'subtitulo' => $asesores->isEmpty()
                ? 'No hay asesores con meta definida (admin/vendedor). Define la meta en Usuarios → Gestión comercial.'
                : 'Meta total de '.$asesores->count()
                    .' asesor(es) con meta. Todas sus facturas del mes (subtotal sin IVA).',
        ]);
    }

    /**
     * Asesores activos (admin + vendedor) para el filtro del dashboard.
     *
     * @return Collection<int, User>
     */
    public function asesoresActivos(): Collection
    {
        return User::query()
            ->with('role')
            ->activos()
            ->whereHas('role', fn ($q) => $q->whereIn('name', User::rolesAsesoresComerciales()))
            ->orderBy('name')
            ->get();
    }

    /**
     * Asesores activos con meta > 0 (consolidado "Todos").
     *
     * @return Collection<int, User>
     */
    public function asesoresConMeta(): Collection
    {
        return $this->asesoresActivos()
            ->filter(fn (User $u) => $u->metaVentasMensual() > 0)
            ->values();
    }

    /**
     * Avance por clientes que tienen meta (capa secundaria).
     * Si hay asesor, solo suma facturas de ese asesor; si no, todas.
     *
     * @return list<array<string, mixed>>
     */
    public function avancePorClientes(Carbon $inicio, Carbon $fin, ?int $asesorId = null): array
    {
        $anio = (int) $inicio->year;
        $clientes = $this->clientesConMetaEnAnio($anio);

        if ($clientes->isEmpty()) {
            return [];
        }

        $query = $this->facturasTimbradas($inicio, $fin)
            ->whereIn('cliente_id', $clientes->pluck('id'));

        if ($asesorId > 0) {
            $query->where('usuario_id', $asesorId);
        }

        $facturas = $query->get(['id', 'cliente_id', 'subtotal']);
        $facturadoPorCliente = $facturas->groupBy('cliente_id')->map(
            fn (Collection $g) => round((float) $g->sum(fn (Factura $f) => (float) $f->subtotal), 2)
        );

        $filas = [];
        foreach ($clientes as $cliente) {
            $meta = $this->metaMensualCliente($cliente, $anio);
            if ($meta <= 0) {
                continue;
            }

            $facturado = (float) ($facturadoPorCliente->get($cliente->id) ?? 0);
            $pct = round(min(100, ($facturado / $meta) * 100), 1);
            $faltante = round(max(0, $meta - $facturado), 2);

            $filas[] = [
                'cliente_id' => $cliente->id,
                'nombre' => $cliente->nombre_comercial ?: $cliente->nombre,
                'meta' => $meta,
                'facturado' => $facturado,
                'faltante' => $faltante,
                'pct_avance' => $pct,
                'num_facturas' => $facturas->where('cliente_id', $cliente->id)->count(),
            ];
        }

        usort($filas, fn ($a, $b) => $b['pct_avance'] <=> $a['pct_avance']);

        return $filas;
    }

    /**
     * @return Collection<int, Cliente>
     */
    public function clientesConMetaEnAnio(int $anio): Collection
    {
        return Cliente::query()
            ->whereHas('metasComerciales', fn ($q) => $q->where('anio', $anio))
            ->with(['metasComerciales' => fn ($q) => $q->where('anio', $anio)])
            ->orderBy('nombre')
            ->get();
    }

    public function metaMensualCliente(Cliente $cliente, int $anio): float
    {
        $metas = $cliente->relationLoaded('metasComerciales')
            ? $cliente->metasComerciales->where('anio', $anio)
            : $cliente->metasComerciales()->where('anio', $anio)->get();

        $mensual = $metas->firstWhere('periodo', ClienteMetaComercial::PERIODO_MENSUAL);
        if ($mensual) {
            return round((float) $mensual->monto_meta, 2);
        }

        $anual = $metas->firstWhere('periodo', ClienteMetaComercial::PERIODO_ANUAL);
        if ($anual) {
            return round((float) $anual->monto_meta / 12, 2);
        }

        return 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildParaAsesor(User $user, Carbon $inicio, Carbon $fin): array
    {
        $meta = $user->metaVentasMensual();

        $facturas = $this->facturasTimbradas($inicio, $fin)
            ->where('usuario_id', $user->id)
            ->get(['id', 'fecha_emision', 'subtotal', 'cliente_id', 'usuario_id']);

        return $this->armarMetricas($meta, $facturas, $inicio, $fin, [
            'modo' => 'asesor',
            'asesor_nombre' => $user->name,
            'subtitulo' => 'Todas las facturas del vendedor (subtotal sin IVA) vs meta mensual del asesor (sin IVA).',
        ]);
    }

    /**
     * @param  Collection<int, Factura>  $facturas
     * @param  array<string, mixed>  $extras
     * @return array<string, mixed>
     */
    private function armarMetricas(float $meta, Collection $facturas, Carbon $inicio, Carbon $fin, array $extras = []): array
    {
        $hoy = $this->diaReferencia($inicio, $fin);
        $diasMes = (int) $inicio->daysInMonth;
        $diaActual = $hoy->between($inicio, $fin) ? (int) $hoy->day : ($hoy->gt($fin) ? $diasMes : 0);

        $facturado = round((float) $facturas->sum(fn (Factura $f) => (float) $f->subtotal), 2);
        $faltante = round(max(0, $meta - $facturado), 2);

        $diasRestantes = 0;
        if ($hoy->lte($fin) && $faltante > 0) {
            $diasRestantes = max(1, (int) $hoy->diffInDays($fin->copy()->endOfDay()) + 1);
        }

        $produccionDiaria = ($faltante > 0 && $diasRestantes > 0)
            ? round($faltante / $diasRestantes, 2)
            : 0.0;

        $pctAvance = $meta > 0 ? round(min(100, ($facturado / $meta) * 100), 1) : 0.0;

        $montosPorDia = [];
        foreach ($facturas as $f) {
            if ($f->fecha_emision === null) {
                continue;
            }
            $key = $f->fecha_emision->format('Y-m-d');
            $montosPorDia[$key] = ($montosPorDia[$key] ?? 0) + (float) $f->subtotal;
        }

        $labels = [];
        $acumulado = [];
        $objetivoLineal = [];
        $suma = 0.0;

        for ($d = 1; $d <= $diasMes; $d++) {
            $fecha = Carbon::create($inicio->year, $inicio->month, $d);
            $labels[] = (string) $d;

            if ($d <= $diaActual) {
                $key = $fecha->format('Y-m-d');
                $suma += (float) ($montosPorDia[$key] ?? 0);
            }

            $acumulado[] = $d <= $diaActual ? round($suma, 2) : null;
            $objetivoLineal[] = $meta > 0 ? round($meta * ($d / $diasMes), 2) : 0.0;
        }

        return array_merge([
            'meta' => $meta,
            'facturado' => $facturado,
            'faltante' => $faltante,
            'pct_avance' => $pctAvance,
            'produccion_diaria' => $produccionDiaria,
            'dias_restantes' => $diasRestantes,
            'num_facturas' => $facturas->count(),
            'mes_label' => $inicio->locale('es')->translatedFormat('F Y'),
            'chart_avance' => [
                'avance' => $pctAvance,
                'restante' => round(max(0, 100 - $pctAvance), 1),
            ],
            'chart_tendencia' => [
                'labels' => $labels,
                'acumulado' => $acumulado,
                'objetivo' => $objetivoLineal,
            ],
        ], $extras);
    }

    private function facturasTimbradas(Carbon $inicio, Carbon $fin)
    {
        return Factura::query()
            ->where('estado', 'timbrada')
            ->whereBetween('fecha_emision', [$inicio->copy()->startOfDay(), $fin->copy()->endOfDay()]);
    }

    private function diaReferencia(Carbon $inicio, Carbon $fin): Carbon
    {
        $hoy = now()->startOfDay();

        if ($hoy->lt($inicio)) {
            return $inicio->copy()->subDay();
        }

        if ($hoy->gt($fin)) {
            return $fin->copy();
        }

        return $hoy;
    }
}
