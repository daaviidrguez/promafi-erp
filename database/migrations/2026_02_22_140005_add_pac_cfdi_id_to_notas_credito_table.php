<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_credito', function (Blueprint $table) {
            $table->string('pac_cfdi_id')->nullable()->after('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('notas_credito', function (Blueprint $table) {
            $table->dropColumn('pac_cfdi_id');
        });
    }
};
