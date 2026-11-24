<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para stats (por_vencer / groupBy estado) y búsqueda por cliente en index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->index(['estado', 'fecha_vencimiento'], 'cotizaciones_estado_vencimiento_idx');
            $table->index('cliente_nombre', 'cotizaciones_cliente_nombre_idx');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropIndex('cotizaciones_estado_vencimiento_idx');
            $table->dropIndex('cotizaciones_cliente_nombre_idx');
        });
    }
};
