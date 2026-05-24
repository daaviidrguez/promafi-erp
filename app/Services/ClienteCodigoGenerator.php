<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Support\Facades\DB;

class ClienteCodigoGenerator
{
    public const SEQUENCE_LENGTH = 5;

    public static function fromId(int $id): string
    {
        return self::fromSequence($id);
    }

    public static function fromSequence(int $sequence): string
    {
        return str_pad((string) $sequence, self::SEQUENCE_LENGTH, '0', STR_PAD_LEFT);
    }

    public static function generarSiguiente(): string
    {
        return DB::transaction(function () {
            $maxSequence = Cliente::withTrashed()
                ->whereNotNull('codigo')
                ->lockForUpdate()
                ->pluck('codigo')
                ->map(fn (string $codigo) => self::parseSequence($codigo))
                ->filter(fn (?int $n) => $n !== null)
                ->max();

            return self::fromSequence(($maxSequence ?? 0) + 1);
        });
    }

    public static function parseSequence(?string $codigo): ?int
    {
        if ($codigo === null || $codigo === '') {
            return null;
        }

        if (preg_match('/^CLI-(\d+)$/', $codigo, $legacy)) {
            return (int) $legacy[1];
        }

        if (preg_match('/^(\d+)$/', $codigo, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
