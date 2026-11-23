<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices compuestos para orderBy fecha + id en listados (simplePaginate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->index(['fecha_emision', 'id'], 'facturas_fecha_emision_id_idx');
        });

        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->index(['fecha', 'id'], 'cotizaciones_fecha_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropIndex('facturas_fecha_emision_id_idx');
        });

        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropIndex('cotizaciones_fecha_id_idx');
        });
    }
};
