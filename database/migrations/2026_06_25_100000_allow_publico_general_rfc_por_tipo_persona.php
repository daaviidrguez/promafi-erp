<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite XAXX010101000 una vez por tipo de persona; el resto de RFC sigue siendo único.
     */
    public function up(): void
    {
        if ($this->hasIndex('clientes', 'clientes_rfc_unique')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropUnique(['rfc']);
            });
        }

        if (! Schema::hasColumn('clientes', 'rfc_unique_key')) {
            $this->addRfcUniqueKeyColumn();
        }

        if (! $this->hasIndex('clientes', 'clientes_rfc_unique_key_unique')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->unique('rfc_unique_key');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('clientes', 'clientes_rfc_unique_key_unique')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropUnique(['rfc_unique_key']);
            });
        }

        if (Schema::hasColumn('clientes', 'rfc_unique_key')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropColumn('rfc_unique_key');
            });
        }

        if (! $this->hasIndex('clientes', 'clientes_rfc_unique')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->unique('rfc');
            });
        }
    }

    private function addRfcUniqueKeyColumn(): void
    {
        $version = DB::selectOne('SELECT VERSION() as version')->version ?? '';
        $isMariaDb = stripos($version, 'mariadb') !== false;

        $expression = "CASE WHEN rfc = 'XAXX010101000' THEN CONCAT(rfc, '-', tipo_persona) ELSE rfc END";

        if ($isMariaDb) {
            DB::statement(
                "ALTER TABLE clientes ADD COLUMN rfc_unique_key VARCHAR(25) AS ({$expression}) PERSISTENT"
            );

            return;
        }

        DB::statement(
            "ALTER TABLE clientes ADD COLUMN rfc_unique_key VARCHAR(25) GENERATED ALWAYS AS ({$expression}) STORED"
        );
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))
            ->pluck('Key_name')
            ->contains($indexName);
    }
};
