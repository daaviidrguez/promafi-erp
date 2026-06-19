<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table) {
            $table->foreignId('entrada_anticipada_id')
                ->nullable()
                ->after('factura_compra_id')
                ->constrained('entradas_anticipadas')
                ->nullOnDelete();
        });

        Schema::table('facturas_compra', function (Blueprint $table) {
            $table->foreignId('entrada_anticipada_id')
                ->nullable()
                ->after('orden_compra_id')
                ->constrained('entradas_anticipadas')
                ->nullOnDelete();
            $table->string('origen', 30)->default('directa')->after('estado');
        });

        DB::statement("ALTER TABLE ordenes_compra MODIFY COLUMN estado ENUM('borrador', 'aceptada', 'recibida', 'cancelada', 'convertida_compra', 'en_recepcion') DEFAULT 'borrador'");
    }

    public function down(): void
    {
        DB::table('ordenes_compra')->where('estado', 'en_recepcion')->update(['estado' => 'aceptada']);

        DB::statement("ALTER TABLE ordenes_compra MODIFY COLUMN estado ENUM('borrador', 'aceptada', 'recibida', 'cancelada', 'convertida_compra') DEFAULT 'borrador'");

        Schema::table('facturas_compra', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entrada_anticipada_id');
            $table->dropColumn('origen');
        });

        Schema::table('inventario_movimientos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entrada_anticipada_id');
        });
    }
};
