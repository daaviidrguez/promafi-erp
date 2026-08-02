<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_truper', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->comment('Código Truper');
            $table->string('clave', 100)->nullable();
            $table->text('descripcion');
            $table->string('unidad', 20)->default('PZA');
            $table->decimal('costo', 15, 4)->default(0)->comment('Precio distribuidor sin IVA');
            $table->decimal('venta', 15, 4)->default(0)->comment('Precio medio mayoreo sin IVA');
            $table->string('codigo_sat', 20)->nullable();
            $table->decimal('peso_kg', 12, 4)->nullable();
            $table->decimal('volumen_cm3', 15, 4)->nullable();
            $table->timestamps();

            $table->unique('codigo');
            $table->index('clave');
            $table->index('codigo_sat');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_truper');
    }
};
