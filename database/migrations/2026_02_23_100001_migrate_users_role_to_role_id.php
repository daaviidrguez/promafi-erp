<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'role')) {
            return;
        }
        $roleMap = [];
        foreach (DB::table('roles')->get() as $role) {
            $roleMap[$role->name] = $role->id;
        }
        foreach (DB::table('users')->get() as $user) {
            $roleName = $user->role ?? 'usuario';
            $roleId = $roleMap[$roleName] ?? $roleMap['usuario'] ?? null;
            if ($roleId) {
                DB::table('users')->where('id', $user->id)->update(['role_id' => $roleId]);
            }
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('usuario')->after('password');
        });
        foreach (DB::table('users')->get() as $user) {
            $role = DB::table('roles')->find($user->role_id);
            DB::table('users')->where('id', $user->id)->update(['role' => $role->name ?? 'usuario']);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
        });
    }
};
