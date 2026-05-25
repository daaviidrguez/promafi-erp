<?php

use App\Services\ProveedorCodigoGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('proveedores', 'codigo')) {
            Schema::table('proveedores', function (Blueprint $table) {
                $table->string('codigo', 20)->unique()->nullable()->after('id');
            });
        }

        DB::table('proveedores')
            ->orderBy('id')
            ->lazyById()
            ->each(function ($proveedor) {
                DB::table('proveedores')
                    ->where('id', $proveedor->id)
                    ->update([
                        'codigo' => ProveedorCodigoGenerator::fromId((int) $proveedor->id),
                    ]);
            });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('codigo', 20)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('codigo', 20)->nullable()->change();
        });
    }
};
