<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Elimina datos transaccionales de la BD.
 * Mantiene: empresas, clientes, cliente_contactos, categorias_productos, productos,
 * proveedores, producto_proveedores, clientes_direcciones_entrega, users, roles,
 * permissions, permission_role, catálogos SAT, isr_resico_tasas.
 *
 * Además reinicia en productos los flags de revisión de precios (post-compra CFDI).
 */
class LimpiarDatosTransaccionalesCommand extends Command
{
    protected $signature = 'db:limpiar-transaccionales
                            {--force : Ejecutar sin pedir confirmación}';

    protected $description = 'Elimina facturas, cotizaciones, complementos, cuentas por cobrar/pagar, órdenes de compra, etc. Mantiene configuración, clientes, productos, proveedores, usuarios y roles.';

    /**
     * Tablas a vaciar, en orden para respetar FKs (hijos antes que padres).
     * Mantiene: empresas, clientes, cliente_contactos, productos, categorias_productos,
     * proveedores, users, roles, permissions, permission_role, catálogos SAT, isr_resico_tasas.
     */
    private array $tablasALimpiar = [
        // Complementos de pago
        'documentos_relacionados_pago',
        'pagos_recibidos',
        'complementos_pago',
        // Cuentas y facturación
        'cuentas_por_cobrar',
        'notas_credito_impuestos',
        'notas_credito_detalle',
        'notas_credito',
        'devolucion_detalle',
        'devoluciones',
        'facturas_impuestos',
        'facturas_detalle',
        'factura_cancelaciones_administrativas',
        'facturas',
        // Listas de precios y cotizaciones
        'listas_precios_detalle',
        'listas_precios',
        'cotizaciones_detalle',
        'cotizaciones',
        // Remisiones y compras
        'remisiones_detalle',
        'remisiones',
        'ordenes_compra_detalle',
        'ordenes_compra',
        'cuentas_por_pagar',
        'cotizaciones_compra_detalle',
        'cotizaciones_compra',
        // Inventario y sugerencias
        'inventario_movimientos',
        // Logística (hijos antes que logistica_envios)
        'logistica_envio_historial',
        'logistica_envio_items',
        'logistica_envios',
        // Facturas de compra (módulo Compras)
        'facturas_compra_impuestos',
        'facturas_compra_detalle',
        'facturas_compra',
        'sugerencias',
        // Sistema (sessions, cache, jobs)
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
        if (! $this->option('force') && ! $this->confirm('¿Eliminar todos los datos transaccionales, credenciales Facturama y certificados? Se mantendrán: datos fiscales empresa, clientes, productos, proveedores, usuarios y roles.')) {
            $this->info('Operación cancelada.');

            return self::SUCCESS;
        }

        $driver = DB::getDriverName();
        $this->info("Motor de BD: {$driver}");

        Schema::disableForeignKeyConstraints();

        $limpiadas = 0;
        foreach ($this->tablasALimpiar as $tabla) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            try {
                DB::table($tabla)->truncate();
                $this->line("  ✓ {$tabla}");
                $limpiadas++;
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$tabla}: ".$e->getMessage());
            }
        }

        Schema::enableForeignKeyConstraints();

        // Saldo en clientes se calcula desde cuentas_por_cobrar; al vaciarlas hay que poner saldo_actual en 0
        if (Schema::hasTable('clientes') && Schema::hasColumn('clientes', 'saldo_actual')) {
            DB::table('clientes')->update(['saldo_actual' => 0]);
            $this->line('  ✓ clientes.saldo_actual puesto a 0');
        }

        // Tras borrar compras / movimientos, los avisos de revisión de precio ya no aplican
        if (Schema::hasTable('productos')) {
            $resetProductos = [];
            if (Schema::hasColumn('productos', 'requiere_revision_precio')) {
                $resetProductos['requiere_revision_precio'] = false;
            }
            if (Schema::hasColumn('productos', 'ultimo_costo')) {
                $resetProductos['ultimo_costo'] = null;
            }
            if ($resetProductos !== []) {
                DB::table('productos')->update($resetProductos);
                $this->line('  ✓ productos: revisión de precios (flags) reiniciados');
            }
        }

        // Resetear folios, series por defecto, credenciales Facturama y certificados en empresas
        if (Schema::hasTable('empresas')) {
            $folios = ['folio_factura', 'folio_factura_credito', 'folio_nota_credito', 'folio_nota_debito', 'folio_complemento', 'folio_cotizacion', 'folio_remision', 'folio_logistica'];
            $update = [];
            foreach ($folios as $col) {
                if (Schema::hasColumn('empresas', $col)) {
                    $update[$col] = 1;
                }
            }
            $seriesDefaults = [
                'serie_factura' => 'A',
                'serie_factura_credito' => 'FB',
                'serie_nota_credito' => 'NC',
                'serie_nota_debito' => 'ND',
                'serie_complemento' => 'CP',
                'serie_cotizacion' => 'COT',
                'serie_remision' => 'REM',
                'serie_logistica' => 'LOG',
            ];
            foreach ($seriesDefaults as $col => $default) {
                if (Schema::hasColumn('empresas', $col)) {
                    $update[$col] = $default;
                }
            }

            // Eliminar archivos de certificados del storage antes de limpiar la BD
            $empresas = DB::table('empresas')->get();
            foreach ($empresas as $e) {
                foreach (['certificado_cer', 'certificado_key'] as $campo) {
                    $path = $e->{$campo} ?? null;
                    if ($path && Storage::disk('local')->exists($path)) {
                        Storage::disk('local')->delete($path);
                    }
                }
            }

            // Vaciar directorio certificados (por si quedó algo)
            $certDir = 'certificados';
            if (Storage::disk('local')->exists($certDir)) {
                $files = Storage::disk('local')->files($certDir);
                foreach ($files as $f) {
                    Storage::disk('local')->delete($f);
                }
            }

            // Resetear credenciales Facturama y certificados en BD
            $colsResetear = [
                'pac_facturama_user', 'pac_facturama_password',
                'pac_facturama_user_sandbox', 'pac_facturama_password_sandbox',
                'pac_facturama_user_production', 'pac_facturama_password_production',
                'certificado_cer', 'certificado_key', 'certificado_password',
                'no_certificado', 'certificado_vigencia',
            ];
            foreach ($colsResetear as $col) {
                if (Schema::hasColumn('empresas', $col)) {
                    $update[$col] = null;
                }
            }

            if (! empty($update)) {
                DB::table('empresas')->update($update);
                $this->line('  ✓ empresas: folios y series reseteados, credenciales Facturama y certificados limpiados');
            }
        }

        // Limpiar PDFs y XMLs de documentos transaccionales en storage
        $dirsStorage = [
            'documentos',
            'notas-credito',
            'facturas',
            'complementos',
            'cuentas-por-pagar',
        ];
        $baseDir = storage_path('app');
        foreach ($dirsStorage as $dir) {
            $path = $baseDir.'/'.$dir;
            if (File::isDirectory($path)) {
                File::cleanDirectory($path);
                $this->line("  ✓ storage/app/{$dir}/ vaciado");
            }
        }

        // Limpiar caché de aplicación (evita credenciales/config obsoletos en memoria)
        Artisan::call('cache:clear');
        $this->line('  ✓ cache:clear');
        Artisan::call('config:clear');
        $this->line('  ✓ config:clear');
        Artisan::call('view:clear');
        $this->line('  ✓ view:clear');
        Artisan::call('route:clear');
        $this->line('  ✓ route:clear');

        $this->newLine();
        $this->info("Listo. Se vaciaron {$limpiadas} tablas. Saldos de clientes en 0. Flags de revisión de precios en productos reiniciados. Folios y series de empresa reseteados. Documentos (PDF/XML) en storage limpiados. Caché, config, vistas y rutas limpiadas. Configuración, clientes, productos, proveedores, usuarios y roles se mantuvieron.");

        return self::SUCCESS;
    }
}
