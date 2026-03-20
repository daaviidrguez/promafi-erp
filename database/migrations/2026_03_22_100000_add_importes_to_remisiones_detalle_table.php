<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('remisiones_detalle', function (Blueprint $table) {
            $table->decimal('precio_unitario', 15, 2)->nullable()->after('unidad');
            $table->decimal('tasa_iva', 10, 4)->nullable()->after('precio_unitario');

            // Sin descuentos en remisión (por diseño). Se guarda snapshot por trazabilidad.
            $table->decimal('subtotal', 15, 2)->default(0)->after('tasa_iva');
            $table->decimal('iva_monto', 15, 2)->default(0)->after('subtotal');
            $table->decimal('total', 15, 2)->default(0)->after('iva_monto');
        });
    }

    public function down(): void
    {
        Schema::table('remisiones_detalle', function (Blueprint $table) {
            $table->dropColumn(['precio_unitario', 'tasa_iva', 'subtotal', 'iva_monto', 'total']);
        });
    }
};

