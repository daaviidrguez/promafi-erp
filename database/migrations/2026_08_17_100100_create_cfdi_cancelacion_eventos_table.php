<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfdi_cancelacion_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('cancelable_type');
            $table->unsignedBigInteger('cancelable_id');
            $table->string('tipo', 30);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status_pac', 30)->nullable();
            $table->string('estatus_sat', 40)->nullable();
            $table->string('codigo_estatus', 20)->nullable();
            $table->string('is_cancelable', 80)->nullable();
            $table->text('mensaje')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['cancelable_type', 'cancelable_id'], 'cce_cancelable_idx');
            $table->index(['tipo', 'created_at'], 'cce_tipo_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfdi_cancelacion_eventos');
    }
};
