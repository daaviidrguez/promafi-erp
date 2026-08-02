<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_metas_comerciales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();

            // periodo: anual | mensual (monto fijo; mensual no requiere mes concreto)
            $table->unsignedSmallInteger('anio');
            $table->string('periodo', 20)->default('anual');
            $table->decimal('monto_meta', 15, 2);
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->unique(['cliente_id', 'anio', 'periodo'], 'cliente_metas_unicas');
            $table->index(['anio', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_metas_comerciales');
    }
};
