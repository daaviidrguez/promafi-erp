<?php

namespace App\Support;

use App\Models\Producto;

/**
 * Consecutivo PSI- sin pluck de todos los códigos (I/O en Hostinger).
 */
class ProductoPsiConsecutivo
{
    /**
     * Siguiente número libre >= $desde (usa MAX en SQL; el caller puede reintentar si hay carrera).
     */
    public static function siguienteNumeroDesde(int $desde = 1): int
    {
        $max = FolioNumerico::maxSecuencia(Producto::withTrashed(), 'PSI-%');

        return max(1, $desde, $max + 1);
    }
}
