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
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['rfc']);
        });

        DB::statement(
            "CREATE UNIQUE INDEX clientes_rfc_unique ON clientes ((
                CASE
                    WHEN rfc = 'XAXX010101000' THEN CONCAT(rfc, '-', tipo_persona)
                    ELSE rfc
                END
            ))"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX clientes_rfc_unique ON clientes');

        Schema::table('clientes', function (Blueprint $table) {
            $table->unique('rfc');
        });
    }
};
