<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $id = DB::table('permissions')->insertGetId([
            'key' => 'importador_cfdi.ver',
            'name' => 'Ver y usar Importador CFDI',
            'module' => 'Sistema',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if ($adminRoleId) {
            DB::table('permission_role')->insert(['role_id' => $adminRoleId, 'permission_id' => $id]);
        }
    }

    public function down(): void
    {
        $pid = DB::table('permissions')->where('key', 'importador_cfdi.ver')->value('id');
        if ($pid) {
            DB::table('permission_role')->where('permission_id', $pid)->delete();
            DB::table('permissions')->where('id', $pid)->delete();
        }
    }
};
