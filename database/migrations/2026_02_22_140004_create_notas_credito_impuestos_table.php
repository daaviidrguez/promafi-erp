<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_credito_impuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_credito_detalle_id')->constrained('notas_credito_detalle')->cascadeOnDelete();
            $table->enum('tipo', ['traslado', 'retencion']);
            $table->string('impuesto', 3);
            $table->enum('tipo_factor', ['Tasa', 'Cuota', 'Exento']);
            $table->decimal('tasa_o_cuota', 8, 6)->nullable();
            $table->decimal('base', 15, 2);
            $table->decimal('importe', 15, 2)->nullable();
            $table->timestamps();
            $table->index('nota_credito_detalle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_credito_impuestos');
    }
};
