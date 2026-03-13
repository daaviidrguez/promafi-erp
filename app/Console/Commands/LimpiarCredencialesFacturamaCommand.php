<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Limpia solo las credenciales de Facturama en la tabla empresas.
 * Útil para quitar valores corruptos sin afectar el resto de la configuración.
 */
class LimpiarCredencialesFacturamaCommand extends Command
{
    protected $signature = 'db:limpiar-credenciales-facturama
                            {--force : Ejecutar sin pedir confirmación}';

    protected $description = 'Limpia credenciales Facturama (sandbox y producción) en empresas. No afecta certificados ni otros datos.';

    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('¿Limpiar credenciales Facturama? Deberás volver a configurarlas en Configuración de empresa.')) {
            $this->info('Operación cancelada.');
            return self::SUCCESS;
        }

        if (!Schema::hasTable('empresas')) {
            $this->warn('La tabla empresas no existe.');
            return self::SUCCESS;
        }

        $cols = [
            'pac_facturama_user',
            'pac_facturama_password',
            'pac_facturama_user_sandbox',
            'pac_facturama_password_sandbox',
            'pac_facturama_user_production',
            'pac_facturama_password_production',
        ];

        $update = [];
        foreach ($cols as $col) {
            if (Schema::hasColumn('empresas', $col)) {
                $update[$col] = null;
            }
        }

        if (empty($update)) {
            $this->warn('No se encontraron columnas de credenciales Facturama.');
            return self::SUCCESS;
        }

        DB::table('empresas')->update($update);
        $this->info('✓ Credenciales Facturama limpiadas. Configura usuario y contraseña en Configuración de empresa.');

        return self::SUCCESS;
    }
}
