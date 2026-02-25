<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $perms = [
            ['key' => 'listas_precios.ver', 'name' => 'Ver listas de precios', 'module' => 'Facturación'],
            ['key' => 'listas_precios.crear', 'name' => 'Crear listas de precios', 'module' => 'Facturación'],
            ['key' => 'listas_precios.editar', 'name' => 'Editar listas de precios', 'module' => 'Facturación'],
        ];
        foreach ($perms as $p) {
            if (DB::table('permissions')->where('key', $p['key'])->exists()) continue;
            DB::table('permissions')->insert([
                'key' => $p['key'], 'name' => $p['name'], 'module' => $p['module'],
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if ($adminRoleId) {
            $ids = DB::table('permissions')->whereIn('key', array_column($perms, 'key'))->pluck('id');
            foreach ($ids as $pid) {
                if (!DB::table('permission_role')->where('role_id', $adminRoleId)->where('permission_id', $pid)->exists()) {
                    DB::table('permission_role')->insert(['role_id' => $adminRoleId, 'permission_id' => $pid]);
                }
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('key', ['listas_precios.ver', 'listas_precios.crear', 'listas_precios.editar'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
