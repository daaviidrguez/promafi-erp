<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones_detalle', function (Blueprint $table) {
            $table->decimal('utilidad', 5, 2)->nullable()->after('precio_unitario');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones_detalle', function (Blueprint $table) {
            $table->dropColumn('utilidad');
        });
    }
};
