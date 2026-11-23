<?php

namespace App\Support;

use App\Models\NotaCredito;
use Illuminate\Support\Collection;

/**
 * Cálculo de saldo pendiente real en lote (evita N+1 de notas de crédito).
 */
class CobranzaSaldoBatch
{
    /**
     * @param  iterable<int|string|null>  $facturaIds
     * @return Collection<int|string, float|string> factura_id => SUM(total) NC timbradas
     */
    public static function ncTimbradaPorFactura(iterable $facturaIds): Collection
    {
        $ids = collect($facturaIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return NotaCredito::query()
            ->whereIn('factura_id', $ids)
            ->where('estado', 'timbrada')
            ->groupBy('factura_id')
            ->selectRaw('factura_id, SUM(total) as total_nc')
            ->pluck('total_nc', 'factura_id');
    }

    public static function saldoDesdeMontoYNc(float $montoPendiente, int|string|null $facturaId, Collection $ncPorFactura): float
    {
        $totalNc = (float) ($ncPorFactura[$facturaId] ?? 0);

        return max(0.0, $montoPendiente - $totalNc);
    }

    /**
     * Hidrata saldo_pendiente_real en cada cuenta sin queries adicionales.
     *
     * @param  Collection<int, \App\Models\CuentaPorCobrar>  $cuentas
     * @return Collection<int, \App\Models\CuentaPorCobrar>
     */
    public static function hidratarCuentas(Collection $cuentas): Collection
    {
        if ($cuentas->isEmpty()) {
            return $cuentas;
        }

        $ncPorFactura = self::ncTimbradaPorFactura($cuentas->pluck('factura_id'));

        foreach ($cuentas as $cuenta) {
            $cuenta->hidratarSaldoPendienteReal(
                self::saldoDesdeMontoYNc((float) $cuenta->monto_pendiente, $cuenta->factura_id, $ncPorFactura)
            );
        }

        return $cuentas;
    }

    public static function estadoDisplayFromSaldo(object $cuenta, float $saldoReal): string
    {
        if (($cuenta->estado ?? null) === 'cancelada') {
            return 'cancelada';
        }
        if ($saldoReal <= 0) {
            return 'pagada';
        }
        if (method_exists($cuenta, 'estaVencida') ? $cuenta->estaVencida() : false) {
            return 'vencida';
        }
        if ((float) ($cuenta->monto_pagado ?? 0) > 0) {
            return 'parcial';
        }

        return 'pendiente';
    }
}
