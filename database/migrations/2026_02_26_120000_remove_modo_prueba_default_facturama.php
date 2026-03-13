<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Elimina el modo prueba (UUID fake). Solo sandbox y producción Facturama.
 * - Actualiza pac_provider de 'fake' a 'facturama_sandbox' en empresas existentes
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('empresas', 'pac_provider')) {
            DB::table('empresas')
                ->where(function ($q) {
                    $q->where('pac_provider', 'fake')->orWhereNull('pac_provider');
                })
                ->update(['pac_provider' => 'facturama_sandbox']);
        }
    }

    public function down(): void
    {
        // No revertir — las empresas quedarían con facturama_sandbox
    }
};
