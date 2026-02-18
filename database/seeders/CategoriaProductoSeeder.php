<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategoriaProductoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
        public function run(): void
    {
        collect([
            [
                'nombre' => 'Servicios',
                'codigo' => 'SERV',
                'color'  => '#0B3C5D',
                'orden'  => 1,
                'activo' => true,
            ],
            [
                'nombre' => 'Productos Terminados',
                'codigo' => 'PROD',
                'color'  => '#1D2731',
                'orden'  => 2,
                'activo' => true,
            ],
            [
                'nombre' => 'Insumos',
                'codigo' => 'INS',
                'color'  => '#CEAC41',
                'orden'  => 3,
                'activo' => true,
            ],
        ])->each(fn ($categoria) => \App\Models\CategoriaProducto::create($categoria));

        $this->command->info('✅ Categorías de productos creadas al estilo Laravel 12.');
    }

}
