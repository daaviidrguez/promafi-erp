<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('serie_factura_credito', 5)->default('FB')->after('folio_factura');
            $table->integer('folio_factura_credito')->default(1)->after('serie_factura_credito');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['serie_factura_credito', 'folio_factura_credito']);
        });
    }
};
