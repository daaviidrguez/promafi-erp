<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('numero_exterior', 20)->nullable()->change();
            $table->string('numero_interior', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('numero_exterior', 10)->nullable()->change();
            $table->string('numero_interior', 10)->nullable()->change();
        });
    }
};
