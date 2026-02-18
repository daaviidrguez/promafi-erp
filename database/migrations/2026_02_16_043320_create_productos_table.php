<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UBICACIÓN: database/migrations/2026_02_16_043320_create_productos_table.php
     * 
     * IMPORTANTE: Debe ir DESPUÉS de categorias_productos y ANTES de cotizaciones_detalle
     * 
     * Orden correcto:
     * - 2026_02_16_043100_create_categorias_productos_table.php
     * - 2026_02_16_043320_create_productos_table.php          ← ESTE
     * - 2026_02_16_043330_create_cotizaciones_table.php
     * - 2026_02_16_043333_create_cotizaciones_detalle_table.php
     */
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            
            // Identificación
            $table->string('codigo', 50)->unique();
            $table->string('codigo_barras', 50)->nullable()->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            
            // Clasificación SAT
            $table->string('clave_sat', 8)->default('01010101'); // Clave producto/servicio SAT
            $table->string('clave_unidad_sat', 3)->default('H87'); // Clave unidad SAT
            $table->string('unidad', 20)->default('Pieza'); // Nombre de la unidad
            
            // Precios
            $table->decimal('costo', 15, 2)->default(0);
            $table->decimal('precio_venta', 15, 2)->default(0);
            $table->decimal('precio_mayoreo', 15, 2)->nullable();
            $table->decimal('precio_minimo', 15, 2)->nullable();
            
            // Impuestos
            $table->decimal('tasa_iva', 5, 4)->default(0.1600); // 16%
            $table->boolean('aplica_iva')->default(true);
            $table->decimal('tasa_ieps', 5, 4)->default(0); // Para productos especiales
            
            // Inventario
            $table->decimal('stock', 15, 2)->default(0);
            $table->decimal('stock_minimo', 15, 2)->default(0);
            $table->decimal('stock_maximo', 15, 2)->nullable();
            $table->boolean('controla_inventario')->default(true);
            
            // Categorización
            $table->foreignId('categoria_id')->nullable()->constrained('categorias_productos')->nullOnDelete();
            
            // Imágenes
            $table->string('imagen_principal')->nullable();
            
            // Control
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('codigo');
            $table->index('codigo_barras');
            $table->index('clave_sat');
            $table->index('activo');
            $table->index('categoria_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};