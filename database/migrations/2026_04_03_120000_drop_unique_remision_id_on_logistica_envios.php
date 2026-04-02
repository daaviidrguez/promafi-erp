<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistica_envios', function (Blueprint $table) {
            $table->dropForeign(['remision_id']);
        });
        Schema::table('logistica_envios', function (Blueprint $table) {
            $table->dropUnique(['remision_id']);
        });
        Schema::table('logistica_envios', function (Blueprint $table) {
            $table->foreign('remision_id')->references('id')->on('remisiones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('logistica_envios', function (Blueprint $table) {
            $table->dropForeign(['remision_id']);
        });
        Schema::table('logistica_envios', function (Blueprint $table) {
            $table->unique('remision_id');
        });
        Schema::table('logistica_envios', function (Blueprint $table) {
            $table->foreign('remision_id')->references('id')->on('remisiones')->nullOnDelete();
        });
    }
};
