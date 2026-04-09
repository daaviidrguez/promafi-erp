<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReporteVentasMensualesExport implements FromArray, WithHeadings
{
    public function __construct(
        protected array $lineas,
        protected int $numFacturas,
        protected float $subtotalVentas,
        protected float $ivaVentas,
        protected float $isrRetenidoVentas,
        protected float $totalVentas,
    ) {}

    public function headings(): array
    {
        return [
            'Serie / Folio',
            'Fecha',
            'Cliente',
            'Subtotal',
            'IVA',
            'ISR retenido',
            'Total',
        ];
    }

    public function array(): array
    {
        $rows = collect($this->lineas)->map(fn (array $l) => [
            $l['factura'],
            $l['fecha'],
            $l['cliente'],
            round($l['subtotal'], 2),
            round($l['iva'], 2),
            round($l['isr_retenido'], 2),
            round($l['total'], 2),
        ])->all();

        $rows[] = ['', '', '', '', '', '', ''];
        $rows[] = [
            '',
            '',
            'Resumen del período',
            round($this->subtotalVentas, 2),
            round($this->ivaVentas, 2),
            round($this->isrRetenidoVentas, 2),
            round($this->totalVentas, 2),
        ];
        $rows[] = ['', '', 'Cantidad de facturas', $this->numFacturas, '', '', ''];

        return $rows;
    }
}
