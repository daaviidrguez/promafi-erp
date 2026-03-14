<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Relación de CFDI: sustitución (tipo 04) cuando la factura reemplaza un CFDI emitido con errores.
     */
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->string('uuid_referencia', 36)->nullable()->after('observaciones');
            $table->string('tipo_relacion', 2)->nullable()->after('uuid_referencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['uuid_referencia', 'tipo_relacion']);
        });
    }
};
