<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->json('imagenes')->nullable()->after('imagen_principal');
        });

        // Migrar imagen_principal existente al arreglo imagenes (compatibilidad).
        if (Schema::hasColumn('productos', 'imagenes')) {
            foreach (DB::table('productos')->whereNotNull('imagen_principal')->cursor() as $row) {
                $principal = trim((string) ($row->imagen_principal ?? ''));
                if ($principal === '') {
                    continue;
                }
                DB::table('productos')->where('id', $row->id)->update([
                    'imagenes' => json_encode([$principal]),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('imagenes');
        });
    }
};
