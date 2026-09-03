<?php

namespace App\Services;

use App\Models\Producto;
use Illuminate\Support\Facades\DB;

/**
 * Importación masiva de productos (upsert por codigo).
 * Columnas alineadas con la plantilla de Productos.
 */
class ProductoImportService
{
    public const HEADINGS = [
        'codigo',
        'nombre',
        'marca',
        'descripcion',
        'clave_sat',
        'clave_unidad_sat',
        'unidad',
        'objeto_impuesto',
        'tipo_impuesto',
        'tipo_factor',
        'tasa_iva',
        'costo',
        'precio_venta',
        'precio_mayoreo',
        'precio_minimo',
        'stock_minimo',
        'controla_inventario',
        'aplica_iva',
        'activo',
    ];

    /**
     * Valores por defecto al importar filas incompletas / plantilla.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'marca' => '',
            'clave_sat' => '01010101',
            'clave_unidad_sat' => 'H87',
            'unidad' => 'Pieza',
            'objeto_impuesto' => '02',
            'tipo_impuesto' => '002',
            'tipo_factor' => 'Tasa',
            'tasa_iva' => 0.16,
            'precio_minimo' => 0,
            'stock_minimo' => 0,
            'controla_inventario' => 1,
            'aplica_iva' => 1,
            'activo' => 1,
        ];
    }

    public static function mapUnidad(string $unidad): string
    {
        $u = strtoupper(trim($unidad));
        if ($u === '' || in_array($u, ['PZA', 'PZ', 'PZA.', 'PIEZA', 'PZA '], true)) {
            return 'Pieza';
        }

        return mb_substr(trim($unidad), 0, 20) ?: 'Pieza';
    }

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

        foreach ($filas as $row) {
            $parsed = $this->normalizarFila($row);
            if ($parsed === null) {
                $omitidos++;
                continue;
            }
            // Último gana si hay códigos duplicados en el mismo lote.
            $batch[$parsed['codigo']] = $parsed;
        }

        if ($batch === []) {
            return compact('creados', 'actualizados', 'omitidos');
        }

        DB::transaction(function () use ($batch, &$creados, &$actualizados) {
            $codigos = array_keys($batch);
            $existentes = Producto::withTrashed()
                ->whereIn('codigo', $codigos)
                ->get()
                ->keyBy('codigo');

            foreach ($batch as $codigo => $data) {
                /** @var Producto|null $producto */
                $producto = $existentes->get($codigo);

                if ($producto) {
                    if ($producto->trashed()) {
                        $producto->restore();
                    }
                    // No tocar stock ni imágenes; solo datos de catálogo/precios.
                    unset($data['stock']);
                    $producto->fill($data);
                    $producto->save();
                    $actualizados++;
                } else {
                    $data['stock'] = 0;
                    Producto::create($data);
                    $creados++;
                }
            }
        });

        return compact('creados', 'actualizados', 'omitidos');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function normalizarFila(array $row): ?array
    {
        $defaults = self::defaults();
        $codigo = strtoupper(trim($this->asText($row['codigo'] ?? '')));
        if ($codigo === '') {
            return null;
        }

        $nombre = trim($this->asText($row['nombre'] ?? ''));
        if ($nombre === '') {
            return null;
        }

        $tipoFactor = trim($this->asText($row['tipo_factor'] ?? $defaults['tipo_factor']));
        if (! in_array($tipoFactor, ['Tasa', 'Exento'], true)) {
            $tipoFactor = 'Tasa';
        }

        $objeto = trim($this->asText($row['objeto_impuesto'] ?? $defaults['objeto_impuesto']));
        if (! in_array($objeto, ['01', '02', '03'], true)) {
            $objeto = '02';
        }

        $claveSat = trim($this->asText($row['clave_sat'] ?? $defaults['clave_sat']));
        if ($claveSat === '') {
            $claveSat = $defaults['clave_sat'];
        }
        $claveSat = mb_substr($claveSat, 0, 8);

        $claveUnidad = trim($this->asText($row['clave_unidad_sat'] ?? $defaults['clave_unidad_sat']));
        if ($claveUnidad === '') {
            $claveUnidad = $defaults['clave_unidad_sat'];
        }
        $claveUnidad = mb_substr($claveUnidad, 0, 3);

        $unidad = trim($this->asText($row['unidad'] ?? ''));
        $unidad = self::mapUnidad($unidad !== '' ? $unidad : (string) $defaults['unidad']);

        $tasaIva = $this->aFloat($row['tasa_iva'] ?? $defaults['tasa_iva']);
        if ($tasaIva < 0 || $tasaIva > 1) {
            // Permite 16 en lugar de 0.16
            if ($tasaIva > 1 && $tasaIva <= 100) {
                $tasaIva = $tasaIva / 100;
            } else {
                $tasaIva = (float) $defaults['tasa_iva'];
            }
        }

        $aplicaIva = $this->aBool($row['aplica_iva'] ?? null, $tipoFactor !== 'Exento');
        if ($tipoFactor === 'Exento') {
            $aplicaIva = false;
            $tasaIva = 0;
        }

        $marca = trim($this->asText($row['marca'] ?? $defaults['marca']));
        $descripcion = trim($this->asText($row['descripcion'] ?? ''));
        if ($descripcion === '') {
            $descripcion = $nombre;
        }

        return [
            'codigo' => mb_substr($codigo, 0, 50),
            'nombre' => mb_substr($nombre, 0, 255),
            'marca' => $marca !== '' ? mb_substr($marca, 0, 120) : null,
            'descripcion' => $descripcion,
            'clave_sat' => $claveSat,
            'clave_unidad_sat' => $claveUnidad,
            'unidad' => $unidad,
            'objeto_impuesto' => $objeto,
            'tipo_impuesto' => trim($this->asText($row['tipo_impuesto'] ?? $defaults['tipo_impuesto'])) ?: '002',
            'tipo_factor' => $tipoFactor,
            'tasa_iva' => $tasaIva,
            'costo' => max(0, $this->aFloat($row['costo'] ?? 0)),
            'precio_venta' => max(0, $this->aFloat($row['precio_venta'] ?? 0)),
            'precio_mayoreo' => max(0, $this->aFloat($row['precio_mayoreo'] ?? ($row['precio_venta'] ?? 0))),
            'precio_minimo' => max(0, $this->aFloat($row['precio_minimo'] ?? 0)),
            'stock_minimo' => max(0, $this->aFloat($row['stock_minimo'] ?? 0)),
            'controla_inventario' => $this->aBool($row['controla_inventario'] ?? null, true),
            'aplica_iva' => $aplicaIva,
            'activo' => $this->aBool($row['activo'] ?? null, true),
        ];
    }

    private function asText(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
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
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $raw = str_replace([',', '$', ' ', '%'], ['', '', '', ''], (string) $value);

        return is_numeric($raw) ? (float) $raw : 0.0;
    }

    private function aBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        $v = strtolower(trim((string) $value));

        return in_array($v, ['1', 'true', 'si', 'sí', 'yes', 'activo'], true);
    }
}
