<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Regímenes fiscales (SAT)
        Schema::create('regimenes_fiscales', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 10)->unique();
            $table->string('descripcion', 255);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        // Usos de CFDI
        Schema::create('usos_cfdi', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 10)->unique();
            $table->string('descripcion', 255);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        // Formas de pago (SAT)
        Schema::create('formas_pago', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 5)->unique();
            $table->string('descripcion', 255);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        // Métodos de pago (PUE, PPD)
        Schema::create('metodos_pago', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 5)->unique();
            $table->string('descripcion', 255);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        // Monedas
        Schema::create('monedas', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 10)->unique();
            $table->string('descripcion', 255);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        // Unidades de medida (SAT)
        Schema::create('unidades_medida_sat', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 10)->unique();
            $table->string('descripcion', 255);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        // Clave producto/servicio (SAT) - se puede cargar masivamente por Excel
        Schema::create('claves_producto_servicio', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 8)->unique();
            $table->string('descripcion', 500);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
            $table->index('clave');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claves_producto_servicio');
        Schema::dropIfExists('unidades_medida_sat');
        Schema::dropIfExists('monedas');
        Schema::dropIfExists('metodos_pago');
        Schema::dropIfExists('formas_pago');
        Schema::dropIfExists('usos_cfdi');
        Schema::dropIfExists('regimenes_fiscales');
    }
};
