<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_credito_detalle', function (Blueprint $table) {
            $table->foreignId('factura_detalle_id')->nullable()->after('nota_credito_id')->constrained('facturas_detalle')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notas_credito_detalle', function (Blueprint $table) {
            $table->dropConstrainedForeignId('factura_detalle_id');
        });
    }
};
