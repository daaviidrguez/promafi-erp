<?php

namespace Database\Seeders;

// UBICACIÓN: database/seeders/ProductoSeeder.php
//
// Este seeder crea productos de ejemplo.
// Se ejecuta con: php artisan db:seed --class=ProductoSeeder

use Illuminate\Database\Seeder;
use App\Models\Producto;
use App\Models\CategoriaProducto;

class ProductoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener categorías
        $electronica = CategoriaProducto::where('codigo', 'ELEC')->first();
        $herramientas = CategoriaProducto::where('codigo', 'HERR')->first();
        $construccion = CategoriaProducto::where('codigo', 'CONST')->first();
        $industrial = CategoriaProducto::where('codigo', 'IND')->first();
        $servicios = CategoriaProducto::where('codigo', 'SERV')->first();

        $productos = [
            // Electrónica
            [
                'codigo' => 'ELEC-001',
                'codigo_barras' => '7501234567890',
                'nombre' => 'Multímetro Digital',
                'descripcion' => 'Multímetro digital profesional con pantalla LCD',
                'clave_sat' => '43211601', // Multímetros
                'clave_unidad_sat' => 'H87', // Pieza
                'unidad' => 'Pieza',
                'costo' => 450.00,
                'precio_venta' => 750.00,
                'precio_mayoreo' => 650.00,
                'stock' => 25,
                'stock_minimo' => 5,
                'categoria_id' => $electronica?->id,
                'activo' => true,
            ],
            [
                'codigo' => 'ELEC-002',
                'nombre' => 'Cable Calibre 12 AWG',
                'descripcion' => 'Cable eléctrico calibre 12 uso rudo',
                'clave_sat' => '26111702', // Cables eléctricos
                'clave_unidad_sat' => 'MTR', // Metro
                'unidad' => 'Metro',
                'costo' => 12.50,
                'precio_venta' => 22.00,
                'stock' => 500,
                'stock_minimo' => 100,
                'categoria_id' => $electronica?->id,
                'activo' => true,
            ],
            
            // Herramientas
            [
                'codigo' => 'HERR-001',
                'nombre' => 'Taladro Percutor 1/2"',
                'descripcion' => 'Taladro percutor profesional 850W',
                'clave_sat' => '27112101', // Taladros
                'clave_unidad_sat' => 'H87', // Pieza
                'unidad' => 'Pieza',
                'costo' => 1200.00,
                'precio_venta' => 1850.00,
                'precio_mayoreo' => 1650.00,
                'stock' => 15,
                'stock_minimo' => 3,
                'categoria_id' => $herramientas?->id,
                'activo' => true,
            ],
            [
                'codigo' => 'HERR-002',
                'nombre' => 'Juego de Llaves Allen',
                'descripcion' => 'Juego de 9 llaves allen métricas',
                'clave_sat' => '27111701', // Llaves
                'clave_unidad_sat' => 'SET', // Juego
                'unidad' => 'Juego',
                'costo' => 85.00,
                'precio_venta' => 165.00,
                'stock' => 40,
                'stock_minimo' => 10,
                'categoria_id' => $herramientas?->id,
                'activo' => true,
            ],
            
            // Construcción
            [
                'codigo' => 'CONST-001',
                'nombre' => 'Cemento Gris 50kg',
                'descripcion' => 'Cemento Portland gris uso general',
                'clave_sat' => '30111701', // Cemento
                'clave_unidad_sat' => 'KGM', // Kilogramo
                'unidad' => 'Bulto',
                'costo' => 145.00,
                'precio_venta' => 195.00,
                'stock' => 200,
                'stock_minimo' => 50,
                'categoria_id' => $construccion?->id,
                'activo' => true,
            ],
            [
                'codigo' => 'CONST-002',
                'nombre' => 'Varilla Corrugada 3/8"',
                'descripcion' => 'Varilla de acero corrugada 3/8" x 6m',
                'clave_sat' => '30121701', // Acero
                'clave_unidad_sat' => 'H87', // Pieza
                'unidad' => 'Pieza',
                'costo' => 65.00,
                'precio_venta' => 95.00,
                'stock' => 350,
                'stock_minimo' => 100,
                'categoria_id' => $construccion?->id,
                'activo' => true,
            ],
            
            // Industrial
            [
                'codigo' => 'IND-001',
                'nombre' => 'Banda Transportadora 1m',
                'descripcion' => 'Banda transportadora industrial 1m ancho',
                'clave_sat' => '24101516', // Bandas
                'clave_unidad_sat' => 'MTR', // Metro
                'unidad' => 'Metro',
                'costo' => 850.00,
                'precio_venta' => 1450.00,
                'stock' => 50,
                'stock_minimo' => 10,
                'categoria_id' => $industrial?->id,
                'activo' => true,
            ],
            
            // Servicios
            [
                'codigo' => 'SERV-001',
                'nombre' => 'Servicio de Instalación Eléctrica',
                'descripcion' => 'Instalación eléctrica residencial por punto',
                'clave_sat' => '81111501', // Servicios de instalación
                'clave_unidad_sat' => 'E48', // Servicio
                'unidad' => 'Servicio',
                'costo' => 0,
                'precio_venta' => 350.00,
                'stock' => 0,
                'stock_minimo' => 0,
                'controla_inventario' => false,
                'categoria_id' => $servicios?->id,
                'activo' => true,
            ],
            [
                'codigo' => 'SERV-002',
                'nombre' => 'Consultoría Técnica por Hora',
                'descripcion' => 'Asesoría técnica especializada',
                'clave_sat' => '80101501', // Consultoría
                'clave_unidad_sat' => 'HUR', // Hora
                'unidad' => 'Hora',
                'costo' => 0,
                'precio_venta' => 650.00,
                'stock' => 0,
                'stock_minimo' => 0,
                'controla_inventario' => false,
                'categoria_id' => $servicios?->id,
                'activo' => true,
            ],
        ];

        foreach ($productos as $producto) {
            Producto::create($producto);
        }

        echo "✅ " . count($productos) . " productos de ejemplo creados\n";
    }
}