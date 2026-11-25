<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table) {
            $table->foreignId('reverso_de_id')
                ->nullable()
                ->after('entrada_anticipada_id')
                ->constrained('inventario_movimientos')
                ->nullOnDelete();
            $table->unique('reverso_de_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table) {
            $table->dropUnique(['reverso_de_id']);
            $table->dropConstrainedForeignId('reverso_de_id');
        });
    }
};
