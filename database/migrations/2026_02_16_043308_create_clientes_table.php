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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            
            // Datos Generales
            $table->string('codigo', 20)->unique()->nullable();
            $table->string('nombre');
            $table->string('nombre_comercial')->nullable();
            
            // Datos Fiscales
            $table->string('rfc', 13)->unique();
            $table->string('regimen_fiscal', 3)->nullable(); // Clave SAT
            $table->string('uso_cfdi_default', 3)->default('G03'); // Clave SAT
            
            // Domicilio Fiscal
            $table->string('calle')->nullable();
            $table->string('numero_exterior', 10)->nullable();
            $table->string('numero_interior', 10)->nullable();
            $table->string('colonia')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('estado')->nullable();
            $table->string('codigo_postal', 5)->nullable();
            $table->string('pais', 3)->default('MEX');
            
            // Contacto
            $table->string('email')->nullable();
            $table->string('telefono', 15)->nullable();
            $table->string('celular', 15)->nullable();
            $table->string('contacto_nombre')->nullable();
            $table->string('contacto_puesto')->nullable();
            
            // Configuración Comercial
            $table->integer('dias_credito')->default(0); // 0 = Contado
            $table->decimal('limite_credito', 15, 2)->default(0);
            $table->decimal('saldo_actual', 15, 2)->default(0);
            $table->decimal('descuento_porcentaje', 5, 2)->default(0);
            
            // Datos Bancarios (opcional)
            $table->string('banco')->nullable();
            $table->string('cuenta_bancaria')->nullable();
            $table->string('clabe', 18)->nullable();
            
            // Control
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices adicionales
            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};