<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $newPermissions = [
            ['key' => 'catalogos_sat.ver', 'name' => 'Ver catálogos SAT', 'module' => 'Facturación'],
            ['key' => 'complementos.ver', 'name' => 'Ver complementos de pago', 'module' => 'Facturación'],
            ['key' => 'remisiones.ver', 'name' => 'Ver remisiones', 'module' => 'Facturación'],
            ['key' => 'devoluciones.ver', 'name' => 'Ver devoluciones', 'module' => 'Facturación'],
            ['key' => 'notas_credito.ver', 'name' => 'Ver notas de crédito', 'module' => 'Facturación'],
            ['key' => 'inventario.ver', 'name' => 'Ver inventario', 'module' => 'Catálogos'],
            ['key' => 'categorias.ver', 'name' => 'Ver categorías', 'module' => 'Catálogos'],
            ['key' => 'sugerencias.ver', 'name' => 'Ver sugerencias', 'module' => 'Catálogos'],
            ['key' => 'ordenes_compra.ver', 'name' => 'Ver órdenes de compra', 'module' => 'Compras'],
            ['key' => 'cotizaciones_compra.ver', 'name' => 'Ver cotizaciones de compra', 'module' => 'Compras'],
            ['key' => 'proveedores.ver', 'name' => 'Ver proveedores', 'module' => 'Compras'],
            ['key' => 'cuentas_por_pagar.ver', 'name' => 'Ver cuentas por pagar', 'module' => 'Compras'],
            ['key' => 'estado_cuenta.ver', 'name' => 'Ver estado de cuenta', 'module' => 'Finanzas'],
            ['key' => 'cuentas_cobrar.ver', 'name' => 'Ver cuentas por cobrar', 'module' => 'Finanzas'],
        ];

        foreach ($newPermissions as $p) {
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
        $newIds = DB::table('permissions')->whereIn('key', array_column($newPermissions, 'key'))->pluck('id');
        foreach ($newIds as $pid) {
            if (!DB::table('permission_role')->where('role_id', $adminRoleId)->where('permission_id', $pid)->exists()) {
                DB::table('permission_role')->insert(['role_id' => $adminRoleId, 'permission_id' => $pid]);
            }
        }
    }

    public function down(): void
    {
        $keys = [
            'catalogos_sat.ver', 'complementos.ver', 'remisiones.ver', 'devoluciones.ver',
            'notas_credito.ver', 'inventario.ver', 'categorias.ver', 'sugerencias.ver',
            'ordenes_compra.ver', 'cotizaciones_compra.ver', 'proveedores.ver',
            'cuentas_por_pagar.ver', 'estado_cuenta.ver', 'cuentas_cobrar.ver',
        ];
        $ids = DB::table('permissions')->whereIn('key', $keys)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('key', $keys)->delete();
    }
};
