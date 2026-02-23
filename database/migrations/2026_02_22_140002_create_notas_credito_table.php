<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_credito', function (Blueprint $table) {
            $table->id();
            $table->string('serie', 5)->default('NC');
            $table->unsignedInteger('folio');
            $table->string('tipo_comprobante', 1)->default('E');
            $table->enum('estado', ['borrador', 'timbrada', 'cancelada'])->default('borrador');
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('empresa_id')->nullable()->constrained('empresas');
            $table->foreignId('devolucion_id')->nullable()->constrained('devoluciones')->nullOnDelete();
            $table->string('rfc_emisor', 13);
            $table->string('nombre_emisor');
            $table->string('regimen_fiscal_emisor', 3);
            $table->string('rfc_receptor', 13);
            $table->string('nombre_receptor');
            $table->string('uso_cfdi', 3);
            $table->string('regimen_fiscal_receptor', 3)->nullable();
            $table->string('domicilio_fiscal_receptor', 5)->nullable();
            $table->string('lugar_expedicion', 5);
            $table->timestamp('fecha_emision');
            $table->string('forma_pago', 2)->default('99');
            $table->string('metodo_pago', 3)->default('PUE');
            $table->string('moneda', 3)->default('MXN');
            $table->decimal('tipo_cambio', 10, 6)->default(1);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('descuento', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->string('motivo_cfdi', 2)->nullable();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('uuid_referencia', 36)->nullable();
            $table->timestamp('fecha_timbrado')->nullable();
            $table->string('no_certificado_sat', 20)->nullable();
            $table->text('sello_cfdi')->nullable();
            $table->text('sello_sat')->nullable();
            $table->text('cadena_original')->nullable();
            $table->text('xml_content')->nullable();
            $table->string('xml_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('motivo_cancelacion', 2)->nullable();
            $table->timestamp('fecha_cancelacion')->nullable();
            $table->text('acuse_cancelacion')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['serie', 'folio']);
            $table->index('uuid');
            $table->index('estado');
            $table->index('factura_id');
            $table->index('fecha_emision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_credito');
    }
};
