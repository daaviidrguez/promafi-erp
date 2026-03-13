<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturas_compra', function (Blueprint $table) {
            $table->id();
            $table->string('serie', 5)->nullable();
            $table->string('folio', 40); // Puede ser numérico o alfanumérico
            $table->string('tipo_comprobante', 1)->default('E'); // E=Egreso (compra)
            $table->enum('estado', ['borrador', 'registrada', 'cancelada'])->default('registrada');

            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('orden_compra_id')->nullable()->constrained('ordenes_compra')->nullOnDelete();

            // Emisor (proveedor - quien vende a la empresa)
            $table->string('rfc_emisor', 13);
            $table->string('nombre_emisor');
            $table->string('regimen_fiscal_emisor', 5)->nullable();

            // Receptor (empresa - quien compra)
            $table->string('rfc_receptor', 13);
            $table->string('nombre_receptor');
            $table->string('regimen_fiscal_receptor', 5)->nullable();

            $table->string('lugar_expedicion', 10)->nullable();
            $table->timestamp('fecha_emision');
            $table->string('forma_pago', 2)->nullable();
            $table->string('metodo_pago', 3)->nullable();
            $table->string('moneda', 3)->default('MXN');
            $table->decimal('tipo_cambio', 10, 6)->default(1);

            $table->decimal('subtotal', 15, 2);
            $table->decimal('descuento', 15, 2)->default(0);
            $table->decimal('total', 15, 2);

            $table->uuid('uuid')->nullable()->unique();
            $table->timestamp('fecha_timbrado')->nullable();
            $table->string('no_certificado_sat', 20)->nullable();

            $table->text('xml_content')->nullable();
            $table->string('xml_path')->nullable();
            $table->string('pdf_path')->nullable();

            $table->text('observaciones')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index('estado');
            $table->index('proveedor_id');
            $table->index('fecha_emision');
        });

        Schema::create('facturas_compra_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_compra_id')->constrained('facturas_compra')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();

            $table->string('clave_prod_serv', 10)->default('01010101');
            $table->string('clave_unidad', 3)->default('H87');
            $table->string('unidad', 20)->default('Pieza');
            $table->string('no_identificacion', 100)->nullable();
            $table->text('descripcion');

            $table->decimal('cantidad', 15, 4);
            $table->decimal('valor_unitario', 15, 6);
            $table->decimal('importe', 15, 2);
            $table->decimal('descuento', 15, 2)->default(0);
            $table->decimal('base_impuesto', 15, 2)->default(0);
            $table->string('objeto_impuesto', 2)->default('02');

            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->index('factura_compra_id');
        });

        Schema::create('facturas_compra_impuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_compra_detalle_id')->constrained('facturas_compra_detalle')->cascadeOnDelete();

            $table->enum('tipo', ['traslado', 'retencion']);
            $table->string('impuesto', 3);
            $table->enum('tipo_factor', ['Tasa', 'Cuota', 'Exento'])->default('Tasa');
            $table->decimal('tasa_o_cuota', 8, 6)->nullable();

            $table->decimal('base', 15, 2);
            $table->decimal('importe', 15, 2)->nullable();

            $table->timestamps();
            $table->index('factura_compra_detalle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas_compra_impuestos');
        Schema::dropIfExists('facturas_compra_detalle');
        Schema::dropIfExists('facturas_compra');
    }
};
