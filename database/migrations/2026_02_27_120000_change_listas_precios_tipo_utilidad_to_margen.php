<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE listas_precios_detalle MODIFY COLUMN tipo_utilidad ENUM('factorizado', 'porcentual', 'margen') NOT NULL");
        DB::statement("UPDATE listas_precios_detalle SET tipo_utilidad = 'margen', valor_utilidad = 0.70 WHERE tipo_utilidad = 'porcentual'");
        DB::statement("ALTER TABLE listas_precios_detalle MODIFY COLUMN tipo_utilidad ENUM('factorizado', 'margen') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE listas_precios_detalle MODIFY COLUMN tipo_utilidad ENUM('factorizado', 'margen', 'porcentual') NOT NULL");
        DB::statement("UPDATE listas_precios_detalle SET tipo_utilidad = 'porcentual', valor_utilidad = 25 WHERE tipo_utilidad = 'margen'");
        DB::statement("ALTER TABLE listas_precios_detalle MODIFY COLUMN tipo_utilidad ENUM('factorizado', 'porcentual') NOT NULL");
    }
};
