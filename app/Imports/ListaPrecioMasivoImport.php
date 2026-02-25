<?php

namespace App\Imports;

use App\Models\ListaPrecioDetalle;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;

class ListaPrecioMasivoImport implements ToCollection, WithHeadingRow
{
    public function __construct(
        protected int $listaPrecioId
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if (!$id) {
                continue;
            }

            $detalle = ListaPrecioDetalle::where('lista_precio_id', $this->listaPrecioId)
                ->find($id);

            if (!$detalle) {
                continue;
            }

            $tipo = (int) ($row['tipo_utilidad'] ?? 1);
            $detalle->tipo_utilidad = $tipo === 2 ? 'margen' : 'factorizado';

            $valor = (float) ($row['valor_utilidad'] ?? $detalle->valor_utilidad);
            $detalle->valor_utilidad = max(1, min(99, $valor));

            $activo = (int) ($row['activo'] ?? 1);
            $detalle->activo = $activo === 1;

            $detalle->save();
        }
    }
}
