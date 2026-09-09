<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factura_cancelaciones_administrativas', function (Blueprint $table) {
            if (! Schema::hasColumn('factura_cancelaciones_administrativas', 'tipo')) {
                $table->string('tipo', 20)->default('cancelacion')->after('factura_id');
                $table->index(['factura_id', 'tipo'], 'fca_factura_tipo_idx');
            }
        });

        if (! DB::table('permissions')->where('key', 'cancelaciones_administrativas.revertir')->exists()) {
            $now = now();
            $id = DB::table('permissions')->insertGetId([
                'key' => 'cancelaciones_administrativas.revertir',
                'name' => 'Reactivar cancelación administrativa (facturas)',
                'module' => 'Facturación',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
            if ($adminRoleId) {
                DB::table('permission_role')->insert(['role_id' => $adminRoleId, 'permission_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        $pid = DB::table('permissions')->where('key', 'cancelaciones_administrativas.revertir')->value('id');
        if ($pid) {
            DB::table('permission_role')->where('permission_id', $pid)->delete();
            DB::table('permissions')->where('id', $pid)->delete();
        }

        Schema::table('factura_cancelaciones_administrativas', function (Blueprint $table) {
            if (Schema::hasColumn('factura_cancelaciones_administrativas', 'tipo')) {
                $table->dropIndex('fca_factura_tipo_idx');
                $table->dropColumn('tipo');
            }
        });
    }
};
