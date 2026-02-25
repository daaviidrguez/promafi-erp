<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('listas_precios_detalle')->get();
        foreach ($rows as $r) {
            $valor = (float) $r->valor_utilidad;
            $nuevo = 30;
            if ($r->tipo_utilidad === 'factorizado') {
                $nuevo = (int) round(($valor - 1) * 100);
            } else {
                $nuevo = (int) round((1 - $valor) * 100);
            }
            $nuevo = max(1, min(99, $nuevo));
            DB::table('listas_precios_detalle')->where('id', $r->id)->update(['valor_utilidad' => $nuevo]);
        }
    }

    public function down(): void
    {
        $rows = DB::table('listas_precios_detalle')->get();
        foreach ($rows as $r) {
            $pct = (float) $r->valor_utilidad / 100;
            $nuevo = $r->tipo_utilidad === 'factorizado' ? (1 + $pct) : (1 - $pct);
            DB::table('listas_precios_detalle')->where('id', $r->id)->update(['valor_utilidad' => $nuevo]);
        }
    }
};
