<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devolucion_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devolucion_id')->constrained('devoluciones')->cascadeOnDelete();
            $table->foreignId('factura_detalle_id')->constrained('facturas_detalle')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->decimal('cantidad_devuelta', 15, 4);
            $table->string('motivo_linea', 255)->nullable();
            $table->timestamps();
            $table->index('devolucion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucion_detalle');
    }
};
