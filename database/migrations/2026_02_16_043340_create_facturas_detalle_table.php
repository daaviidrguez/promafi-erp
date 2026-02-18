<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('facturas_detalle', function (Blueprint $table) {
            $table->id();
            
            // Relación con factura
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            
            // Relación con producto (puede ser NULL para productos manuales)
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            
            // Datos del producto/servicio
            $table->string('clave_prod_serv', 8); // Clave SAT
            $table->string('clave_unidad', 3); // Clave unidad SAT
            $table->string('unidad', 20)->default('Pieza'); // Nombre de la unidad
            $table->string('no_identificacion', 100)->nullable(); // SKU/Código
            $table->text('descripcion');
            
            // Cantidades e importes
            $table->decimal('cantidad', 15, 4);
            $table->decimal('valor_unitario', 15, 6);
            $table->decimal('importe', 15, 2); // cantidad * valor_unitario
            $table->decimal('descuento', 15, 2)->default(0);
            
            // Objeto de impuesto (se guardan en tabla relacionada)
            // Solo guardamos el importe base aquí
            $table->decimal('base_impuesto', 15, 2);
            
            $table->integer('orden')->default(0);
            $table->timestamps();
            
            // Índices
            $table->index('factura_id');
            $table->index('producto_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facturas_detalle');
    }
};