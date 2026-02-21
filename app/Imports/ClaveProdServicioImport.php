<?php

namespace App\Imports;

use App\Models\ClaveProdServicio;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ClaveProdServicioImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row): ?ClaveProdServicio
    {
        $clave = trim((string) ($row['clave'] ?? ''));
        $descripcion = trim((string) ($row['descripcion'] ?? ''));
        if ($clave === '' || $descripcion === '') {
            return null;
        }
        $clave = str_pad(substr($clave, 0, 8), 8, '0', STR_PAD_LEFT);

        return ClaveProdServicio::updateOrCreate(
            ['clave' => $clave],
            ['descripcion' => $descripcion, 'activo' => true, 'orden' => 0]
        );
    }

    public function rules(): array
    {
        return [
            'clave' => 'required|string|max:8',
            'descripcion' => 'required|string|max:500',
        ];
    }
}
