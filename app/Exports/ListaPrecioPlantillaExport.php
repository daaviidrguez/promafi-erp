<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ListaPrecioPlantillaExport implements FromArray, WithHeadings
{
    public function __construct(
        protected array $detalles
    ) {}

    public function headings(): array
    {
        return [
            'id',
            'producto_id',
            'producto',
            'codigo',
            'tipo_utilidad',
            'valor_utilidad',
            'activo',
        ];
    }

    public function array(): array
    {
        return collect($this->detalles)->map(function ($d) {
            $p = $d->producto;
            return [
                $d->id,
                $d->producto_id,
                $p?->nombre ?? '',
                $p?->codigo ?? '',
                $d->tipo_utilidad === 'factorizado' ? 1 : 2,
                (float) $d->valor_utilidad,
                $d->activo ? 1 : 2,
            ];
        })->all();
    }
}
