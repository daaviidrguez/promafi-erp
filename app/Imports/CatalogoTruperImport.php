<?php

namespace App\Imports;

use App\Services\CatalogoTruperImportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Importa catálogo Truper desde Excel en bloques de 500.
 * Encabezados reales suelen ser:
 * código, clave, descripcion, unidad,
 * "COSTO: precio distribuidor sin IVA", "VENTA: Precio Medio Mayoreo sin IVA",
 * Codigo SAT, Peso[Kg], Volumen[cm3]
 */
class CatalogoTruperImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    public int $creados = 0;

    public int $actualizados = 0;

    public int $omitidos = 0;

    public function __construct(
        protected ?CatalogoTruperImportService $service = null
    ) {
        $this->service ??= new CatalogoTruperImportService;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows): void
    {
        $filas = [];
        foreach ($rows as $row) {
            $filas[] = [
                'codigo' => $this->valor($row, ['codigo', 'código', 'code']),
                'clave' => $this->valor($row, ['clave']),
                'descripcion' => $this->valor($row, ['descripcion', 'descripción', 'description']),
                'unidad' => $this->valor($row, ['unidad']) ?: 'PZA',
                // Prefijo: "COSTO: precio distribuidor sin IVA" → costo_precio_distribuidor_sin_iva
                'costo' => $this->numeroConPrefijo($row, 'costo', [
                    'costo',
                    'precio_distribuidor',
                    'precio_distribuidor_sin_iva',
                    'costo_precio_distribuidor_sin_iva',
                ]),
                'venta' => $this->numeroConPrefijo($row, 'venta', [
                    'venta',
                    'precio_medio_mayoreo',
                    'precio_medio_mayoreo_sin_iva',
                    'venta_precio_medio_mayoreo_sin_iva',
                ]),
                'codigo_sat' => $this->valor($row, ['codigo_sat', 'codigosat', 'clave_sat']),
                'peso_kg' => $this->numeroNullable($row, ['peso_kg', 'pesokg', 'peso']),
                'volumen_cm3' => $this->numeroNullable($row, ['volumen_cm3', 'volumencm3', 'volumen']),
            ];
        }

        $result = $this->service->upsertFilas($filas);
        $this->creados += $result['creados'];
        $this->actualizados += $result['actualizados'];
        $this->omitidos += $result['omitidos'];
    }

    private function valor(Collection|array $row, array $keys): string
    {
        $arr = $row instanceof Collection ? $row->toArray() : $row;
        foreach ($keys as $key) {
            if (array_key_exists($key, $arr) && $arr[$key] !== null && $arr[$key] !== '') {
                return $this->normalizarTexto($arr[$key]);
            }
        }

        foreach ($keys as $key) {
            foreach ($arr as $k => $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                $norm = preg_replace('/[^a-z0-9]/', '', strtolower((string) $k));
                $target = preg_replace('/[^a-z0-9]/', '', strtolower($key));
                if ($norm === $target) {
                    return $this->normalizarTexto($v);
                }
            }
        }

        return '';
    }

    /**
     * Busca valor numérico por clave exacta o por encabezado que empiece con el prefijo
     * (ej. costo → costo_precio_distribuidor_sin_iva).
     */
    private function numeroConPrefijo(Collection|array $row, string $prefijo, array $keys): float
    {
        $raw = $this->valor($row, $keys);
        if ($raw !== '') {
            return $this->parseNumero($raw);
        }

        $arr = $row instanceof Collection ? $row->toArray() : $row;
        $prefijoNorm = preg_replace('/[^a-z0-9]/', '', strtolower($prefijo));

        foreach ($arr as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $norm = preg_replace('/[^a-z0-9]/', '', strtolower((string) $k));
            if ($norm === $prefijoNorm || str_starts_with($norm, $prefijoNorm)) {
                return $this->parseNumero($this->normalizarTexto($v));
            }
        }

        return 0;
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

    private function parseNumero(string $raw): float
    {
        $raw = str_replace([',', '$', ' '], ['', '', ''], $raw);

        return is_numeric($raw) ? (float) $raw : 0;
    }

    private function numeroNullable(Collection|array $row, array $keys): ?float
    {
        $raw = $this->valor($row, $keys);
        if ($raw === '') {
            return null;
        }

        return $this->parseNumero($raw);
    }
}
