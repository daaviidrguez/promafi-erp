<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ordenes_compra MODIFY COLUMN estado ENUM('borrador', 'aceptada', 'recibida', 'cancelada', 'convertida_compra') DEFAULT 'borrador'");
    }

    public function down(): void
    {
        DB::table('ordenes_compra')->where('estado', 'convertida_compra')->update(['estado' => 'recibida']);
        DB::statement("ALTER TABLE ordenes_compra MODIFY COLUMN estado ENUM('borrador', 'aceptada', 'recibida', 'cancelada') DEFAULT 'borrador'");
    }
};
