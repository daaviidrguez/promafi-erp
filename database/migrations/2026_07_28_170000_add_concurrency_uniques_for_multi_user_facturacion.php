<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si hay varias facturas por la misma cotización, conservar la más antigua
        // y desvincular el resto para poder aplicar unique nullable.
        $duplicadas = DB::table('facturas')
            ->select('cotizacion_id', DB::raw('MIN(id) as keep_id'))
            ->whereNotNull('cotizacion_id')
            ->groupBy('cotizacion_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicadas as $dup) {
            DB::table('facturas')
                ->where('cotizacion_id', $dup->cotizacion_id)
                ->where('id', '!=', $dup->keep_id)
                ->update(['cotizacion_id' => null]);
        }

        Schema::table('facturas', function (Blueprint $table) {
            $table->unique('cotizacion_id');
        });

        // folio_interno duplicado: conservar el más antiguo y regenerar el resto.
        $foliosDup = DB::table('facturas_compra')
            ->select('folio_interno', DB::raw('MIN(id) as keep_id'))
            ->whereNotNull('folio_interno')
            ->groupBy('folio_interno')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($foliosDup as $dup) {
            $conflictoIds = DB::table('facturas_compra')
                ->where('folio_interno', $dup->folio_interno)
                ->where('id', '!=', $dup->keep_id)
                ->orderBy('id')
                ->pluck('id');

            foreach ($conflictoIds as $id) {
                DB::table('facturas_compra')
                    ->where('id', $id)
                    ->update(['folio_interno' => 'EM-TMP-'.$id]);
            }
        }

        // Reasignar temporales a EM- consecutivos libres
        $temporales = DB::table('facturas_compra')
            ->where('folio_interno', 'like', 'EM-TMP-%')
            ->orderBy('id')
            ->get(['id']);

        if ($temporales->isNotEmpty()) {
            $max = 0;
            foreach (DB::table('facturas_compra')->whereNotNull('folio_interno')->where('folio_interno', 'like', 'EM-%')->pluck('folio_interno') as $f) {
                if (preg_match('/^EM-(\d{4})$/', (string) $f, $m)) {
                    $max = max($max, (int) $m[1]);
                } elseif (preg_match('/^EM-\d{4}-(\d{4})$/', (string) $f, $m)) {
                    $max = max($max, (int) $m[1]);
                }
            }

            foreach ($temporales as $row) {
                $max++;
                DB::table('facturas_compra')
                    ->where('id', $row->id)
                    ->update(['folio_interno' => 'EM-'.str_pad((string) $max, 4, '0', STR_PAD_LEFT)]);
            }
        }

        Schema::table('facturas_compra', function (Blueprint $table) {
            $table->unique('folio_interno');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropUnique(['cotizacion_id']);
        });

        Schema::table('facturas_compra', function (Blueprint $table) {
            $table->dropUnique(['folio_interno']);
        });
    }
};
