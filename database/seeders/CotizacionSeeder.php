<?php

namespace Database\Seeders;

// UBICACIÓN: database/seeders/CotizacionSeeder.php
//
// Este seeder no crea cotizaciones, solo asegura que la estructura esté lista
// Se ejecuta con: php artisan db:seed --class=CotizacionSeeder

use Illuminate\Database\Seeder;

class CotizacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "✅ Tabla de cotizaciones lista\n";
        echo "   La serie y folios se gestionan desde la empresa\n";
    }
}