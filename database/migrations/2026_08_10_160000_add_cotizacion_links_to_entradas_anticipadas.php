<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entradas_anticipadas', function (Blueprint $table) {
            if (! Schema::hasColumn('entradas_anticipadas', 'cotizacion_id')) {
                $table->foreignId('cotizacion_id')
                    ->nullable()
                    ->after('orden_compra_id')
                    ->constrained('cotizaciones')
                    ->nullOnDelete();
                $table->index('cotizacion_id');
            }
        });

        Schema::table('entradas_anticipadas_detalle', function (Blueprint $table) {
            if (! Schema::hasColumn('entradas_anticipadas_detalle', 'cotizacion_detalle_id')) {
                $table->foreignId('cotizacion_detalle_id')
                    ->nullable()
                    ->after('orden_compra_detalle_id')
                    ->constrained('cotizaciones_detalle')
                    ->nullOnDelete();
                $table->index('cotizacion_detalle_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entradas_anticipadas_detalle', function (Blueprint $table) {
            if (Schema::hasColumn('entradas_anticipadas_detalle', 'cotizacion_detalle_id')) {
                $table->dropConstrainedForeignId('cotizacion_detalle_id');
            }
        });

        Schema::table('entradas_anticipadas', function (Blueprint $table) {
            if (Schema::hasColumn('entradas_anticipadas', 'cotizacion_id')) {
                $table->dropConstrainedForeignId('cotizacion_id');
            }
        });
    }
};
