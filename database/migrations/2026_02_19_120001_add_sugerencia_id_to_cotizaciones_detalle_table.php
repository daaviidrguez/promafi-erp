<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones_detalle', function (Blueprint $table) {
            $table->foreignId('sugerencia_id')
                  ->nullable()
                  ->after('producto_id')
                  ->constrained('sugerencias')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones_detalle', function (Blueprint $table) {
            $table->dropForeign(['sugerencia_id']);
        });
    }
};
