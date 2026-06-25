<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

/**
 * Evita timbrados concurrentes del mismo documento (doble clic, reintentos en paralelo).
 */
class TimbradoConcurrencyGuard
{
    public const LOCK_SECONDS = 120;

    public static function mensajeEnProceso(): string
    {
        return 'Este documento ya se está emitiendo. Espere a que termine el proceso antes de intentar de nuevo.';
    }

    public static function lockKey(string $tipo, int $id): string
    {
        return "timbrado:{$tipo}:{$id}";
    }

    /**
     * @return Lock|null null si otro proceso ya está timbrando este documento
     */
    public static function acquire(string $tipo, int $id): ?Lock
    {
        $lock = Cache::lock(self::lockKey($tipo, $id), self::LOCK_SECONDS);

        if (! $lock->get()) {
            return null;
        }

        return $lock;
    }

    public static function release(?Lock $lock): void
    {
        if ($lock !== null) {
            $lock->release();
        }
    }
}
