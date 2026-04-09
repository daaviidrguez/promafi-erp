<?php

namespace App\Models\Concerns;

/**
 * Desglose de traslados y retenciones como en representación impresa CFDI (PDF / vista show).
 */
trait HasDesgloseTotalesCfdi
{
    /**
     * @return array{impuestosPorTasa: array<string, array{base: float, importe: float, nombre: string}>, totalIva: float, totalRetenciones: float}
     */
    public function desgloseTotalesCfdi(): array
    {
        $totalIva = 0.0;
        $totalRetenciones = 0.0;
        $impuestosPorTasa = [];

        foreach ($this->detalles ?? [] as $d) {
            foreach ($d->impuestos ?? [] as $imp) {
                if ($imp->tipo === 'traslado') {
                    $totalIva += (float) $imp->importe;
                    $tasa = (float) ($imp->tasa_o_cuota ?? 0);
                    $key = (string) $tasa;
                    if (! isset($impuestosPorTasa[$key])) {
                        $pct = $tasa >= 1 ? $tasa : ($tasa * 100);
                        $nombreBase = $imp->nombre_impuesto ?? 'IVA';
                        $impuestosPorTasa[$key] = [
                            'base' => 0.0,
                            'importe' => 0.0,
                            'nombre' => $nombreBase.' '.number_format($pct, 0).'%',
                        ];
                    }
                    $impuestosPorTasa[$key]['base'] += (float) ($imp->base ?? 0);
                    $impuestosPorTasa[$key]['importe'] += (float) $imp->importe;
                } else {
                    $totalRetenciones += (float) $imp->importe;
                }
            }
        }

        return [
            'impuestosPorTasa' => $impuestosPorTasa,
            'totalIva' => $totalIva,
            'totalRetenciones' => $totalRetenciones,
        ];
    }
}
