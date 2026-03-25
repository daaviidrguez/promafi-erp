<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas_compra', function (Blueprint $table) {
            $table->string('folio_interno', 20)->nullable()->after('folio');
        });

        // Compras manuales históricas: folio ya era EM-xxxx
        DB::table('facturas_compra')
            ->whereNull('folio_interno')
            ->whereNotNull('folio')
            ->where('folio', 'like', 'EM-%')
            ->update(['folio_interno' => DB::raw('folio')]);

        $max = 0;
        foreach (DB::table('facturas_compra')->whereNotNull('folio_interno')->pluck('folio_interno') as $f) {
            if (preg_match('/^EM-(\d{4})$/', $f, $m)) {
                $max = max($max, (int) $m[1]);
            } elseif (preg_match('/^EM-\d{4}-(\d{4})$/', $f, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        $next = $max + 1;
        foreach (DB::table('facturas_compra')->whereNull('folio_interno')->orderBy('id')->get(['id']) as $row) {
            $interno = 'EM-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            DB::table('facturas_compra')->where('id', $row->id)->update(['folio_interno' => $interno]);
            $next++;
        }
    }

    public function down(): void
    {
        Schema::table('facturas_compra', function (Blueprint $table) {
            $table->dropColumn('folio_interno');
        });
    }
};
