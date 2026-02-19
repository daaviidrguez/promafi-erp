<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_contactos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')
                  ->constrained('clientes')
                  ->cascadeOnDelete();

            // Información principal
            $table->string('nombre');
            $table->string('puesto')->nullable();
            $table->string('departamento')->nullable();

            // Contacto
            $table->string('email')->nullable();
            $table->string('telefono', 15)->nullable();
            $table->string('celular', 15)->nullable();

            // Control
            $table->boolean('principal')->default(false);
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['cliente_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_contactos');
    }
};