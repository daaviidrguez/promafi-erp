<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE facturas_compra MODIFY COLUMN estado ENUM('borrador', 'registrada', 'recibida', 'cancelada') DEFAULT 'registrada'");
        Schema::table('facturas_compra', function (Blueprint $table) {
            $table->timestamp('fecha_recepcion')->nullable()->after('fecha_emision');
        });
    }

    public function down(): void
    {
        Schema::table('facturas_compra', function (Blueprint $table) {
            $table->dropColumn('fecha_recepcion');
        });
        DB::statement("ALTER TABLE facturas_compra MODIFY COLUMN estado ENUM('borrador', 'registrada', 'cancelada') DEFAULT 'registrada'");
    }
};
