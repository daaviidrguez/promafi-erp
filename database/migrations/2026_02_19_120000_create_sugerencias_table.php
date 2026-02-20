<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sugerencias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->nullable()->comment('Clave o modelo para búsqueda rápida (ej. 46557, CPS26)');
            $table->text('descripcion');
            $table->string('unidad', 10)->default('PZA');
            $table->decimal('precio_unitario', 15, 2)->default(0);
            $table->timestamps();
            $table->index('codigo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sugerencias');
    }
};
