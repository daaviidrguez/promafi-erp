<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_proveedores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();

            $table->string('codigo', 100)->comment('Código del producto para el proveedor');

            $table->timestamps();

            $table->unique(['producto_id', 'proveedor_id']);
            $table->index(['producto_id', 'proveedor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_proveedores');
    }
};

