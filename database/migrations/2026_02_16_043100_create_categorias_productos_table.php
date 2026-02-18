<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UBICACIÓN: database/migrations/XXXX_create_categorias_productos_table.php
     * 
     * Esta migración DEBE ejecutarse ANTES que create_productos_table
     * Renómbrala para que tenga una fecha anterior si es necesario.
     */
    public function up(): void
    {
        Schema::create('categorias_productos', function (Blueprint $table) {
            $table->id();
            
            // Datos básicos
            $table->string('nombre', 100);
            $table->string('codigo', 20)->unique()->nullable();
            $table->text('descripcion')->nullable();
            
            // Jerarquía (categoría padre)
            $table->foreignId('parent_id')->nullable()->constrained('categorias_productos')->nullOnDelete();
            
            // Color para UI
            $table->string('color', 7)->default('#0B3C5D'); // Hex color
            $table->string('icono', 50)->nullable(); // Emoji o clase de icono
            
            // Control
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('activo');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_productos');
    }
};