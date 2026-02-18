<?php

namespace Database\Seeders;

// UBICACIÓN: database/seeders/UserSeeder.php
//
// Este seeder crea el usuario administrador inicial.
// Se ejecuta con: php artisan db:seed --class=UserSeeder

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear usuario administrador
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@promafi.mx',
            'password' => Hash::make('promafi2026'), // Cambiar en producción
            'role' => 'admin',
            'activo' => true,
        ]);

        // Crear usuario de prueba vendedor
        User::create([
            'name' => 'Vendedor Demo',
            'email' => 'vendedor@promafi.mx',
            'password' => Hash::make('12345678'),
            'role' => 'vendedor',
            'activo' => true,
        ]);

        // Crear usuario contador
        User::create([
            'name' => 'Contador Demo',
            'email' => 'contador@promafi.mx',
            'password' => Hash::make('12345678'),
            'role' => 'contador',
            'activo' => true,
        ]);

        echo "✅ Usuarios creados exitosamente\n";
        echo "   Email: admin@promafi.mx\n";
        echo "   Password: promafi2026\n";
    }
}