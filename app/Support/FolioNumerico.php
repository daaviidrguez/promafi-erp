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
     */
    public static function maxSecuencia(Builder $query, string $prefijoLike): int
    {
        $driver = $query->getConnection()->getDriverName();
        $scoped = (clone $query)->where('folio', 'like', $prefijoLike);

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $max = $scoped
                ->toBase()
                ->selectRaw("MAX(CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED)) as max_num")
                ->value('max_num');

            return (int) ($max ?? 0);
        }

        $max = 0;
        foreach ($scoped->pluck('folio') as $folio) {
            if (preg_match('/-(\d+)$/', (string) $folio, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max;
    }
}
