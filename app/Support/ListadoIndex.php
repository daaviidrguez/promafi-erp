<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Ayudas de listados rápidos en Hostinger (rango default + búsqueda liviana).
 */
class ListadoIndex
{
    public const DIAS_DEFAULT = 90;

    /**
     * Aplica últimos N días si no hay fechas ni flag "todas".
     * Devuelve [fecha_inicio|null, fecha_fin|null, rango_default_activo].
     *
     * @return array{0:?string,1:?string,2:bool}
     */
    public static function aplicarRangoFecha(
        Builder $query,
        Request $request,
        string $columna,
        int $dias = self::DIAS_DEFAULT
    ): array {
        $todas = $request->boolean('todas');
        $inicio = $request->filled('fecha_inicio') ? (string) $request->fecha_inicio : null;
        $fin = $request->filled('fecha_fin') ? (string) $request->fecha_fin : null;

        $defaultActivo = false;
        if (! $todas && $inicio === null && $fin === null) {
            $inicio = now()->subDays($dias)->toDateString();
            $defaultActivo = true;
        }

        if ($inicio !== null) {
            $query->where($columna, '>=', $inicio);
        }
        if ($fin !== null) {
            $query->where($columna, '<=', $fin);
        }

        return [$inicio, $fin, $defaultActivo];
    }

    /**
     * Búsqueda de facturas: prefijo en folio/serie; UUID exacto si parece UUID;
     * nombre/RFC con LIKE solo si el término no es numérico corto.
     */
    public static function buscarFactura(Builder $query, string $search): Builder
    {
        $term = trim($search);
        if ($term === '') {
            return $query;
        }

        $uuid = preg_replace('/[^a-fA-F0-9\-]/', '', $term) ?? '';
        if (strlen($uuid) >= 32 && substr_count($uuid, '-') >= 4) {
            return $query->where('uuid', $uuid);
        }

        return $query->where(function ($q) use ($term) {
            $q->where('folio', 'like', $term.'%')
                ->orWhere('serie', 'like', $term.'%')
                ->orWhereRaw("CONCAT(serie, '-', LPAD(folio, 4, '0')) LIKE ?", [$term.'%'])
                ->orWhere('nombre_receptor', 'like', $term.'%')
                ->orWhere('rfc_receptor', 'like', $term.'%');

            if (strlen($term) >= 3) {
                $q->orWhere('nombre_receptor', 'like', '%'.$term.'%')
                    ->orWhere('rfc_receptor', 'like', '%'.$term.'%');
            }
        });
    }

    /**
     * Búsqueda de cotizaciones en columnas denormalizadas (sin orWhereHas).
     */
    public static function buscarCotizacion(Builder $query, string $search): Builder
    {
        $term = trim($search);
        if ($term === '') {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('folio', 'like', $term.'%')
                ->orWhere('cliente_nombre', 'like', $term.'%')
                ->orWhere('cliente_rfc', 'like', $term.'%');

            if (strlen($term) >= 3) {
                $q->orWhere('cliente_nombre', 'like', '%'.$term.'%')
                    ->orWhere('cliente_rfc', 'like', '%'.$term.'%');
            }
        });
    }
}
