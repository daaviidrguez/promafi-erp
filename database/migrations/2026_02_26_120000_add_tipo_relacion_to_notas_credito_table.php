<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_credito', function (Blueprint $table) {
            $table->string('tipo_relacion', 2)->default('01')->after('uuid_referencia')
                ->comment('c_TipoRelacion SAT: 01=Nota de crédito de los documentos relacionados');
        });
    }

    public function down(): void
    {
        Schema::table('notas_credito', function (Blueprint $table) {
            $table->dropColumn('tipo_relacion');
        });
    }
};
