<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devoluciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('empresa_id')->nullable()->constrained('empresas');
            $table->date('fecha_devolucion');
            $table->string('motivo', 100)->nullable();
            $table->enum('estado', ['borrador', 'autorizada', 'cerrada'])->default('borrador');
            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['factura_id', 'estado']);
            $table->index('fecha_devolucion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devoluciones');
    }
};
