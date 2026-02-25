<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listas_precios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index('cliente_id');
            $table->index('activo');
        });

        Schema::create('listas_precios_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lista_precio_id')->constrained('listas_precios')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->enum('tipo_utilidad', ['factorizado', 'porcentual']);
            $table->decimal('valor_utilidad', 10, 4);
            $table->integer('orden')->default(0);
            $table->timestamps();
            $table->unique(['lista_precio_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listas_precios_detalle');
        Schema::dropIfExists('listas_precios');
    }
};
