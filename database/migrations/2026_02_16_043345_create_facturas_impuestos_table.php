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
        Schema::create('facturas_impuestos', function (Blueprint $table) {
            $table->id();
            
            // Relación con el detalle de la factura
            $table->foreignId('factura_detalle_id')->constrained('facturas_detalle')->cascadeOnDelete();
            
            // Tipo de impuesto
            $table->enum('tipo', ['traslado', 'retencion']); // Traslado o Retención
            $table->string('impuesto', 3); // 001=ISR, 002=IVA, 003=IEPS
            $table->enum('tipo_factor', ['Tasa', 'Cuota', 'Exento']);
            $table->decimal('tasa_o_cuota', 8, 6)->nullable(); // 0.160000 para IVA 16%
            
            // Importes
            $table->decimal('base', 15, 2); // Base gravable
            $table->decimal('importe', 15, 2)->nullable(); // Monto del impuesto
            
            $table->timestamps();
            
            // Índices
            $table->index('factura_detalle_id');
            $table->index(['tipo', 'impuesto']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facturas_impuestos');
    }
};