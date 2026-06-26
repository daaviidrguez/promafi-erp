<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->decimal('pdf_factura_font_cuerpo', 3, 1)->default(7.5)->after('logo_path');
            $table->decimal('pdf_factura_font_titulo', 3, 1)->default(8.0)->after('pdf_factura_font_cuerpo');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['pdf_factura_font_cuerpo', 'pdf_factura_font_titulo']);
        });
    }
};
