<?php

namespace App\Services;

use App\Models\CuentaPorCobrar;
use App\Models\NotaCredito;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Resumen de cobranza para Cuentas por Cobrar:
 * - Total cobrado del mes (pagos de complementos timbrados por fecha_pago).
 * - Cartera vencida agrupada por mes de vencimiento (mismo criterio que la tarjeta "Cartera vencida").
 */
class CobranzaResumenService
{
    public const MES_ANTERIORES = 'anteriores';
    public const MES_POSTERIORES = 'posteriores';
    public const MES_SIN_FECHA = 'sin_fecha';

    /**
     * @return array<string, mixed>
     */
    public function cobradoMes(int $mes, int $anio): array
    {
        $inicio = Carbon::create($anio, $mes, 1)->startOfMonth();
        $prevInicio = $inicio->copy()->subMonthNoOverflow()->startOfMonth();

        $actual = $this->totalesCobrados($inicio, $inicio->copy()->endOfMonth());
        $anterior = $this->totalesCobrados($prevInicio, $prevInicio->copy()->endOfMonth());

        return [
            'mes_label' => $inicio->locale('es')->translatedFormat('F Y'),
            'mes_anterior_label' => $prevInicio->locale('es')->translatedFormat('F Y'),
            'total_cobrado' => $actual['total'],
            'total_sin_iva' => $actual['total_sin_iva'],
            'num_facturas' => $actual['num_facturas'],
            'total_cobrado_anterior' => $anterior['total'],
            'total_sin_iva_anterior' => $anterior['total_sin_iva'],
            'variacion_pct' => $this->variacionPct($actual['total'], $anterior['total']),
            'chart_comparativo' => [
                'labels' => [
                    $prevInicio->locale('es')->translatedFormat('M Y'),
                    $inicio->locale('es')->translatedFormat('M Y'),
                ],
                'total' => [$anterior['total'], $actual['total']],
                'sin_iva' => [$anterior['total_sin_iva'], $actual['total_sin_iva']],
            ],
        ];
    }

    /**
     * Saldo vencido (saldo real: monto_pendiente - NC timbradas) agrupado por mes de vencimiento.
     * Los saldos fuera del año se agrupan en filas extra para que el total cuadre con "Cartera vencida".
     *
     * @return array<string, mixed>
     */
    public function vencidoPorMes(int $anio): array
    {
        $cuentas = CuentaPorCobrar::query()
            ->excluirFacturaBorrador()
            ->vencidas()
            ->get(['id', 'factura_id', 'monto_pendiente', 'fecha_vencimiento']);

        $ncPorFactura = $cuentas->isEmpty()
            ? collect()
            : NotaCredito::query()
                ->whereIn('factura_id', $cuentas->pluck('factura_id')->unique()->values())
                ->where('estado', 'timbrada')
                ->groupBy('factura_id')
                ->selectRaw('factura_id, SUM(total) as total_nc')
                ->pluck('total_nc', 'factura_id');

        $buckets = [];
        $total = 0.0;
        foreach ($cuentas as $cuenta) {
            $saldo = max(0.0, (float) $cuenta->monto_pendiente - (float) ($ncPorFactura[$cuenta->factura_id] ?? 0));
            if ($saldo <= 0) {
                continue;
            }

            $key = $this->bucketKey($cuenta->fecha_vencimiento, $anio);
            $buckets[$key]['monto'] = ($buckets[$key]['monto'] ?? 0.0) + $saldo;
            $buckets[$key]['facturas'] = ($buckets[$key]['facturas'] ?? 0) + 1;
            $total += $saldo;
        }

        $pct = static fn (float $monto): float => $total > 0 ? round(($monto / $total) * 100, 1) : 0.0;

        $labels = [];
        $montos = [];
        $filas = [];

        $extra = function (string $key, string $label, string $mes) use (&$labels, &$montos, &$filas, $buckets, $pct) {
            if (empty($buckets[$key])) {
                return;
            }
            $monto = round($buckets[$key]['monto'], 2);
            $labels[] = $label;
            $montos[] = $monto;
            $filas[] = ['mes' => $mes, 'monto' => $monto, 'facturas' => $buckets[$key]['facturas'], 'porcentaje' => $pct($monto)];
        };

        $extra(self::MES_ANTERIORES, 'Ant. '.$anio, 'Anteriores a '.$anio);

        for ($m = 1; $m <= 12; $m++) {
            $key = sprintf('%04d-%02d', $anio, $m);
            $mesCarbon = Carbon::create($anio, $m, 1)->locale('es');
            $monto = round($buckets[$key]['monto'] ?? 0.0, 2);
            $labels[] = ucfirst($mesCarbon->translatedFormat('M'));
            $montos[] = $monto;
            $filas[] = [
                'mes' => ucfirst($mesCarbon->translatedFormat('F Y')),
                'monto' => $monto,
                'facturas' => $buckets[$key]['facturas'] ?? 0,
                'porcentaje' => $pct($monto),
            ];
        }

        $extra(self::MES_POSTERIORES, 'Post. '.$anio, 'Posteriores a '.$anio);
        $extra(self::MES_SIN_FECHA, 'Sin fecha', 'Sin fecha de vencimiento');

        return [
            'anio' => $anio,
            'total' => round($total, 2),
            'labels' => $labels,
            'montos' => $montos,
            'filas' => $filas,
        ];
    }

    /**
     * @return array{total: float, total_sin_iva: float, num_facturas: int}
     */
    private function totalesCobrados(Carbon $desde, Carbon $hasta): array
    {
        $filas = DB::table('documentos_relacionados_pago as d')
            ->join('pagos_recibidos as p', 'p.id', '=', 'd.pago_recibido_id')
            ->join('complementos_pago as c', 'c.id', '=', 'p.complemento_pago_id')
            ->join('facturas as f', 'f.id', '=', 'd.factura_id')
            ->where('c.estado', 'timbrado')
            ->whereNull('c.deleted_at')
            ->whereBetween('p.fecha_pago', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()])
            ->get(['d.factura_id', 'd.monto_pagado', 'f.subtotal', 'f.descuento', 'f.total']);

        $total = 0.0;
        $sinIva = 0.0;
        foreach ($filas as $fila) {
            $monto = (float) $fila->monto_pagado;
            $totalFactura = (float) $fila->total;
            $proporcion = $totalFactura > 0
                ? ((float) $fila->subtotal - (float) $fila->descuento) / $totalFactura
                : 1.0;
            $total += $monto;
            $sinIva += $monto * $proporcion;
        }

        return [
            'total' => round($total, 2),
            'total_sin_iva' => round($sinIva, 2),
            'num_facturas' => $filas->pluck('factura_id')->unique()->count(),
        ];
    }

    private function bucketKey($fechaVencimiento, int $anio): string
    {
        if (empty($fechaVencimiento)) {
            return self::MES_SIN_FECHA;
        }

        $fecha = Carbon::parse($fechaVencimiento);
        if ($fecha->year < $anio) {
            return self::MES_ANTERIORES;
        }
        if ($fecha->year > $anio) {
            return self::MES_POSTERIORES;
        }

        return $fecha->format('Y-m');
    }

    private function variacionPct(float $actual, float $anterior): ?float
    {
        if ($anterior <= 0.0) {
            return $actual > 0.0 ? 100.0 : null;
        }

        return round((($actual - $anterior) / $anterior) * 100, 1);
    }
}
