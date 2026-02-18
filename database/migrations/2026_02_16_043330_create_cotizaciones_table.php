<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UBICACIÓN: database/migrations/2026_02_16_200000_create_cotizaciones_table.php
     * IMPORTANTE: Debe ir DESPUÉS de clientes y productos
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | TABLA PRINCIPAL: cotizaciones
        |--------------------------------------------------------------------------
        */
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            
            // Identificación
            $table->string('folio', 20)->unique();
            $table->enum('estado', ['borrador', 'aceptada', 'enviada', 'facturada', 'rechazada', 'vencida'])
                  ->default('borrador');
            
            // Relaciones
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            
            // Datos del cliente (snapshot)
            $table->string('cliente_nombre');
            $table->string('cliente_rfc', 13);
            $table->string('cliente_email')->nullable();
            $table->string('cliente_telefono', 20)->nullable();
            
            // Dirección del cliente
            $table->string('cliente_calle')->nullable();
            $table->string('cliente_numero_exterior', 10)->nullable();
            $table->string('cliente_numero_interior', 10)->nullable();
            $table->string('cliente_colonia')->nullable();
            $table->string('cliente_municipio')->nullable();
            $table->string('cliente_estado')->nullable();
            $table->string('cliente_codigo_postal', 5)->nullable();
            
            // Datos fiscales
            $table->date('fecha');
            $table->date('fecha_vencimiento')->nullable(); // Válida hasta
            
            // Moneda
            $table->string('moneda', 3)->default('MXN');
            $table->decimal('tipo_cambio', 10, 6)->default(1);
            
            // Importes
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('descuento', 15, 2)->default(0);
            $table->decimal('iva', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            
            // Condiciones de pago
            $table->enum('tipo_venta', ['contado', 'credito'])->default('contado');
            $table->integer('dias_credito_aplicados')->default(0);
            $table->text('condiciones_pago')->nullable();
            $table->text('observaciones')->nullable();
            
            // Archivos generados
            $table->string('pdf_path')->nullable();
            $table->timestamp('fecha_envio')->nullable();
            
            // Control
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('estado');
            $table->index('cliente_id');
            $table->index('fecha');
            $table->index('fecha_vencimiento');
        });

        /*
        |--------------------------------------------------------------------------
        | DETALLE: cotizaciones_detalle
        |--------------------------------------------------------------------------
        */
        Schema::create('cotizaciones_detalle', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('cotizacion_id')
                  ->constrained('cotizaciones')
                  ->cascadeOnDelete();
                  
            $table->foreignId('producto_id')
                  ->nullable()
                  ->constrained('productos')
                  ->nullOnDelete();
            
            // Datos del producto (snapshot o manual)
            $table->string('codigo', 50)->nullable();
            $table->text('descripcion');
            $table->boolean('es_producto_manual')->default(false);
            
            // Cantidades y precios
            $table->decimal('cantidad', 10, 2);
            $table->decimal('precio_unitario', 15, 2);
            $table->decimal('descuento_porcentaje', 5, 2)->default(0);
            
            // IVA
            $table->decimal('tasa_iva', 5, 4)->nullable(); // null = exento, 0.16 = 16%
            
            // Importes calculados
            $table->decimal('subtotal', 15, 2); // cantidad * precio_unitario
            $table->decimal('descuento_monto', 15, 2)->default(0);
            $table->decimal('base_imponible', 15, 2); // subtotal - descuento
            $table->decimal('iva_monto', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            
            // Orden de aparición
            $table->integer('orden')->default(0);
            
            $table->timestamps();
            
            // Índices
            $table->index('cotizacion_id');
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizaciones_detalle');
        Schema::dropIfExists('cotizaciones');
    }
};