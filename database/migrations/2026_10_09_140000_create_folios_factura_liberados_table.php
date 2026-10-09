<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Folios de factura que quedaron libres al eliminar un borrador intermedio; se reutilizan antes de avanzar el contador.
        Schema::create('folios_factura_liberados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('serie', 10);
            $table->integer('folio');
            $table->timestamps();

            $table->unique(['empresa_id', 'serie', 'folio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folios_factura_liberados');
    }
};
