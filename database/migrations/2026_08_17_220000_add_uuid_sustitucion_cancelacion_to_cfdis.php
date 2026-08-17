<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->string('uuid_sustitucion_cancelacion', 36)->nullable()->after('motivo_cancelacion');
        });

        Schema::table('complementos_pago', function (Blueprint $table) {
            $table->string('uuid_sustitucion_cancelacion', 36)->nullable()->after('motivo_cancelacion');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('uuid_sustitucion_cancelacion');
        });

        Schema::table('complementos_pago', function (Blueprint $table) {
            $table->dropColumn('uuid_sustitucion_cancelacion');
        });
    }
};
