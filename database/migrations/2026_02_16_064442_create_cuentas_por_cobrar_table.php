<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UBICACIÓN: database/migrations/2026_02_16_043340_create_cuentas_por_cobrar_table.php
     * 
     * IMPORTANTE: Debe ir DESPUÉS de create_facturas_table
     */
    public function up(): void
    {
        Schema::create('cuentas_por_cobrar', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes');
            
            // Importes
            $table->decimal('monto_total', 15, 2);
            $table->decimal('monto_pagado', 15, 2)->default(0);
            $table->decimal('monto_pendiente', 15, 2);
            
            // Fechas
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento');
            
            // Días de vencimiento (calculado automáticamente)
            $table->integer('dias_vencido')->default(0);
            
            // Estado
            $table->enum('estado', ['pendiente', 'parcial', 'vencida', 'pagada', 'cancelada'])->default('pendiente');
            
            // Notas
            $table->text('notas')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index('factura_id');
            $table->index('cliente_id');
            $table->index('estado');
            $table->index('fecha_vencimiento');
            $table->index(['estado', 'monto_pendiente']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_por_cobrar');
    }
};