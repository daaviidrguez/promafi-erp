<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cotizaciones_detalle', function (Blueprint $table) {
            $table->string('tiempo_entrega', 120)->nullable()->after('imagenes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones_detalle', function (Blueprint $table) {
            $table->dropColumn('tiempo_entrega');
        });
    }
};
