<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Integra permisos completos para todos los módulos del ERP.
 * Añade permisos faltantes (crear, editar, eliminar, acciones específicas)
 * y asigna todos al rol admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            // Principal / Reportes
            ['key' => 'reportes.ver', 'name' => 'Ver reportes', 'module' => 'Principal'],

            // Cotizaciones
            ['key' => 'cotizaciones.eliminar', 'name' => 'Eliminar cotizaciones', 'module' => 'Cotizaciones'],

            // Facturas
            ['key' => 'facturas.editar', 'name' => 'Editar facturas', 'module' => 'Facturas'],
            ['key' => 'facturas.cancelar', 'name' => 'Cancelar facturas', 'module' => 'Facturas'],

            // Productos
            ['key' => 'productos.eliminar', 'name' => 'Eliminar productos', 'module' => 'Productos'],

            // Complementos de pago
            ['key' => 'complementos.crear', 'name' => 'Crear complementos de pago', 'module' => 'Facturación'],
            ['key' => 'complementos.editar', 'name' => 'Editar complementos de pago', 'module' => 'Facturación'],
            ['key' => 'complementos.timbrar', 'name' => 'Timbrar complementos de pago', 'module' => 'Facturación'],

            // Remisiones
            ['key' => 'remisiones.crear', 'name' => 'Crear remisiones', 'module' => 'Facturación'],
            ['key' => 'remisiones.editar', 'name' => 'Editar remisiones', 'module' => 'Facturación'],
            ['key' => 'remisiones.eliminar', 'name' => 'Eliminar remisiones', 'module' => 'Facturación'],

            // Devoluciones
            ['key' => 'devoluciones.crear', 'name' => 'Crear devoluciones', 'module' => 'Facturación'],
            ['key' => 'devoluciones.autorizar', 'name' => 'Autorizar devoluciones', 'module' => 'Facturación'],

            // Notas de crédito
            ['key' => 'notas_credito.crear', 'name' => 'Crear notas de crédito', 'module' => 'Facturación'],
            ['key' => 'notas_credito.editar', 'name' => 'Editar notas de crédito', 'module' => 'Facturación'],
            ['key' => 'notas_credito.timbrar', 'name' => 'Timbrar notas de crédito', 'module' => 'Facturación'],

            // Catálogos SAT
            ['key' => 'catalogos_sat.editar', 'name' => 'Editar catálogos SAT', 'module' => 'Facturación'],

            // Categorías
            ['key' => 'categorias.crear', 'name' => 'Crear categorías', 'module' => 'Catálogos'],
            ['key' => 'categorias.editar', 'name' => 'Editar categorías', 'module' => 'Catálogos'],
            ['key' => 'categorias.eliminar', 'name' => 'Eliminar categorías', 'module' => 'Catálogos'],

            // Inventario
            ['key' => 'inventario.crear', 'name' => 'Crear movimientos de inventario', 'module' => 'Catálogos'],
            ['key' => 'inventario.editar', 'name' => 'Editar inventario', 'module' => 'Catálogos'],

            // Sugerencias
            ['key' => 'sugerencias.crear', 'name' => 'Crear sugerencias', 'module' => 'Catálogos'],
            ['key' => 'sugerencias.editar', 'name' => 'Editar sugerencias', 'module' => 'Catálogos'],
            ['key' => 'sugerencias.eliminar', 'name' => 'Eliminar sugerencias', 'module' => 'Catálogos'],

            // Órdenes de compra
            ['key' => 'ordenes_compra.crear', 'name' => 'Crear órdenes de compra', 'module' => 'Compras'],
            ['key' => 'ordenes_compra.editar', 'name' => 'Editar órdenes de compra', 'module' => 'Compras'],
            ['key' => 'ordenes_compra.eliminar', 'name' => 'Eliminar órdenes de compra', 'module' => 'Compras'],

            // Cotizaciones de compra
            ['key' => 'cotizaciones_compra.crear', 'name' => 'Crear cotizaciones de compra', 'module' => 'Compras'],
            ['key' => 'cotizaciones_compra.editar', 'name' => 'Editar cotizaciones de compra', 'module' => 'Compras'],

            // Compras (facturas de compra)
            ['key' => 'compras.ver', 'name' => 'Ver compras', 'module' => 'Compras'],
            ['key' => 'compras.crear', 'name' => 'Crear compras', 'module' => 'Compras'],

            // Proveedores
            ['key' => 'proveedores.crear', 'name' => 'Crear proveedores', 'module' => 'Compras'],
            ['key' => 'proveedores.editar', 'name' => 'Editar proveedores', 'module' => 'Compras'],
            ['key' => 'proveedores.eliminar', 'name' => 'Eliminar proveedores', 'module' => 'Compras'],

            // Cuentas por pagar
            ['key' => 'cuentas_por_pagar.registrar_pago', 'name' => 'Registrar pagos (cuentas por pagar)', 'module' => 'Compras'],

            // Usuarios
            ['key' => 'usuarios.eliminar', 'name' => 'Eliminar usuarios', 'module' => 'Sistema'],
        ];

        foreach ($permissions as $p) {
            if (DB::table('permissions')->where('key', $p['key'])->exists()) {
                continue;
            }
            DB::table('permissions')->insert([
                'key' => $p['key'],
                'name' => $p['name'],
                'module' => $p['module'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if (!$adminRoleId) {
            return;
        }

        $newKeys = array_column($permissions, 'key');
        $newIds = DB::table('permissions')->whereIn('key', $newKeys)->pluck('id');
        foreach ($newIds as $pid) {
            if (!DB::table('permission_role')->where('role_id', $adminRoleId)->where('permission_id', $pid)->exists()) {
                DB::table('permission_role')->insert(['role_id' => $adminRoleId, 'permission_id' => $pid]);
            }
        }
    }

    public function down(): void
    {
        $keys = [
            'reportes.ver', 'cotizaciones.eliminar', 'facturas.editar', 'facturas.cancelar', 'productos.eliminar',
            'complementos.crear', 'complementos.editar', 'complementos.timbrar',
            'remisiones.crear', 'remisiones.editar', 'remisiones.eliminar',
            'devoluciones.crear', 'devoluciones.autorizar',
            'notas_credito.crear', 'notas_credito.editar', 'notas_credito.timbrar',
            'catalogos_sat.editar',
            'categorias.crear', 'categorias.editar', 'categorias.eliminar',
            'inventario.crear', 'inventario.editar',
            'sugerencias.crear', 'sugerencias.editar', 'sugerencias.eliminar',
            'ordenes_compra.crear', 'ordenes_compra.editar', 'ordenes_compra.eliminar',
            'cotizaciones_compra.crear', 'cotizaciones_compra.editar',
            'compras.ver', 'compras.crear',
            'proveedores.crear', 'proveedores.editar', 'proveedores.eliminar',
            'cuentas_por_pagar.registrar_pago',
            'usuarios.eliminar',
        ];
        $ids = DB::table('permissions')->whereIn('key', $keys)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
