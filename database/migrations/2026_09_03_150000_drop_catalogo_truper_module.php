<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $keys = [
            'catalogo_truper.ver',
            'catalogo_truper.importar',
            'catalogo_truper.exportar',
        ];

        $ids = DB::table('permissions')->whereIn('key', $keys)->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }

        Schema::dropIfExists('catalogo_truper');
    }

    public function down(): void
    {
        // No se recrea el módulo: el catálogo Truper fue retirado a favor del catálogo de productos.
    }
};
