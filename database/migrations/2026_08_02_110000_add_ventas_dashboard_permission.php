<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $key = 'ventas.dashboard';

        if (DB::table('permissions')->where('key', $key)->exists()) {
            return;
        }

        $id = DB::table('permissions')->insertGetId([
            'key' => $key,
            'name' => 'Dashboard de ventas',
            'module' => 'Ventas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if ($adminRoleId) {
            DB::table('permission_role')->insert([
                'role_id' => $adminRoleId,
                'permission_id' => $id,
            ]);
        }

        // Roles con reportes o facturas también lo reciben
        $roleIds = DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->whereIn('permissions.key', ['reportes.ver', 'facturas.ver', 'dashboard.ver'])
            ->distinct()
            ->pluck('permission_role.role_id');

        foreach ($roleIds as $roleId) {
            if (! DB::table('permission_role')
                ->where('role_id', $roleId)
                ->where('permission_id', $id)
                ->exists()) {
                DB::table('permission_role')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $id,
                ]);
            }
        }
    }

    public function down(): void
    {
        $pid = DB::table('permissions')->where('key', 'ventas.dashboard')->value('id');
        if ($pid) {
            DB::table('permission_role')->where('permission_id', $pid)->delete();
            DB::table('permissions')->where('id', $pid)->delete();
        }
    }
};
