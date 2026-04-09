<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Costo unitario congelado al timbrar la factura (reporte de utilidad e históricos sin depender del catálogo).
     */
    public function up(): void
    {
        Schema::table('facturas_detalle', function (Blueprint $table) {
            $table->decimal('costo_unitario_timbrado', 15, 6)->nullable()->after('base_impuesto');
        });
    }

    public function down(): void
    {
        Schema::table('facturas_detalle', function (Blueprint $table) {
            $table->dropColumn('costo_unitario_timbrado');
        });
    }
};
