<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remisiones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_compra_id');
            $table->string('orden_compra', 200)->nullable()->after('factura_id_cancelada');
        });

        Schema::table('facturas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_compra_id');
            $table->string('orden_compra', 200)->nullable()->after('cotizacion_id');
        });
    }

    public function down(): void
    {
        // Volver a FK (sin recrear datos)
        Schema::table('remisiones', function (Blueprint $table) {
            $table->dropColumn('orden_compra');
            $table->foreignId('orden_compra_id')
                ->nullable()
                ->after('factura_id_cancelada')
                ->constrained('ordenes_compra')
                ->nullOnDelete();
        });

        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('orden_compra');
            $table->foreignId('orden_compra_id')
                ->nullable()
                ->after('cotizacion_id')
                ->constrained('ordenes_compra')
                ->nullOnDelete();
        });
    }
};

