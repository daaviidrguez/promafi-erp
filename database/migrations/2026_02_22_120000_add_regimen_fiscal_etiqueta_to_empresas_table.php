<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const REGIMEN_ETIQUETAS = [
        '601' => '601 - General de Ley Personas Morales',
        '603' => '603 - Personas Morales sin Fines Lucrativos',
        '605' => '605 - Sueldos y Salarios',
        '606' => '606 - Arrendamiento',
        '612' => '612 - Personas Físicas con Act. Empresariales',
        '621' => '621 - Incorporación Fiscal',
        '626' => '626 - Régimen Simplificado de Confianza',
    ];

    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('regimen_fiscal_etiqueta', 120)->nullable()->after('regimen_fiscal');
        });

        foreach (self::REGIMEN_ETIQUETAS as $codigo => $etiqueta) {
            DB::table('empresas')->where('regimen_fiscal', $codigo)->update(['regimen_fiscal_etiqueta' => $etiqueta]);
        }
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('regimen_fiscal_etiqueta');
        });
    }
};
