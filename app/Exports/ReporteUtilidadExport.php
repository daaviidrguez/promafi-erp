<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReporteUtilidadExport implements FromArray, WithHeadings
{
    public function __construct(
        protected array $lineas,
        protected float $totalIngreso,
        protected float $totalCosto,
        protected float $totalUtilidad,
        protected float $margen
    ) {}

    public function headings(): array
    {
        return [
            'Factura',
            'OC',
            'Fecha',
            'Cliente',
            'Producto / concepto',
            'Cantidad',
            'Costo unit.',
            'Costo',
            'Ingreso unit.',
            'Ingreso',
            'Margen %',
            'Utilidad',
            'Entregado',
            'Pagada',
        ];
    }

    public function array(): array
    {
        $rows = collect($this->lineas)->map(fn (array $l) => [
            $l['factura'],
            $l['oc'] ?? '—',
            $l['fecha'],
            $l['cliente'],
            $l['concepto'],
            $l['cantidad'],
            round($l['costo_unitario'] ?? 0, 4),
            round($l['costo'], 2),
            round($l['ingreso_unitario'] ?? 0, 4),
            round($l['ingreso'], 2),
            round($l['margen_pct'] ?? 0, 2),
            round($l['utilidad'], 2),
            $l['entregado_destino'] ?? 'No',
            $l['pagada'],
        ])->all();

        $empty = array_fill(0, 14, '');
        $rows[] = $empty;
        $rows[] = [
            '', '', '', '', '', 'Totales', '',
            round($this->totalCosto, 2),
            '',
            round($this->totalIngreso, 2),
            round($this->margen, 2),
            round($this->totalUtilidad, 2),
            '', '',
        ];

        return $rows;
    }
}
