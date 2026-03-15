<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Campos del timbre fiscal para mostrar en PDF: QR, sellos y cadena original (igual que factura).
     */
    public function up(): void
    {
        Schema::table('complementos_pago', function (Blueprint $table) {
            $table->string('no_certificado_sat', 20)->nullable()->after('fecha_timbrado');
            $table->text('sello_cfdi')->nullable()->after('no_certificado_sat');
            $table->text('sello_sat')->nullable()->after('sello_cfdi');
            $table->text('cadena_original')->nullable()->after('sello_sat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('complementos_pago', function (Blueprint $table) {
            $table->dropColumn(['no_certificado_sat', 'sello_cfdi', 'sello_sat', 'cadena_original']);
        });
    }
};
