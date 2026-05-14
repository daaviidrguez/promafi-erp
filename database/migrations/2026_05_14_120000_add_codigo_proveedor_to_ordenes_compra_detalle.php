<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_compra_detalle', function (Blueprint $table) {
            $table->string('codigo_proveedor', 100)->nullable()->after('codigo');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_compra_detalle', function (Blueprint $table) {
            $table->dropColumn('codigo_proveedor');
        });
    }
};
