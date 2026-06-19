<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entradas_anticipadas', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->enum('estado', [
                'borrador',
                'confirmada',
                'parcialmente_facturada',
                'facturada',
                'cancelada',
            ])->default('borrador');
            $table->foreignId('orden_compra_id')->nullable()->constrained('ordenes_compra')->nullOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->date('fecha_recepcion');
            $table->string('moneda', 3)->default('MXN');
            $table->decimal('tipo_cambio', 10, 6)->default(1);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('descuento', 15, 2)->default(0);
            $table->decimal('iva', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->foreignId('factura_compra_id')->nullable()->constrained('facturas_compra')->nullOnDelete();
            $table->timestamp('fecha_facturacion')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('estado');
            $table->index('proveedor_id');
            $table->index('orden_compra_id');
            $table->index('fecha_recepcion');
        });

        Schema::create('entradas_anticipadas_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrada_anticipada_id')->constrained('entradas_anticipadas')->cascadeOnDelete();
            $table->foreignId('orden_compra_detalle_id')->nullable()->constrained('ordenes_compra_detalle')->nullOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->string('codigo_proveedor', 100)->nullable();
            $table->text('descripcion');
            $table->decimal('cantidad_ordenada', 10, 2)->default(0);
            $table->decimal('cantidad_recibida', 10, 2);
            $table->decimal('cantidad_facturada', 10, 2)->default(0);
            $table->decimal('precio_unitario_estimado', 15, 2);
            $table->decimal('descuento_porcentaje', 5, 2)->default(0);
            $table->decimal('tasa_iva', 5, 4)->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('descuento_monto', 15, 2)->default(0);
            $table->decimal('iva_monto', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->index('entrada_anticipada_id');
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entradas_anticipadas_detalle');
        Schema::dropIfExists('entradas_anticipadas');
    }
};
