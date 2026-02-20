<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique()->nullable();
            $table->string('nombre');
            $table->string('nombre_comercial')->nullable();
            $table->string('rfc', 13)->nullable();
            $table->string('regimen_fiscal', 3)->nullable();
            $table->string('calle')->nullable();
            $table->string('numero_exterior', 10)->nullable();
            $table->string('numero_interior', 10)->nullable();
            $table->string('colonia')->nullable();
            $table->string('municipio')->nullable();
            $table->string('estado')->nullable();
            $table->string('codigo_postal', 5)->nullable();
            $table->string('pais', 3)->default('MEX');
            $table->string('email')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('contacto_nombre')->nullable();
            $table->integer('dias_credito')->default(0);
            $table->string('banco')->nullable();
            $table->string('cuenta_bancaria')->nullable();
            $table->string('clabe', 18)->nullable();
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
