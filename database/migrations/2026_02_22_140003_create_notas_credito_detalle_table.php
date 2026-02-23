<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_credito_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_credito_id')->constrained('notas_credito')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('clave_prod_serv', 8);
            $table->string('clave_unidad', 3);
            $table->string('unidad', 20)->default('Pieza');
            $table->string('no_identificacion', 100)->nullable();
            $table->text('descripcion');
            $table->decimal('cantidad', 15, 4);
            $table->decimal('valor_unitario', 15, 6);
            $table->decimal('importe', 15, 2);
            $table->decimal('descuento', 15, 2)->default(0);
            $table->decimal('base_impuesto', 15, 2);
            $table->string('objeto_impuesto', 2)->default('02');
            $table->integer('orden')->default(0);
            $table->timestamps();
            $table->index('nota_credito_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_credito_detalle');
    }
};
