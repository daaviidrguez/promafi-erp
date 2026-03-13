<?php

namespace Database\Seeders;

// UBICACIÓN: database/seeders/DatabaseSeeder.php
// REEMPLAZA el contenido actual con este

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Llamar a los seeders en orden
        $this->call([
            UserSeeder::class,                // Usuarios
            EmpresaSeeder::class,             // Empresa (configuración)
            CategoriaProductoSeeder::class,   // Categorías
            CatalogosSatSeeder::class,        // Catálogos SAT (regímenes, usos CFDI, etc.)
            ProductoSeeder::class,            // Productos de ejemplo
        ]);
        
        echo "\n";
        echo "═══════════════════════════════════════════════════════════\n";
        echo "🎉 ¡Base de datos inicializada correctamente!\n";
        echo "═══════════════════════════════════════════════════════════\n";
        echo "\n";
        echo "👤 ACCESO AL SISTEMA:\n";
        echo "   Email: admin@promafi.mx\n";
        echo "   Password: promafi2026\n";
        echo "\n";
        echo "⚙️  CONFIGURACIÓN:\n";
        echo "   1. Accede a Dashboard → Configuración\n";
        echo "   2. Actualiza los datos fiscales de tu empresa\n";
        echo "   3. Configura credenciales de Facturama (sandbox o producción)\n";
        echo "\n";
        echo "📦 DATOS DE EJEMPLO:\n";
        echo "   • 3 usuarios creados\n";
        echo "   • 1 empresa de ejemplo\n";
        echo "   • 5 categorías de productos\n";
        echo "   • 10 productos de ejemplo\n";
        echo "\n";
        echo "🚀 LISTO PARA USAR:\n";
        echo "   php artisan serve\n";
        echo "   http://localhost:8000\n";
        echo "\n";
    }
}