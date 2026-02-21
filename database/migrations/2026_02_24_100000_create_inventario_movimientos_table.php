<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('tipo', 40); // entrada_compra, salida_factura, devolucion_factura, salida_remision, entrada_manual, salida_manual
            $table->decimal('cantidad', 15, 2); // siempre positivo; entrada suma, salida resta
            $table->decimal('stock_anterior', 15, 2)->nullable();
            $table->decimal('stock_resultante', 15, 2)->nullable();
            $table->foreignId('factura_id')->nullable()->constrained('facturas')->nullOnDelete();
            $table->foreignId('remision_id')->nullable()->constrained('remisiones')->nullOnDelete();
            $table->foreignId('orden_compra_id')->nullable()->constrained('ordenes_compra')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('observaciones', 500)->nullable();
            $table->timestamps();
            $table->index(['producto_id', 'created_at']);
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_movimientos');
    }
};
