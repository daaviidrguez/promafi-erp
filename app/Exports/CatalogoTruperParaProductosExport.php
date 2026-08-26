<?php

namespace App\Exports;

use App\Models\CatalogoTruper;
use App\Services\ProductoImportService;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exporta catálogo Truper ya mapeado a columnas de plantilla Productos
 * (listo para revisar y subir en Productos → Importar).
 */
class CatalogoTruperParaProductosExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading
{
    public function query()
    {
        return CatalogoTruper::query()->orderBy('codigo');
    }

    public function headings(): array
    {
        return ProductoImportService::HEADINGS;
    }

    /**
     * @param  CatalogoTruper  $item
     * @return list<mixed>
     */
    public function map($item): array
    {
        return ProductoImportService::mapFromTruper($item);
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
