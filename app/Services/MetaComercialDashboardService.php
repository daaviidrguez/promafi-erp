<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ClienteMetaComercial;
use App\Models\Factura;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Panel meta vs real (sin IVA) usando ClienteMetaComercial.
 * Mensual = monto fijo; Anual = monto / 12 para el mes consultado.
 */
class MetaComercialDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(Carbon $inicio, Carbon $fin, ?int $clienteId = null): array
    {
        $anio = (int) $inicio->year;
        $clientesConMeta = $this->clientesConMetaEnAnio($anio);

        if ($clienteId > 0) {
            $cliente = $clientesConMeta->firstWhere('id', $clienteId)
                ?? Cliente::query()->find($clienteId);

            if ($cliente) {
                return $this->buildParaCliente($cliente, $inicio, $fin, $anio);
            }
        }

        $meta = round((float) $clientesConMeta->sum(
            fn (Cliente $c) => $this->metaMensualCliente($c, $anio)
        ), 2);

        $clienteIds = $clientesConMeta->pluck('id');

        $facturas = $clienteIds->isEmpty()
            ? collect()
            : $this->facturasTimbradas($inicio, $fin)
                ->whereIn('cliente_id', $clienteIds)
                ->get(['id', 'fecha_emision', 'subtotal', 'cliente_id']);

        return $this->armarMetricas($meta, $facturas, $inicio, $fin, [
            'modo' => 'global',
            'num_clientes_meta' => $clientesConMeta->count(),
            'subtitulo' => 'Meta consolidada de '.$clientesConMeta->count()
                .' cliente(s) con meta en '.$anio
                .'. Facturación del mes (subtotal sin IVA).',
        ]);
    }

    /**
     * Clientes que tienen al menos una meta (anual o mensual) en el año.
     *
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

    /**
     * @return array<string, mixed>
     */
    public function buildParaCliente(Cliente $cliente, Carbon $inicio, Carbon $fin, int $anio): array
    {
        if (! $cliente->relationLoaded('metasComerciales')) {
            $cliente->load(['metasComerciales' => fn ($q) => $q->where('anio', $anio)]);
        }

        $meta = $this->metaMensualCliente($cliente, $anio);

        $facturas = $this->facturasTimbradas($inicio, $fin)
            ->where('cliente_id', $cliente->id)
            ->get(['id', 'fecha_emision', 'subtotal', 'cliente_id']);

        return $this->armarMetricas($meta, $facturas, $inicio, $fin, [
            'modo' => 'cliente',
            'cliente_nombre' => $cliente->nombre_comercial ?: $cliente->nombre,
            'subtitulo' => 'Facturación del mes (subtotal sin IVA) vs meta del cliente (sin IVA).',
        ]);
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
            $objetivoLineal[] = round($meta * ($d / $diasMes), 2);
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
