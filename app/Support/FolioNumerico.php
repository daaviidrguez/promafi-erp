<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Extrae el máximo número de secuencia de folios tipo SERIE-0001 sin traer todos a PHP.
 */
class FolioNumerico
{
    /**
     * Máximo valor numérico del último segmento tras '-' (p. ej. COT-0012 → 12, OC-2026-0003 → 3).
     *
     * @param  string  $columna  Columna a inspeccionar (folio, codigo, etc.).
     */
    public static function maxSecuencia(Builder $query, string $prefijoLike, string $columna = 'folio'): int
    {
        $columna = preg_replace('/[^a-zA-Z0-9_]/', '', $columna) ?: 'folio';
        $driver = $query->getConnection()->getDriverName();
        $scoped = (clone $query)->where($columna, 'like', $prefijoLike);

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $max = $scoped
                ->toBase()
                ->selectRaw("MAX(CAST(SUBSTRING_INDEX(`{$columna}`, '-', -1) AS UNSIGNED)) as max_num")
                ->value('max_num');

            return (int) ($max ?? 0);
        }

        $max = 0;
        foreach ($scoped->pluck($columna) as $valor) {
            if (preg_match('/-(\d+)$/', (string) $valor, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max;
    }
}
