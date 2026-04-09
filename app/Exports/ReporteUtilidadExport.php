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
        protected float $margen,
        protected float $totalIvaAcreditable,
        protected float $totalCostoConIva
    ) {}

    public function headings(): array
    {
        return [
            'Pedido',
            'Factura',
            'Fecha factura',
            'Cliente',
            'Producto / concepto',
            'Costo unit.',
            'Venta unit.',
            'Margen %',
            'Utilidad unit.',
            'Cant.',
            'Costo',
            'Imp. IVA acred. (16%)',
            'Total costo c/IVA',
            'Ingreso',
            'Utilidad',
            'Entregado',
            'Pagada',
        ];
    }

    public function array(): array
    {
        $rows = collect($this->lineas)->map(fn (array $l) => [
            $l['oc'] ?? '—',
            $l['factura'],
            $l['fecha'],
            $l['cliente'],
            $l['concepto'],
            round($l['costo_unitario'] ?? 0, 4),
            round($l['ingreso_unitario'] ?? 0, 4),
            round($l['margen_pct'] ?? 0, 2),
            round($l['utilidad_unitaria'] ?? 0, 4),
            $l['cantidad'],
            round($l['costo'], 2),
            round($l['iva_acreditable'] ?? 0, 2),
            round($l['costo_con_iva'] ?? 0, 2),
            round($l['ingreso'], 2),
            round($l['utilidad'], 2),
            $l['entregado_destino'] ?? 'No',
            $l['pagada'],
        ])->all();

        $empty = array_fill(0, 17, '');
        $rows[] = $empty;
        $rows[] = [
            '', '', '', '', 'Totales',
            '', '', round($this->margen, 2), '',
            '',
            round($this->totalCosto, 2),
            round($this->totalIvaAcreditable, 2),
            round($this->totalCostoConIva, 2),
            round($this->totalIngreso, 2),
            round($this->totalUtilidad, 2),
            '', '',
        ];

        return $rows;
    }
}
