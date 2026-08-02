<?php

namespace App\Services;

use App\Models\CatalogoTruper;

/**
 * Upsert de catálogo Truper en lotes (bloques de 500).
 */
class CatalogoTruperImportService
{
    /**
     * @param  array<int, array<string, mixed>>  $filas
     * @return array{creados: int, actualizados: int, omitidos: int}
     */
    public function upsertFilas(array $filas): array
    {
        $creados = 0;
        $actualizados = 0;
        $omitidos = 0;
        $batch = [];
        $now = now();

        foreach ($filas as $row) {
            $codigo = $this->normalizarTexto($row['codigo'] ?? '');
            if ($codigo === '') {
                $omitidos++;
                continue;
            }

            $descripcion = trim((string) ($row['descripcion'] ?? ''));
            if ($descripcion === '') {
                $omitidos++;
                continue;
            }

            $clave = trim((string) ($row['clave'] ?? ''));
            $unidad = trim((string) ($row['unidad'] ?? '')) ?: 'PZA';
            $codigoSat = trim((string) ($row['codigo_sat'] ?? ''));

            $batch[$codigo] = [
                'codigo' => $codigo,
                'clave' => $clave !== '' ? $clave : null,
                'descripcion' => $descripcion,
                'unidad' => $unidad,
                'costo' => $this->aFloat($row['costo'] ?? 0),
                'venta' => $this->aFloat($row['venta'] ?? 0),
                'codigo_sat' => $codigoSat !== '' ? $codigoSat : null,
                'peso_kg' => $this->aFloatNullable($row['peso_kg'] ?? null),
                'volumen_cm3' => $this->aFloatNullable($row['volumen_cm3'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($batch === []) {
            return compact('creados', 'actualizados', 'omitidos');
        }

        $codigos = array_keys($batch);
        $existentes = CatalogoTruper::whereIn('codigo', $codigos)->pluck('codigo')->all();
        $existentesSet = array_fill_keys($existentes, true);

        foreach ($codigos as $codigo) {
            if (isset($existentesSet[$codigo])) {
                $actualizados++;
            } else {
                $creados++;
            }
        }

        CatalogoTruper::upsert(
            array_values($batch),
            ['codigo'],
            ['clave', 'descripcion', 'unidad', 'costo', 'venta', 'codigo_sat', 'peso_kg', 'volumen_cm3', 'updated_at']
        );

        return compact('creados', 'actualizados', 'omitidos');
    }

    private function normalizarTexto(mixed $value): string
    {
        if (is_float($value) || is_int($value)) {
            if (is_float($value) && floor($value) == $value) {
                return (string) (int) $value;
            }

            return trim((string) $value);
        }

        return trim((string) $value);
    }

    private function aFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $raw = str_replace([',', '$', ' '], ['', '', ''], (string) $value);

        return is_numeric($raw) ? (float) $raw : 0;
    }

    private function aFloatNullable(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->aFloat($value);
    }
}
