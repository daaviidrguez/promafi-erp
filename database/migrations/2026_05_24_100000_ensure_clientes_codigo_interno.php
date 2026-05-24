<?php

use App\Services\ClienteCodigoGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clientes', 'codigo')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->string('codigo', 20)->unique()->nullable()->after('id');
            });
        }

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

        Schema::table('clientes', function (Blueprint $table) {
            $table->string('codigo', 20)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('codigo', 20)->nullable()->change();
        });
    }
};
