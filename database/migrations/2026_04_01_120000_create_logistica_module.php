<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('serie_logistica', 20)->default('LOG');
            $table->unsignedInteger('folio_logistica')->default(1);
        });

        Schema::create('logistica_envios', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 32)->unique();
            $table->string('estado', 32)->index(); // pendiente, preparado, enviado, en_ruta, entregado, cancelado
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('factura_id')->nullable()->constrained('facturas')->nullOnDelete();
            $table->foreignId('remision_id')->nullable()->unique()->constrained('remisiones')->nullOnDelete();
            $table->foreignId('cliente_direccion_entrega_id')->nullable()
                ->constrained('clientes_direcciones_entrega')->nullOnDelete();
            $table->text('direccion_entrega')->nullable();
            $table->string('chofer', 200)->nullable();
            $table->string('recibido_almacen', 200)->nullable();
            $table->string('lugar_entrega', 255)->nullable();
            $table->string('entrega_recibido_por', 200)->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });

        Schema::create('logistica_envio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistica_envio_id')->constrained('logistica_envios')->cascadeOnDelete();
            $table->foreignId('factura_detalle_id')->nullable()->constrained('facturas_detalle')->nullOnDelete();
            $table->unsignedBigInteger('remision_detalle_id')->nullable();
            $table->foreign('remision_detalle_id', 'lei_remision_det_fk')
                ->references('id')->on('remisiones_detalle')->nullOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('descripcion', 500)->nullable();
            $table->decimal('cantidad', 14, 4);
            $table->timestamps();
        });

        Schema::create('logistica_envio_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistica_envio_id')->constrained('logistica_envios')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado_anterior', 32)->nullable();
            $table->string('estado_nuevo', 32);
            $table->text('nota')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        $now = now();
        $permissions = [
            ['key' => 'logistica.ver', 'name' => 'Ver logística', 'module' => 'Logística'],
            ['key' => 'logistica.crear', 'name' => 'Crear envíos de logística', 'module' => 'Logística'],
            ['key' => 'logistica.editar', 'name' => 'Editar envíos de logística', 'module' => 'Logística'],
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
        if ($adminRoleId) {
            $ids = DB::table('permissions')->whereIn('key', array_column($permissions, 'key'))->pluck('id');
            foreach ($ids as $pid) {
                if (! DB::table('permission_role')->where('role_id', $adminRoleId)->where('permission_id', $pid)->exists()) {
                    DB::table('permission_role')->insert(['role_id' => $adminRoleId, 'permission_id' => $pid]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logistica_envio_historial');
        Schema::dropIfExists('logistica_envio_items');
        Schema::dropIfExists('logistica_envios');

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['serie_logistica', 'folio_logistica']);
        });

        $keys = ['logistica.ver', 'logistica.crear', 'logistica.editar'];
        $ids = DB::table('permissions')->whereIn('key', $keys)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
