<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si quedó tabla huérfana por migración interrumpida (índice demasiado largo en MySQL), recrear limpio.
        Schema::dropIfExists('factura_cancelaciones_administrativas');

        Schema::create('factura_cancelaciones_administrativas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('detalle')->nullable();
            $table->timestamps();

            $table->index(['factura_id', 'created_at'], 'fca_factura_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_cancelaciones_administrativas');
    }
};
