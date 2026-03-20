<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Referencia comercial / URL solo para uso interno (no en PDF ni al cliente).
     */
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->string('referencia_comercial', 255)->nullable()->after('observaciones');
            $table->string('referencia_url', 2048)->nullable()->after('referencia_comercial');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn(['referencia_comercial', 'referencia_url']);
        });
    }
};
