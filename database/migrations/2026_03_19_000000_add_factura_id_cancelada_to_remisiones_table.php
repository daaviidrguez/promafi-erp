<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remisiones', function (Blueprint $table) {
            $table->foreignId('factura_id_cancelada')
                ->nullable()
                ->constrained('facturas')
                ->nullOnDelete();
            $table->index('factura_id_cancelada');
        });
    }

    public function down(): void
    {
        Schema::table('remisiones', function (Blueprint $table) {
            $table->dropForeign(['factura_id_cancelada']);
            $table->dropIndex(['factura_id_cancelada']);
            $table->dropColumn('factura_id_cancelada');
        });
    }
};

