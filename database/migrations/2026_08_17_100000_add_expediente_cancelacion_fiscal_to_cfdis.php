<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->string('estatus_cancelacion_pac', 30)->nullable()->after('codigo_estatus_cancelacion');
            $table->string('mensaje_cancelacion_pac', 500)->nullable()->after('estatus_cancelacion_pac');
            $table->string('is_cancelable', 80)->nullable()->after('mensaje_cancelacion_pac');
            $table->timestamp('fecha_solicitud_cancelacion')->nullable()->after('fecha_cancelacion_pac');
            $table->timestamp('fecha_vencimiento_aceptacion')->nullable()->after('fecha_solicitud_cancelacion');
            $table->string('estatus_sat', 40)->nullable()->after('fecha_vencimiento_aceptacion');
        });

        Schema::table('complementos_pago', function (Blueprint $table) {
            $table->string('estatus_cancelacion_pac', 30)->nullable()->after('codigo_estatus_cancelacion');
            $table->string('mensaje_cancelacion_pac', 500)->nullable()->after('estatus_cancelacion_pac');
            $table->string('is_cancelable', 80)->nullable()->after('mensaje_cancelacion_pac');
            $table->timestamp('fecha_solicitud_cancelacion')->nullable()->after('fecha_cancelacion');
            $table->timestamp('fecha_vencimiento_aceptacion')->nullable()->after('fecha_solicitud_cancelacion');
            $table->string('estatus_sat', 40)->nullable()->after('fecha_vencimiento_aceptacion');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn([
                'estatus_cancelacion_pac',
                'mensaje_cancelacion_pac',
                'is_cancelable',
                'fecha_solicitud_cancelacion',
                'fecha_vencimiento_aceptacion',
                'estatus_sat',
            ]);
        });

        Schema::table('complementos_pago', function (Blueprint $table) {
            $table->dropColumn([
                'estatus_cancelacion_pac',
                'mensaje_cancelacion_pac',
                'is_cancelable',
                'fecha_solicitud_cancelacion',
                'fecha_vencimiento_aceptacion',
                'estatus_sat',
            ]);
        });
    }
};
