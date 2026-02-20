<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos SAT para objeto del impuesto, tipo de impuesto, tipo factor y tasa.
     * Objeto: 01 No objeto, 02 Sí objeto, 03 Sí objeto y no obligado al desglose.
     * Tipo factor: Tasa | Exento. Tasa: 0.160000, 0.080000, 0.000000.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('objeto_impuesto', 2)->default('02')->after('unidad'); // 01, 02, 03
            $table->string('tipo_impuesto', 3)->default('002')->after('objeto_impuesto'); // 002 = IVA
            $table->string('tipo_factor', 10)->default('Tasa')->after('tipo_impuesto'); // Tasa | Exento
            // tasa_iva ya existe; se usa con valores 0.160000, 0.080000, 0.000000
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['objeto_impuesto', 'tipo_impuesto', 'tipo_factor']);
        });
    }
};
