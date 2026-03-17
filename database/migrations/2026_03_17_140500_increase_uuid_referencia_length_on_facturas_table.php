<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aumentar longitud de uuid_referencia para soportar múltiples UUID (Relación de CFDI).
     */
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            // De VARCHAR(36) a VARCHAR(500) para permitir varios UUID separados por coma
            $table->string('uuid_referencia', 500)->nullable()->change();
        });
    }

    /**
     * Revertir longitud a 36 caracteres (puede truncar datos si hay múltiples UUID).
     */
    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->string('uuid_referencia', 36)->nullable()->change();
        });
    }
};

