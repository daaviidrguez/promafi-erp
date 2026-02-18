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
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            
            // Identificación
            $table->string('serie', 5)->default('A');
            $table->integer('folio');
            $table->string('tipo_comprobante', 1)->default('I'); // I=Ingreso, E=Egreso, T=Traslado
            $table->enum('estado', ['borrador', 'timbrada', 'cancelada'])->default('borrador');
            
            // Relaciones
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('empresa_id')->nullable()->constrained('empresas');
            
            // Datos del Emisor (se copian al timbrar)
            $table->string('rfc_emisor', 13);
            $table->string('nombre_emisor');
            $table->string('regimen_fiscal_emisor', 3);
            
            // Datos del Receptor
            $table->string('rfc_receptor', 13);
            $table->string('nombre_receptor');
            $table->string('uso_cfdi', 3); // Clave SAT
            $table->string('regimen_fiscal_receptor', 3)->nullable();
            $table->string('domicilio_fiscal_receptor', 5)->nullable(); // CP
            
            // Datos Fiscales
            $table->string('lugar_expedicion', 5); // CP de expedición
            $table->timestamp('fecha_emision');
            $table->string('forma_pago', 2)->default('99'); // Clave SAT
            $table->string('metodo_pago', 3)->default('PUE'); // PUE o PPD
            $table->string('moneda', 3)->default('MXN');
            $table->decimal('tipo_cambio', 10, 6)->default(1);
            
            // Importes
            $table->decimal('subtotal', 15, 2);
            $table->decimal('descuento', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            
            // Timbrado
            $table->uuid('uuid')->nullable()->unique();
            $table->timestamp('fecha_timbrado')->nullable();
            $table->string('no_certificado_sat', 20)->nullable();
            $table->text('sello_cfdi')->nullable();
            $table->text('sello_sat')->nullable();
            $table->text('cadena_original')->nullable();
            
            // XML y PDF
            $table->text('xml_content')->nullable();
            $table->string('xml_path')->nullable();
            $table->string('pdf_path')->nullable();
            
            // Cancelación
            $table->string('motivo_cancelacion', 2)->nullable();
            $table->timestamp('fecha_cancelacion')->nullable();
            $table->text('acuse_cancelacion')->nullable();
            
            // Relación con documentos
            $table->foreignId('cotizacion_id')->nullable()->constrained('cotizaciones')->nullOnDelete();
            
            // Control
            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->unique(['serie', 'folio']);
            $table->index('uuid');
            $table->index('estado');
            $table->index(['cliente_id', 'estado']);
            $table->index('fecha_emision');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};