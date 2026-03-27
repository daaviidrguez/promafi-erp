<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remisiones', function (Blueprint $table) {
            $table->foreignId('orden_compra_id')
                ->nullable()
                ->after('factura_id_cancelada')
                ->constrained('ordenes_compra')
                ->nullOnDelete();

            $table->index('orden_compra_id');
        });

        Schema::table('facturas', function (Blueprint $table) {
            $table->foreignId('orden_compra_id')
                ->nullable()
                ->after('cotizacion_id')
                ->constrained('ordenes_compra')
                ->nullOnDelete();

            $table->index('orden_compra_id');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_compra_id');
        });

        Schema::table('remisiones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_compra_id');
        });
    }
};

