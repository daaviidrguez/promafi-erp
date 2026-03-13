<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ordenes_compra MODIFY COLUMN estado ENUM('borrador', 'aceptada', 'recibida', 'cancelada') DEFAULT 'borrador'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ordenes_compra MODIFY COLUMN estado ENUM('borrador', 'aceptada', 'recibida') DEFAULT 'borrador'");
    }
};
