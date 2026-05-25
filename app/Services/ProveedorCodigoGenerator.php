<?php

namespace App\Services;

use App\Models\Proveedor;

class ProveedorCodigoGenerator
{
    public static function fromId(int $id): string
    {
        return SequentialInternalCodeGenerator::fromId($id);
    }

    public static function generarSiguiente(): string
    {
        return SequentialInternalCodeGenerator::generarSiguiente(Proveedor::class);
    }
}
