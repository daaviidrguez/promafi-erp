<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complementos_pago', function (Blueprint $table) {
            $table->string('pac_cfdi_id', 100)->nullable()->after('uuid');
            $table->timestamp('fecha_cancelacion')->nullable();
            $table->text('acuse_cancelacion')->nullable()->after('fecha_cancelacion');
            $table->string('codigo_estatus_cancelacion', 20)->nullable()->after('acuse_cancelacion');
            $table->string('motivo_cancelacion', 2)->nullable()->after('codigo_estatus_cancelacion');
            $table->string('uuid_referencia', 36)->nullable()->after('motivo_cancelacion');
            $table->string('tipo_relacion', 2)->nullable()->after('uuid_referencia');
        });
    }

    public function down(): void
    {
        Schema::table('complementos_pago', function (Blueprint $table) {
            $table->dropColumn([
                'pac_cfdi_id',
                'fecha_cancelacion',
                'acuse_cancelacion',
                'codigo_estatus_cancelacion',
                'motivo_cancelacion',
                'uuid_referencia',
                'tipo_relacion',
            ]);
        });
    }
};
