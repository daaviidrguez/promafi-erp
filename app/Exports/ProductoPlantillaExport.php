<?php

namespace App\Exports;

use App\Services\ProductoImportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductoPlantillaExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ProductoImportService::HEADINGS;
    }

    public function array(): array
    {
        $d = ProductoImportService::defaults();

        return [
            [
                'TRU-EJEMPLO',
                'Desarmador punta plana 1/4"',
                $d['marca'],
                'Desarmador punta plana 1/4" — ejemplo de plantilla',
                $d['clave_sat'],
                $d['clave_unidad_sat'],
                $d['unidad'],
                $d['objeto_impuesto'],
                $d['tipo_impuesto'],
                $d['tipo_factor'],
                $d['tasa_iva'],
                25.50,
                42.00,
                42.00,
                $d['precio_minimo'],
                $d['stock_minimo'],
                $d['controla_inventario'],
                $d['aplica_iva'],
                $d['activo'],
            ],
        ];
    }
}
