<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isr_resico_tasas', function (Blueprint $table) {
            $table->id();
            $table->decimal('desde', 15, 2);
            $table->decimal('hasta', 15, 2);
            $table->decimal('tasa', 8, 4); // 0.01 = 1%
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        DB::table('isr_resico_tasas')->insert([
            ['desde' => 0, 'hasta' => 25000, 'tasa' => 0.01, 'orden' => 1],
            ['desde' => 25000, 'hasta' => 50000, 'tasa' => 0.011, 'orden' => 2],
            ['desde' => 50000, 'hasta' => 83333, 'tasa' => 0.015, 'orden' => 3],
            ['desde' => 83333, 'hasta' => 208333, 'tasa' => 0.02, 'orden' => 4],
            ['desde' => 208333, 'hasta' => 3500000, 'tasa' => 0.025, 'orden' => 5],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('isr_resico_tasas');
    }
};
