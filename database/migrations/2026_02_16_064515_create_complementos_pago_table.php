<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UBICACIÓN: database/migrations/2026_02_16_043345_create_complementos_pago_table.php
     * IMPORTANTE: Debe ir DESPUÉS de create_cuentas_por_cobrar_table
     */
    public function up(): void
    {
        Schema::create('complementos_pago', function (Blueprint $table) {
            $table->id();
            
            // Identificación
            $table->string('serie', 5)->default('P');
            $table->integer('folio');
            $table->enum('estado', ['borrador', 'timbrado', 'cancelado'])->default('borrador');
            
            // Relaciones
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            
            // Datos del emisor
            $table->string('rfc_emisor', 13);
            $table->string('nombre_emisor');
            
            // Datos del receptor
            $table->string('rfc_receptor', 13);
            $table->string('nombre_receptor');
            
            // Datos fiscales
            $table->timestamp('fecha_emision');
            $table->string('lugar_expedicion', 5);
            $table->decimal('monto_total', 15, 2);
            
            // Timbrado
            $table->uuid('uuid')->nullable()->unique();
            $table->timestamp('fecha_timbrado')->nullable();
            $table->text('xml_content')->nullable();
            $table->string('xml_path')->nullable();
            
            // Control
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->unique(['serie', 'folio']);
            $table->index('estado');
            $table->index('cliente_id');
        });

        /*
        |--------------------------------------------------------------------------
        | PAGOS RECIBIDOS
        |--------------------------------------------------------------------------
        */
        Schema::create('pagos_recibidos', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('complemento_pago_id')
                  ->constrained('complementos_pago')
                  ->cascadeOnDelete();
            
            // Datos del pago
            $table->timestamp('fecha_pago');
            $table->string('forma_pago', 2); // Clave SAT
            $table->string('moneda', 3)->default('MXN');
            $table->decimal('tipo_cambio', 10, 6)->default(1);
            $table->decimal('monto', 15, 2);
            
            // Referencia bancaria (opcional)
            $table->string('num_operacion', 100)->nullable();
            $table->string('rfc_banco_ordenante', 13)->nullable();
            $table->string('nombre_banco_ordenante')->nullable();
            $table->string('cuenta_ordenante', 50)->nullable();
            
            $table->string('rfc_banco_beneficiario', 13)->nullable();
            $table->string('cuenta_beneficiario', 50)->nullable();
            
            $table->text('observaciones')->nullable();
            
            $table->timestamps();
            
            $table->index('complemento_pago_id');
        });

        /*
        |--------------------------------------------------------------------------
        | DOCUMENTOS RELACIONADOS (FACTURAS PAGADAS)
        |--------------------------------------------------------------------------
        */
        Schema::create('documentos_relacionados_pago', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('pago_recibido_id')
                  ->constrained('pagos_recibidos')
                  ->cascadeOnDelete();
                  
            $table->foreignId('factura_id')
                  ->constrained('facturas');
            
            // UUID de la factura (CRÍTICO para calcular parcialidades)
            $table->string('factura_uuid', 36); // ← COLUMNA AGREGADA
            
            // Datos del documento relacionado
            $table->string('serie', 5);
            $table->string('folio', 20);
            $table->string('moneda', 3);
            $table->decimal('monto_total', 15, 2);
            
            // Parcialidades y saldos
            $table->integer('parcialidad'); // Número de pago (1, 2, 3...)
            $table->decimal('saldo_anterior', 15, 2);
            $table->decimal('monto_pagado', 15, 2);
            $table->decimal('saldo_insoluto', 15, 2);
            
            $table->timestamps();
            
            // Índices
            $table->index('pago_recibido_id');
            $table->index('factura_id');
            $table->index('factura_uuid'); // ← ÍNDICE PARA factura_uuid
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_relacionados_pago');
        Schema::dropIfExists('pagos_recibidos');
        Schema::dropIfExists('complementos_pago');
    }
};