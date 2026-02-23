<?php

namespace App\Imports;

use App\Models\ClaveProdServicio;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithChunkReading;

/**
 * Importación de claves producto/servicio SAT.
 * Acepta clave como número o texto (Excel suele guardar 01010101 como número).
 * Procesa en bloques para no exceder tiempo ni memoria.
 */
class ClaveProdServicioImport implements ToModel, WithHeadingRow, WithValidation, WithChunkReading
{
    public function chunkSize(): int
    {
        return 500;
    }

    public function model(array $row): ?ClaveProdServicio
    {
        $clave = $this->normalizarClave($row['clave'] ?? '');
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

    private function normalizarClave(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $s = is_string($value) ? $value : (string) $value;
        return trim(preg_replace('/\s+/', '', $s));
    }

    public function rules(): array
    {
        return [
            'clave' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $s = $this->normalizarClave($value);
                    if ($s === '') {
                        $fail('El campo clave es obligatorio.');
                        return;
                    }
                    if (strlen($s) > 8) {
                        $fail('La clave no debe superar 8 caracteres.');
                    }
                },
            ],
            'descripcion' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $s = trim((string) $value);
                    if ($s === '') {
                        $fail('El campo descripcion es obligatorio.');
                        return;
                    }
                    if (strlen($s) > 500) {
                        $fail('La descripción no debe superar 500 caracteres.');
                    }
                },
            ],
        ];
    }
}
