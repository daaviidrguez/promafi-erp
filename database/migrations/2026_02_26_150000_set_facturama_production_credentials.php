<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Inserta credenciales de producción Facturama directamente en BD.
 * Corrige posibles datos corruptos guardados por el formulario.
 * Las credenciales se leen siempre desde la BD (getFacturamaCredentials).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('empresas')
            ->where('activo', true)
            ->update([
                'pac_provider' => 'facturama_production',
                'pac_facturama_user_production' => 'daaviidrguez',
                'pac_facturama_password_production' => 'Hola1020',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // No revertir
    }
};
