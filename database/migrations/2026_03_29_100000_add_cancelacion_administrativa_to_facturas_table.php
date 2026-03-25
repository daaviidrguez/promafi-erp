<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->boolean('cancelacion_administrativa')->default(false)->after('codigo_estatus_cancelacion');
            $table->text('cancelacion_administrativa_motivo')->nullable()->after('cancelacion_administrativa');
            $table->timestamp('cancelacion_administrativa_at')->nullable()->after('cancelacion_administrativa_motivo');
            $table->foreignId('cancelacion_administrativa_user_id')->nullable()->after('cancelacion_administrativa_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropForeign(['cancelacion_administrativa_user_id']);
            $table->dropColumn([
                'cancelacion_administrativa',
                'cancelacion_administrativa_motivo',
                'cancelacion_administrativa_at',
                'cancelacion_administrativa_user_id',
            ]);
        });
    }
};
