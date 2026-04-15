<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('uso_cfdi', 4)->nullable()->after('regimen_fiscal');
        });

        Schema::table('cotizaciones_compra', function (Blueprint $table) {
            $table->string('proveedor_regimen_fiscal', 3)->nullable()->after('proveedor_rfc');
            $table->string('proveedor_uso_cfdi', 4)->nullable()->after('proveedor_regimen_fiscal');
        });

        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->string('proveedor_regimen_fiscal', 3)->nullable()->after('proveedor_rfc');
            $table->string('proveedor_uso_cfdi', 4)->nullable()->after('proveedor_regimen_fiscal');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->dropColumn(['proveedor_regimen_fiscal', 'proveedor_uso_cfdi']);
        });

        Schema::table('cotizaciones_compra', function (Blueprint $table) {
            $table->dropColumn(['proveedor_regimen_fiscal', 'proveedor_uso_cfdi']);
        });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropColumn('uso_cfdi');
        });
    }
};
