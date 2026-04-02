<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistica_envio_items', function (Blueprint $table) {
            $table->boolean('linea_entregada')->default(false)->after('cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('logistica_envio_items', function (Blueprint $table) {
            $table->dropColumn('linea_entregada');
        });
    }
};
