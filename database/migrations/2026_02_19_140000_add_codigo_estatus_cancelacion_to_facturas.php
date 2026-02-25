<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Código de estatus SAT de cancelación (201, 202, 601, etc.)
     * Documentación: https://apisandbox.facturama.mx/docs
     */
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->string('codigo_estatus_cancelacion', 20)->nullable()->after('acuse_cancelacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('codigo_estatus_cancelacion');
        });
    }
};
