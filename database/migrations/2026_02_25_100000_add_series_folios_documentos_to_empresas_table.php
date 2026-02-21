<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('serie_nota_debito', 5)->default('ND')->after('folio_nota_credito');
            $table->integer('folio_nota_debito')->default(1)->after('serie_nota_debito');
            $table->string('serie_cotizacion', 10)->default('COT')->after('folio_complemento');
            $table->integer('folio_cotizacion')->default(1)->after('serie_cotizacion');
            $table->string('serie_remision', 10)->default('REM')->after('folio_cotizacion');
            $table->integer('folio_remision')->default(1)->after('serie_remision');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'serie_nota_debito',
                'folio_nota_debito',
                'serie_cotizacion',
                'folio_cotizacion',
                'serie_remision',
                'folio_remision',
            ]);
        });
    }
};
