<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('display_name', 100);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 120);
            $table->string('module', 60)->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        $now = now();
        DB::table('roles')->insert([
            ['name' => 'admin', 'display_name' => 'Administrador', 'description' => 'Acceso total al sistema', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'vendedor', 'display_name' => 'Vendedor', 'description' => 'Ventas, cotizaciones y clientes', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'contador', 'display_name' => 'Contador', 'description' => 'Facturación y cuentas', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'usuario', 'display_name' => 'Usuario', 'description' => 'Acceso básico', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $permissions = [
            ['key' => 'dashboard.ver', 'name' => 'Ver dashboard', 'module' => 'Principal'],
            ['key' => 'clientes.ver', 'name' => 'Ver clientes', 'module' => 'Clientes'],
            ['key' => 'clientes.crear', 'name' => 'Crear clientes', 'module' => 'Clientes'],
            ['key' => 'clientes.editar', 'name' => 'Editar clientes', 'module' => 'Clientes'],
            ['key' => 'clientes.eliminar', 'name' => 'Eliminar clientes', 'module' => 'Clientes'],
            ['key' => 'cotizaciones.ver', 'name' => 'Ver cotizaciones', 'module' => 'Cotizaciones'],
            ['key' => 'cotizaciones.crear', 'name' => 'Crear cotizaciones', 'module' => 'Cotizaciones'],
            ['key' => 'cotizaciones.editar', 'name' => 'Editar cotizaciones', 'module' => 'Cotizaciones'],
            ['key' => 'facturas.ver', 'name' => 'Ver facturas', 'module' => 'Facturas'],
            ['key' => 'facturas.crear', 'name' => 'Crear facturas', 'module' => 'Facturas'],
            ['key' => 'facturas.timbrar', 'name' => 'Timbrar facturas', 'module' => 'Facturas'],
            ['key' => 'productos.ver', 'name' => 'Ver productos', 'module' => 'Productos'],
            ['key' => 'productos.crear', 'name' => 'Crear productos', 'module' => 'Productos'],
            ['key' => 'productos.editar', 'name' => 'Editar productos', 'module' => 'Productos'],
            ['key' => 'usuarios.ver', 'name' => 'Ver usuarios', 'module' => 'Sistema'],
            ['key' => 'usuarios.crear', 'name' => 'Crear usuarios', 'module' => 'Sistema'],
            ['key' => 'usuarios.editar', 'name' => 'Editar usuarios', 'module' => 'Sistema'],
            ['key' => 'roles.ver', 'name' => 'Ver roles y permisos', 'module' => 'Sistema'],
            ['key' => 'roles.editar', 'name' => 'Editar roles y permisos', 'module' => 'Sistema'],
            ['key' => 'configuracion.editar', 'name' => 'Editar configuración empresa', 'module' => 'Sistema'],
        ];
        foreach ($permissions as $p) {
            $p['created_at'] = $now;
            $p['updated_at'] = $now;
        }
        DB::table('permissions')->insert($permissions);

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $permissionIds = DB::table('permissions')->pluck('id');
        foreach ($permissionIds as $pid) {
            DB::table('permission_role')->insert(['role_id' => $adminRoleId, 'permission_id' => $pid]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('password')->constrained('roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
        });
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
