<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permission = [
            'key' => 'cotizaciones.adjuntos',
            'name' => 'Gestionar documentos de respaldo en cotizaciones',
            'module' => 'Cotizaciones',
        ];

        $existing = DB::table('permissions')->where('key', $permission['key'])->first();
        if ($existing) {
            $permissionId = $existing->id;
        } else {
            $permissionId = DB::table('permissions')->insertGetId([
                'key' => $permission['key'],
                'name' => $permission['name'],
                'module' => $permission['module'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Admin siempre
        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        if ($adminRole && ! DB::table('permission_role')
            ->where('role_id', $adminRole->id)
            ->where('permission_id', $permissionId)
            ->exists()) {
            DB::table('permission_role')->insert([
                'role_id' => $adminRole->id,
                'permission_id' => $permissionId,
            ]);
        }

        // Roles que ya crean/editan cotizaciones (p. ej. vendedor)
        $roleIds = DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->whereIn('permissions.key', ['cotizaciones.crear', 'cotizaciones.editar'])
            ->distinct()
            ->pluck('permission_role.role_id');

        foreach ($roleIds as $roleId) {
            if (! DB::table('permission_role')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->exists()) {
                DB::table('permission_role')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('key', 'cotizaciones.adjuntos')->value('id');
        if ($id) {
            DB::table('permission_role')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
