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
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            
            // ==============================
            // DATOS FISCALES
            // ==============================
            $table->string('rfc', 13)->unique();
            $table->string('razon_social');
            $table->string('nombre_comercial')->nullable();
            $table->string('regimen_fiscal', 3);

            // ==============================
            // DOMICILIO FISCAL
            // ==============================
            $table->string('calle');
            $table->string('numero_exterior', 10);
            $table->string('numero_interior', 10)->nullable();
            $table->string('colonia');
            $table->string('municipio')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('estado');
            $table->string('codigo_postal', 5);
            $table->string('pais', 3)->default('MEX');

            // ==============================
            // CONTACTO
            // ==============================
            $table->string('telefono', 15)->nullable();
            $table->string('email')->nullable();
            $table->string('sitio_web')->nullable();

            // ==============================
            // IDENTIDAD VISUAL
            // ==============================
            $table->string('logo_path')->nullable();

            // ==============================
            // DATOS BANCARIOS
            // ==============================
            $table->string('banco')->nullable();
            $table->string('numero_cuenta')->nullable();
            $table->string('clabe', 18)->nullable();

            // ==============================
            // CERTIFICADOS SAT
            // ==============================
            $table->text('certificado_cer')->nullable();
            $table->text('certificado_key')->nullable();
            $table->string('certificado_password')->nullable();
            $table->string('no_certificado', 20)->nullable();
            $table->date('certificado_vigencia')->nullable();

            // ==============================
            // CONFIGURACIÓN DE FACTURACIÓN
            // ==============================
            $table->string('serie_factura', 5)->default('A');
            $table->integer('folio_factura')->default(1);

            $table->string('serie_nota_credito', 5)->default('NC');
            $table->integer('folio_nota_credito')->default(1);

            $table->string('serie_complemento', 5)->default('CP');
            $table->integer('folio_complemento')->default(1);

            // ==============================
            // PAC (Proveedor Autorizado de Certificación)
            // ==============================
            $table->string('pac_nombre')->nullable();
            $table->string('pac_api_key')->nullable();
            $table->string('pac_endpoint')->nullable();
            $table->boolean('pac_modo_prueba')->default(true);

            // ==============================
            // CONTROL
            // ==============================
            $table->boolean('activo')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};