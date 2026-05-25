<?php

namespace App\Services;

use App\Models\Cliente;

class ClienteCodigoGenerator
{
    public static function fromId(int $id): string
    {
        return SequentialInternalCodeGenerator::fromId($id);
    }

    public static function generarSiguiente(): string
    {
        return SequentialInternalCodeGenerator::generarSiguiente(Cliente::class, ['CLI-']);
    }

    public static function parseSequence(?string $codigo): ?int
    {
        return SequentialInternalCodeGenerator::parseSequence($codigo, ['CLI-']);
    }
}
