<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('catalogo_online_visible')->default(false)->after('imagenes');
            $table->boolean('catalogo_online_mostrar_precio')->default(true)->after('catalogo_online_visible');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['catalogo_online_visible', 'catalogo_online_mostrar_precio']);
        });
    }
};
