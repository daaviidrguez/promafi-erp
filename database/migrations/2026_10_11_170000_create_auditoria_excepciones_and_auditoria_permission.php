<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('auditoria_excepciones')) {
            Schema::create('auditoria_excepciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('excepcion', 255);
                $table->string('mensaje', 1000)->nullable();
                $table->string('archivo', 255)->nullable();
                $table->string('metodo', 10)->nullable();
                $table->string('ruta', 255)->nullable();
                $table->string('ruta_nombre', 150)->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
            });
        }

        if (! DB::table('permissions')->where('key', 'auditoria.ver')->exists()) {
            $now = now();
            $id = DB::table('permissions')->insertGetId([
                'key' => 'auditoria.ver',
                'name' => 'Ver auditoría (rendimiento y errores del sistema)',
                'module' => 'Sistema',
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
        $pid = DB::table('permissions')->where('key', 'auditoria.ver')->value('id');
        if ($pid) {
            DB::table('permission_role')->where('permission_id', $pid)->delete();
            DB::table('permissions')->where('id', $pid)->delete();
        }

        Schema::dropIfExists('auditoria_excepciones');
    }
};
