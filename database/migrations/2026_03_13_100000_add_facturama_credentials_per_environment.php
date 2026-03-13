<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('pac_facturama_user_sandbox', 255)->nullable()->after('pac_facturama_password');
            $table->string('pac_facturama_password_sandbox', 255)->nullable()->after('pac_facturama_user_sandbox');
            $table->string('pac_facturama_user_production', 255)->nullable()->after('pac_facturama_password_sandbox');
            $table->string('pac_facturama_password_production', 255)->nullable()->after('pac_facturama_user_production');
        });

        // Migrar credenciales existentes a sandbox (eran las usadas antes)
        \Illuminate\Support\Facades\DB::table('empresas')->whereNotNull('pac_facturama_user')->update([
            'pac_facturama_user_sandbox' => \Illuminate\Support\Facades\DB::raw('pac_facturama_user'),
            'pac_facturama_password_sandbox' => \Illuminate\Support\Facades\DB::raw('pac_facturama_password'),
        ]);
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'pac_facturama_user_sandbox',
                'pac_facturama_password_sandbox',
                'pac_facturama_user_production',
                'pac_facturama_password_production',
            ]);
        });
    }
};
