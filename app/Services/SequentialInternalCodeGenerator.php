<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class SequentialInternalCodeGenerator
{
    public const SEQUENCE_LENGTH = 5;

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $legacyPrefixes
     */
    public static function generarSiguiente(string $modelClass, array $legacyPrefixes = []): string
    {
        return DB::transaction(function () use ($modelClass, $legacyPrefixes) {
            $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);

            $query = $usesSoftDeletes
                ? $modelClass::withTrashed()
                : $modelClass::query();

            $maxSequence = $query
                ->whereNotNull('codigo')
                ->where('codigo', '!=', '')
                ->lockForUpdate()
                ->pluck('codigo')
                ->map(fn (string $codigo) => self::parseSequence($codigo, $legacyPrefixes))
                ->filter(fn (?int $n) => $n !== null)
                ->max();

            return self::fromSequence(($maxSequence ?? 0) + 1);
        });
    }

    public static function fromId(int $id): string
    {
        return self::fromSequence($id);
    }

    public static function fromSequence(int $sequence): string
    {
        return str_pad((string) $sequence, self::SEQUENCE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<string>  $legacyPrefixes
     */
    public static function parseSequence(?string $codigo, array $legacyPrefixes = []): ?int
    {
        if ($codigo === null || $codigo === '') {
            return null;
        }

        if (preg_match('/^(\d+)$/', $codigo, $matches)) {
            return (int) $matches[1];
        }

        foreach ($legacyPrefixes as $prefix) {
            $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)$/';
            if (preg_match($pattern, $codigo, $legacy)) {
                return (int) $legacy[1];
            }
        }

        return null;
    }
}
