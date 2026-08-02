<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            ['key' => 'catalogo_truper.ver', 'name' => 'Ver catálogo Truper', 'module' => 'Catálogos'],
            ['key' => 'catalogo_truper.importar', 'name' => 'Importar catálogo Truper', 'module' => 'Catálogos'],
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
            'catalogo_truper.ver',
            'catalogo_truper.importar',
        ];
        $ids = DB::table('permissions')->whereIn('key', $keys)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('key', $keys)->delete();
    }
};
