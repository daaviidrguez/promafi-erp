<?php

namespace App\Helpers;

use App\Models\IsrResicoTasa;
use App\Models\Empresa;
use App\Models\Cliente;

/**
 * Cálculo de ISR estimado para Régimen Simplificado de Confianza (RESICO - 626).
 * Tabla de tasas aproximadas sobre ingreso mensual (editables desde frontend).
 * Retención ISR cuando persona moral paga a persona física RESICO (SAT 2026).
 */
class IsrResicoHelper
{
    /**
     * Indica si aplica retención ISR: emisor es PF RESICO y receptor es persona moral.
     * La persona moral (receptor) debe retener 1.25% sobre el subtotal según LISR.
     */
    public static function aplicaRetencionIsrPm(Empresa $empresa, Cliente $cliente): bool
    {
        $esResico = ($empresa->tipo_persona ?? 'moral') === 'fisica'
            && ($empresa->regimen_fiscal ?? '') === config('isr_resico.regimen_clave', '626');
        $receptorEsPm = ($cliente->tipo_persona ?? 'fisica') === 'moral';

        return $esResico && $receptorEsPm;
    }

    /**
     * Calcula el monto de retención ISR cuando PM paga a PF RESICO.
     * Base: subtotal - descuento (según LISR Art. 113-J).
     */
    public static function calcularRetencionIsrPm(float $subtotal, float $descuento = 0): float
    {
        $tasa = (float) config('isr_resico.tasa_retencion_pm_a_resico', 0.0125);
        $base = max(0, $subtotal - $descuento);

        return round($base * $tasa, 2);
    }
    private static function getTasas(): array
    {
        $modelos = IsrResicoTasa::orderBy('orden')->orderBy('desde')->get();
        if ($modelos->isEmpty()) {
            return config('isr_resico.tasas', []);
        }
        return $modelos->map(fn ($t) => [
            'desde' => (float) $t->desde,
            'hasta' => (float) $t->hasta,
            'tasa' => (float) $t->tasa,
        ])->toArray();
    }

    public static function calcularIsr(float $ingresoMensual): float
    {
        $tasas = self::getTasas();
        $isr = 0.0;
        $restante = $ingresoMensual;

        foreach ($tasas as $rango) {
            if ($restante <= 0) {
                break;
            }
            $limiteRango = $rango['hasta'] - $rango['desde'];
            $baseEnRango = min($restante, $limiteRango);
            $isr += $baseEnRango * $rango['tasa'];
            $restante -= $baseEnRango;
        }

        return round($isr, 2);
    }

    public static function getTasaAplicable(float $ingresoMensual): ?float
    {
        $tasas = self::getTasas();
        foreach ($tasas as $rango) {
            if ($ingresoMensual >= $rango['desde'] && $ingresoMensual < $rango['hasta']) {
                return $rango['tasa'];
            }
        }
        return null;
    }
}
