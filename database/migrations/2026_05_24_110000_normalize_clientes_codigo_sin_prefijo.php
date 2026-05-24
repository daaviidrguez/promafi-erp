<?php

use App\Services\ClienteCodigoGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('clientes')
            ->orderBy('id')
            ->lazyById()
            ->each(function ($cliente) {
                DB::table('clientes')
                    ->where('id', $cliente->id)
                    ->update([
                        'codigo' => ClienteCodigoGenerator::fromId((int) $cliente->id),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('clientes')
            ->orderBy('id')
            ->lazyById()
            ->each(function ($cliente) {
                DB::table('clientes')
                    ->where('id', $cliente->id)
                    ->update([
                        'codigo' => 'CLI-' . ClienteCodigoGenerator::fromId((int) $cliente->id),
                    ]);
            });
    }
};
