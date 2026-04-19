<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('requiere_revision_precio')->default(false)->after('costo_promedio');
            $table->decimal('ultimo_costo', 15, 4)->nullable()->after('requiere_revision_precio');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['requiere_revision_precio', 'ultimo_costo']);
        });
    }
};
