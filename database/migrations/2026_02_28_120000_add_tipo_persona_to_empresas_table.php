<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->enum('tipo_persona', ['fisica', 'moral'])->default('moral')->after('regimen_fiscal')
                ->comment('Persona física o moral. Si es física + regimen 626 aplica tabla ISR RESICO.');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('tipo_persona');
        });
    }
};
