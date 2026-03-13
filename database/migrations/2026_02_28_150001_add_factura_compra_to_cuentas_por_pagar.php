<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuentas_por_pagar', function (Blueprint $table) {
            $table->foreignId('factura_compra_id')->nullable()->after('id')->constrained('facturas_compra')->nullOnDelete();
        });

        // Hacer orden_compra_id nullable (una cuenta puede venir de OC o de factura directa)
        Schema::table('cuentas_por_pagar', function (Blueprint $table) {
            $table->dropForeign(['orden_compra_id']);
        });
        Schema::table('cuentas_por_pagar', function (Blueprint $table) {
            $table->foreignId('orden_compra_id')->nullable()->change();
            $table->foreign('orden_compra_id')->references('id')->on('ordenes_compra')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cuentas_por_pagar', function (Blueprint $table) {
            $table->dropConstrainedForeignId('factura_compra_id');
        });
        Schema::table('cuentas_por_pagar', function (Blueprint $table) {
            $table->dropForeign(['orden_compra_id']);
            $table->foreignId('orden_compra_id')->nullable(false)->change();
            $table->foreign('orden_compra_id')->references('id')->on('ordenes_compra')->cascadeOnDelete();
        });
    }
};
