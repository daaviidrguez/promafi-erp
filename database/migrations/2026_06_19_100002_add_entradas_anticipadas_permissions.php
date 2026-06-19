<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            ['key' => 'entradas_anticipadas.ver', 'name' => 'Ver entradas anticipadas', 'module' => 'Compras'],
            ['key' => 'entradas_anticipadas.crear', 'name' => 'Crear entradas anticipadas', 'module' => 'Compras'],
            ['key' => 'entradas_anticipadas.editar', 'name' => 'Editar entradas anticipadas', 'module' => 'Compras'],
            ['key' => 'entradas_anticipadas.facturar', 'name' => 'Facturar entradas anticipadas', 'module' => 'Compras'],
        ];

        $ids = [];
        foreach ($permissions as $p) {
            $existing = DB::table('permissions')->where('key', $p['key'])->first();
            if ($existing) {
                $ids[] = $existing->id;

                continue;
            }
            $ids[] = DB::table('permissions')->insertGetId([
                'key' => $p['key'],
                'name' => $p['name'],
                'module' => $p['module'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        if ($adminRole) {
            foreach ($ids as $permissionId) {
                if (! DB::table('permission_role')->where('role_id', $adminRole->id)->where('permission_id', $permissionId)->exists()) {
                    DB::table('permission_role')->insert([
                        'role_id' => $adminRole->id,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $keys = [
            'entradas_anticipadas.ver',
            'entradas_anticipadas.crear',
            'entradas_anticipadas.editar',
            'entradas_anticipadas.facturar',
        ];
        $ids = DB::table('permissions')->whereIn('key', $keys)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('key', $keys)->delete();
    }
};
