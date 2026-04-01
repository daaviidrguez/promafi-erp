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
            'Fecha',
            'Cliente',
            'Producto / concepto',
            'Cantidad',
            'Ingreso',
            'Costo',
            'Utilidad',
            'Pagada',
        ];
    }

    public function array(): array
    {
        $rows = collect($this->lineas)->map(fn (array $l) => [
            $l['factura'],
            $l['fecha'],
            $l['cliente'],
            $l['concepto'],
            $l['cantidad'],
            round($l['ingreso'], 2),
            round($l['costo'], 2),
            round($l['utilidad'], 2),
            $l['pagada'],
        ])->all();

        $rows[] = ['', '', '', '', '', '', '', '', ''];
        $rows[] = ['', '', '', '', 'Totales', round($this->totalIngreso, 2), round($this->totalCosto, 2), round($this->totalUtilidad, 2), ''];
        $rows[] = ['', '', '', '', 'Margen %', round($this->margen, 2), '', '', ''];

        return $rows;
    }
}
