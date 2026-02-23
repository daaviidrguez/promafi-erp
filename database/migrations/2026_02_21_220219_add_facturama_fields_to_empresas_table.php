<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('pac_provider', 30)->default('fake')->after('pac_modo_prueba');
            $table->string('pac_facturama_user', 255)->nullable()->after('pac_provider');
            $table->string('pac_facturama_password', 255)->nullable()->after('pac_facturama_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['pac_provider', 'pac_facturama_user', 'pac_facturama_password']);
        });
    }
};
