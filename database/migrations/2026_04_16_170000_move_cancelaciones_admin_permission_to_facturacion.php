<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El permiso se agrupa en roles por el campo module; debe coincidir con el menú Facturación.
     */
    public function up(): void
    {
        DB::table('permissions')
            ->where('key', 'cancelaciones_administrativas.administrar')
            ->update([
                'module' => 'Facturación',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('key', 'cancelaciones_administrativas.administrar')
            ->update([
                'module' => 'Sistema',
                'updated_at' => now(),
            ]);
    }
};
