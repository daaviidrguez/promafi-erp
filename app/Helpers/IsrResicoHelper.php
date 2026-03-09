<?php

namespace App\Helpers;

use App\Models\IsrResicoTasa;

/**
 * Cálculo de ISR estimado para Régimen Simplificado de Confianza (RESICO - 626).
 * Tabla de tasas aproximadas sobre ingreso mensual (editables desde frontend).
 */
class IsrResicoHelper
{
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
