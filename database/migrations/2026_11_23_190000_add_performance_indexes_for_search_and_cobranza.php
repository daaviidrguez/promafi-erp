<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para búsquedas y cobranza (Hostinger I/O / full scans).
 * No cambia comportamiento funcional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->index(['activo', 'catalogo_online_visible'], 'productos_activo_catalogo_idx');
            $table->index('nombre', 'productos_nombre_idx');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->index(['activo', 'nombre'], 'clientes_activo_nombre_idx');
        });

        Schema::table('notas_credito', function (Blueprint $table) {
            $table->index(['factura_id', 'estado'], 'nc_factura_estado_idx');
        });

        Schema::table('cuentas_por_cobrar', function (Blueprint $table) {
            $table->index(['estado', 'fecha_vencimiento'], 'cxc_estado_vencimiento_idx');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex('productos_activo_catalogo_idx');
            $table->dropIndex('productos_nombre_idx');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex('clientes_activo_nombre_idx');
        });

        Schema::table('notas_credito', function (Blueprint $table) {
            $table->dropIndex('nc_factura_estado_idx');
        });

        Schema::table('cuentas_por_cobrar', function (Blueprint $table) {
            $table->dropIndex('cxc_estado_vencimiento_idx');
        });
    }
};
