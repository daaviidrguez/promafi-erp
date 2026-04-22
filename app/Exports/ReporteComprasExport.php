<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReporteComprasExport implements FromArray, WithHeadings
{
    public function __construct(
        protected array $lineas,
        protected int $numFacturas,
        protected float $subtotalCompras,
        protected float $ivaCompras,
        protected float $totalCompras,
    ) {}

    public function headings(): array
    {
        return [
            'Folio / referencias',
            'Fecha',
            'Proveedor',
            'Subtotal',
            'IVA acreditable',
            'Total',
        ];
    }

    public function array(): array
    {
        $rows = collect($this->lineas)->map(fn (array $l) => [
            $l['folio'],
            $l['fecha'],
            $l['proveedor'],
            round($l['subtotal'], 2),
            round($l['iva'], 2),
            round($l['total'], 2),
        ])->all();

        $rows[] = ['', '', '', '', '', ''];
        $rows[] = [
            '',
            '',
            'Resumen del período',
            round($this->subtotalCompras, 2),
            round($this->ivaCompras, 2),
            round($this->totalCompras, 2),
        ];
        $rows[] = ['', '', 'Facturas de compra', $this->numFacturas, '', ''];

        return $rows;
    }
}
