<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remisiones', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->enum('estado', ['borrador', 'enviada', 'entregada', 'cancelada'])->default('borrador');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('factura_id')->nullable()->constrained('facturas')->nullOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->string('cliente_nombre');
            $table->string('cliente_rfc', 13)->nullable();
            $table->date('fecha');
            $table->text('direccion_entrega')->nullable();
            $table->date('fecha_entrega')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('estado');
            $table->index('cliente_id');
            $table->index('fecha');
            $table->index('factura_id');
        });

        Schema::create('remisiones_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('remision_id')->constrained('remisiones')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('codigo', 50)->nullable();
            $table->text('descripcion');
            $table->decimal('cantidad', 10, 2);
            $table->string('unidad', 10)->default('PZA');
            $table->integer('orden')->default(0);
            $table->timestamps();
            $table->index('remision_id');
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remisiones_detalle');
        Schema::dropIfExists('remisiones');
    }
};
