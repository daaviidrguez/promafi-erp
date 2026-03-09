<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina datos transaccionales de la BD.
 * Mantiene: empresas, clientes, cliente_contactos, categorias_productos, productos,
 * proveedores, users, roles, permissions, permission_role, catálogos SAT.
 */
class LimpiarDatosTransaccionalesCommand extends Command
{
    protected $signature = 'db:limpiar-transaccionales
                            {--force : Ejecutar sin pedir confirmación}';

    protected $description = 'Elimina facturas, cotizaciones, complementos, cuentas por cobrar/pagar, órdenes de compra, etc. Mantiene configuración, clientes, productos, proveedores, usuarios y roles.';

    /** Tablas a vaciar, en orden para respetar FKs (hijos antes que padres). */
    private array $tablasALimpiar = [
        'documentos_relacionados_pago',
        'pagos_recibidos',
        'complementos_pago',
        'cuentas_por_cobrar',
        'notas_credito_impuestos',
        'notas_credito_detalle',
        'notas_credito',
        'devolucion_detalle',
        'devoluciones',
        'facturas_impuestos',
        'facturas_detalle',
        'facturas',
        'listas_precios_detalle',
        'listas_precios',
        'cotizaciones_detalle',
        'cotizaciones',
        'remisiones_detalle',
        'remisiones',
        'ordenes_compra_detalle',
        'ordenes_compra',
        'cuentas_por_pagar',
        'cotizaciones_compra_detalle',
        'cotizaciones_compra',
        'inventario_movimientos',
        'sugerencias',
        'sessions',
        'cache',
        'cache_locks',
        'job_batches',
        'jobs',
        'failed_jobs',
        'password_reset_tokens',
        'personal_access_tokens',
    ];

    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('¿Eliminar todos los datos transaccionales? Se mantendrán: configuración (empresa), clientes, productos, proveedores, usuarios y roles.')) {
            $this->info('Operación cancelada.');
            return self::SUCCESS;
        }

        $driver = DB::getDriverName();
        $this->info("Motor de BD: {$driver}");

        Schema::disableForeignKeyConstraints();

        $limpiadas = 0;
        foreach ($this->tablasALimpiar as $tabla) {
            if (!Schema::hasTable($tabla)) {
                continue;
            }
            try {
                DB::table($tabla)->truncate();
                $this->line("  ✓ {$tabla}");
                $limpiadas++;
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$tabla}: " . $e->getMessage());
            }
        }

        Schema::enableForeignKeyConstraints();

        // Saldo en clientes se calcula desde cuentas_por_cobrar; al vaciarlas hay que poner saldo_actual en 0
        if (Schema::hasTable('clientes') && Schema::hasColumn('clientes', 'saldo_actual')) {
            DB::table('clientes')->update(['saldo_actual' => 0]);
            $this->line('  ✓ clientes.saldo_actual puesto a 0');
        }

        // Limpiar PDFs y XMLs de documentos transaccionales en storage
        $dirsStorage = [
            'documentos',
            'notas-credito',
            'facturas',
            'complementos',
        ];
        $baseDir = storage_path('app');
        foreach ($dirsStorage as $dir) {
            $path = $baseDir . '/' . $dir;
            if (File::isDirectory($path)) {
                File::cleanDirectory($path);
                $this->line("  ✓ storage/app/{$dir}/ vaciado");
            }
        }

        $this->newLine();
        $this->info("Listo. Se vaciaron {$limpiadas} tablas. Saldos de clientes en 0. Documentos (PDF/XML) en storage limpiados. Configuración, clientes, productos, proveedores, usuarios y roles se mantuvieron.");
        return self::SUCCESS;
    }
}
