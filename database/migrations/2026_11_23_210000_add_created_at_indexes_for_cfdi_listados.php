<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices de apoyo para listados que aún ordenan por created_at.
 * Los listados CFDI principales ya priorizan fecha_emision/fecha (indexadas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->index('created_at', 'facturas_created_at_idx');
        });

        Schema::table('complementos_pago', function (Blueprint $table) {
            $table->index('created_at', 'complementos_pago_created_at_idx');
        });

        Schema::table('notas_credito', function (Blueprint $table) {
            $table->index('created_at', 'notas_credito_created_at_idx');
        });

        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->index('created_at', 'cotizaciones_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropIndex('facturas_created_at_idx');
        });

        Schema::table('complementos_pago', function (Blueprint $table) {
            $table->dropIndex('complementos_pago_created_at_idx');
        });

        Schema::table('notas_credito', function (Blueprint $table) {
            $table->dropIndex('notas_credito_created_at_idx');
        });

        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropIndex('cotizaciones_created_at_idx');
        });
    }
};
