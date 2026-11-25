<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table) {
            $table->decimal('costo_unitario', 15, 4)->nullable()->after('stock_resultante');
            $table->decimal('costo_promedio_anterior', 15, 4)->nullable()->after('costo_unitario');
            $table->decimal('costo_promedio_resultante', 15, 4)->nullable()->after('costo_promedio_anterior');
        });
    }

    public function down(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table) {
            $table->dropColumn(['costo_unitario', 'costo_promedio_anterior', 'costo_promedio_resultante']);
        });
    }
};
