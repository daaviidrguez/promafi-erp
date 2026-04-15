<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE devoluciones MODIFY COLUMN estado ENUM('borrador', 'autorizada', 'cerrada', 'cancelada') DEFAULT 'borrador'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE devoluciones MODIFY COLUMN estado ENUM('borrador', 'autorizada', 'cerrada') DEFAULT 'borrador'");
    }
};
