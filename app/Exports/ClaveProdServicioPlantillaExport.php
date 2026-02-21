<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClaveProdServicioPlantillaExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['clave', 'descripcion'];
    }

    public function array(): array
    {
        return [
            ['01010101', 'No existe en el catálogo'],
            ['43211601', 'Multímetros'],
        ];
    }
}
