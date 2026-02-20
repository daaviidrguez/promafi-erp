<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones_compra', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->enum('estado', ['borrador', 'aprobada', 'rechazada', 'convertida_oc'])->default('borrador');
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->string('proveedor_nombre');
            $table->string('proveedor_rfc', 13)->nullable();
            $table->string('proveedor_email')->nullable();
            $table->string('proveedor_telefono', 20)->nullable();
            $table->date('fecha');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('moneda', 3)->default('MXN');
            $table->decimal('tipo_cambio', 10, 6)->default(1);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('descuento', 15, 2)->default(0);
            $table->decimal('iva', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('estado');
            $table->index('proveedor_id');
            $table->index('fecha');
        });

        Schema::create('cotizaciones_compra_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_compra_id')->constrained('cotizaciones_compra')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('codigo', 50)->nullable();
            $table->text('descripcion');
            $table->boolean('es_producto_manual')->default(false);
            $table->decimal('cantidad', 10, 2);
            $table->decimal('precio_unitario', 15, 2);
            $table->decimal('descuento_porcentaje', 5, 2)->default(0);
            $table->decimal('tasa_iva', 5, 4)->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('descuento_monto', 15, 2)->default(0);
            $table->decimal('base_imponible', 15, 2);
            $table->decimal('iva_monto', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->integer('orden')->default(0);
            $table->timestamps();
            $table->index('cotizacion_compra_id');
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizaciones_compra_detalle');
        Schema::dropIfExists('cotizaciones_compra');
    }
};
