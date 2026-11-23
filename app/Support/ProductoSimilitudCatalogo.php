<?php

namespace App\Support;

use App\Models\Producto;
use Illuminate\Support\Collection;

/**
 * Detección de productos similares sin recorrer todo el catálogo.
 * Misma regla de negocio: similar_text > 80% + textos casi idénticos (ratio longitud).
 */
class ProductoSimilitudCatalogo
{
    private const LIMITE_CANDIDATOS = 200;

    /**
     * Nombre del producto activo si la descripción supera el umbral de similitud.
     * Misma semántica que el barrido completo anterior (cotizaciones / producto rápido).
     */
    public static function nombreActivoSiDescripcionSuperaSimilitud(string $descripcion): ?string
    {
        $desc = mb_strtoupper(trim($descripcion));
        if (mb_strlen($desc) < 10) {
            return null;
        }

        foreach (self::candidatosActivos($desc) as $p) {
            foreach ([$p->nombre, $p->descripcion] as $campo) {
                if (! is_string($campo) || trim($campo) === '') {
                    continue;
                }
                $cmp = mb_strtoupper(trim($campo));
                if (mb_strlen($cmp) < 10) {
                    continue;
                }
                $percent = 0.0;
                similar_text($desc, $cmp, $percent);
                if ($percent > 80 && self::sonDescripcionesCasiIdenticas($desc, $cmp)) {
                    return (string) $p->nombre;
                }
            }
        }

        return null;
    }

    /**
     * @return Collection<int, Producto>
     */
    private static function candidatosActivos(string $descUpper): Collection
    {
        $tokens = preg_split('/\s+/u', $descUpper, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_filter($tokens, fn ($t) => mb_strlen((string) $t) >= 4));
        $tokens = array_slice($tokens, 0, 5);

        $compact = preg_replace('/\s+/u', '', $descUpper) ?? $descUpper;
        $prefix = mb_substr($compact, 0, 24);
        $head = mb_substr($descUpper, 0, 40);

        $query = Producto::query()
            ->where('activo', true)
            ->select(['id', 'nombre', 'descripcion']);

        $query->where(function ($w) use ($tokens, $prefix, $head, $descUpper) {
            $w->whereRaw('UPPER(TRIM(nombre)) = ?', [$descUpper]);

            if ($prefix !== '') {
                $esc = addcslashes($prefix, '%_\\');
                $w->orWhere('nombre', 'like', $esc.'%')
                    ->orWhere('nombre', 'like', '%'.$esc.'%')
                    ->orWhere('descripcion', 'like', '%'.$esc.'%');
            }

            if (mb_strlen($head) >= 10) {
                $escHead = addcslashes($head, '%_\\');
                $w->orWhere('nombre', 'like', $escHead.'%')
                    ->orWhere('descripcion', 'like', $escHead.'%');
            }

            foreach ($tokens as $t) {
                $esc = addcslashes((string) $t, '%_\\');
                $w->orWhere('nombre', 'like', '%'.$esc.'%')
                    ->orWhere('descripcion', 'like', '%'.$esc.'%');
            }
        });

        return $query->limit(self::LIMITE_CANDIDATOS)->get();
    }

    private static function sonDescripcionesCasiIdenticas(string $a, string $b): bool
    {
        $na = preg_replace('/\s+/u', ' ', $a) ?? $a;
        $nb = preg_replace('/\s+/u', ' ', $b) ?? $b;
        if ($na === $nb) {
            return true;
        }
        $lenA = mb_strlen($na);
        $lenB = mb_strlen($nb);
        if ($lenA === 0 || $lenB === 0) {
            return false;
        }
        $ratio = min($lenA, $lenB) / max($lenA, $lenB);

        return $ratio >= 0.85;
    }
}
