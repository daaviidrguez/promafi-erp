<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Objeto del impuesto por concepto (01, 02, 03) para CFDI.
     */
    public function up(): void
    {
        Schema::table('facturas_detalle', function (Blueprint $table) {
            $table->string('objeto_impuesto', 2)->default('02')->after('base_impuesto');
        });
    }

    public function down(): void
    {
        Schema::table('facturas_detalle', function (Blueprint $table) {
            $table->dropColumn('objeto_impuesto');
        });
    }
};
