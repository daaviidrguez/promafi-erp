<?php

namespace Database\Seeders;

// UBICACIÓN: database/seeders/EmpresaSeeder.php
// Este seeder crea una empresa ejemplo.
// Se ejecuta con: php artisan db:seed --class=EmpresaSeeder

use Illuminate\Database\Seeder;
use App\Models\Empresa;

class EmpresaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar si ya existe una empresa
        if (Empresa::count() > 0) {
            echo "⚠️  Ya existe una empresa registrada\n";
            return;
        }

        Empresa::create([
            // Datos fiscales
            'rfc' => 'XAXX010101000',
            'razon_social' => 'EMPRESA EJEMPLO SA DE CV',
            'nombre_comercial' => 'Mi Empresa',
            'regimen_fiscal' => '601',
            
            // Domicilio
            'calle' => 'Av. Principal',
            'numero_exterior' => '123',
            'colonia' => 'Centro',
            'municipio' => 'Ciudad',
            'estado' => 'Estado',
            'codigo_postal' => '01000',
            'pais' => 'MEX',
            
            // Contacto
            'email' => 'contacto@ejemplo.com',
            'telefono' => '5555555555',
            
            // Facturación
            'serie_factura' => 'A',
            'folio_factura' => 1,
            
            // PAC (modo prueba por defecto)
            'pac_modo_prueba' => true,
        ]);

        echo "✅ Empresa de ejemplo creada\n";
        echo "\n";
        echo "🔧 IMPORTANTE: Actualiza los datos de la empresa en:\n";
        echo "   Dashboard → Configuración\n";
        echo "\n";
        echo "📋 RFC: XAXX010101000 (genérico - cámbialo)\n";
        echo "🧾 Serie: A | Folio inicial: 1\n";
        echo "🔐 Modo prueba: ACTIVADO (timbrado genera UUID fake)\n";
        echo "\n";
    }
}