<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si la tabla ya nació con periodo (instalación limpia), no hay nada que migrar.
        if (! Schema::hasColumn('cliente_metas_comerciales', 'mes')) {
            return;
        }

        if (! Schema::hasColumn('cliente_metas_comerciales', 'periodo')) {
            Schema::table('cliente_metas_comerciales', function (Blueprint $table) {
                $table->string('periodo', 20)->default('anual')->after('anio');
            });
        }

        // mes = 0 → anual; cualquier mes 1–12 → mensual (monto fijo del año)
        DB::table('cliente_metas_comerciales')->orderBy('id')->each(function ($row) {
            DB::table('cliente_metas_comerciales')
                ->where('id', $row->id)
                ->update([
                    'periodo' => ((int) $row->mes === 0) ? 'anual' : 'mensual',
                ]);
        });

        // Si quedaron duplicados anual/mensual por cliente+año, conserva el más reciente
        $duplicados = DB::table('cliente_metas_comerciales')
            ->select('cliente_id', 'anio', 'periodo', DB::raw('MAX(id) as keep_id'), DB::raw('COUNT(*) as total'))
            ->groupBy('cliente_id', 'anio', 'periodo')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicados as $dup) {
            DB::table('cliente_metas_comerciales')
                ->where('cliente_id', $dup->cliente_id)
                ->where('anio', $dup->anio)
                ->where('periodo', $dup->periodo)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        // La FK de cliente_id usa el unique compuesto; crear índice propio antes de soltarlo.
        Schema::table('cliente_metas_comerciales', function (Blueprint $table) {
            $table->index('cliente_id', 'cliente_metas_cliente_id_index');
        });

        Schema::table('cliente_metas_comerciales', function (Blueprint $table) {
            $table->dropUnique('cliente_metas_unicas');
        });

        if (Schema::hasIndex('cliente_metas_comerciales', 'cliente_metas_comerciales_anio_mes_index')) {
            Schema::table('cliente_metas_comerciales', function (Blueprint $table) {
                $table->dropIndex('cliente_metas_comerciales_anio_mes_index');
            });
        }

        Schema::table('cliente_metas_comerciales', function (Blueprint $table) {
            $table->dropColumn('mes');
            $table->unique(['cliente_id', 'anio', 'periodo'], 'cliente_metas_unicas');
            $table->index(['anio', 'periodo'], 'cliente_metas_comerciales_anio_periodo_index');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('cliente_metas_comerciales', 'mes')) {
            return;
        }

        Schema::table('cliente_metas_comerciales', function (Blueprint $table) {
            $table->dropUnique('cliente_metas_unicas');
            if (Schema::hasIndex('cliente_metas_comerciales', 'cliente_metas_comerciales_anio_periodo_index')) {
                $table->dropIndex('cliente_metas_comerciales_anio_periodo_index');
            }
            $table->unsignedTinyInteger('mes')->default(0)->after('anio');
        });

        if (Schema::hasColumn('cliente_metas_comerciales', 'periodo')) {
            DB::table('cliente_metas_comerciales')->orderBy('id')->each(function ($row) {
                DB::table('cliente_metas_comerciales')
                    ->where('id', $row->id)
                    ->update([
                        'mes' => $row->periodo === 'mensual' ? 1 : 0,
                    ]);
            });

            Schema::table('cliente_metas_comerciales', function (Blueprint $table) {
                $table->dropColumn('periodo');
            });
        }

        Schema::table('cliente_metas_comerciales', function (Blueprint $table) {
            $table->unique(['cliente_id', 'anio', 'mes'], 'cliente_metas_unicas');
            $table->index(['anio', 'mes'], 'cliente_metas_comerciales_anio_mes_index');
        });
    }
};
