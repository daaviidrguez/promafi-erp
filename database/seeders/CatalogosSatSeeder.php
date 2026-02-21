<?php

namespace Database\Seeders;

use App\Models\RegimenFiscal;
use App\Models\UsoCfdi;
use App\Models\FormaPago;
use App\Models\MetodoPago;
use App\Models\Moneda;
use App\Models\UnidadMedidaSat;
use App\Models\ClaveProdServicio;
use Illuminate\Database\Seeder;

class CatalogosSatSeeder extends Seeder
{
    public function run(): void
    {
        $regimenes = config('regimenes_fiscales', []);
        $orden = 0;
        foreach ($regimenes as $clave => $etiqueta) {
            $descripcion = preg_match('/^\d+\s*-\s*/', $etiqueta) ? trim(substr($etiqueta, strpos($etiqueta, '-') + 1)) : $etiqueta;
            RegimenFiscal::firstOrCreate(
                ['clave' => $clave],
                ['descripcion' => $descripcion, 'activo' => true, 'orden' => $orden++]
            );
        }

        $usos = [
            'G03' => 'Gastos en general',
            'P01' => 'Por definir',
            'S01' => 'Sin efectos fiscales',
            'D01' => 'Honorarios médicos',
            'D02' => 'Gastos médicos',
            'D03' => 'Gastos funerales',
            'D04' => 'Donativos',
            'I01' => 'Construcciones',
        ];
        $orden = 0;
        foreach ($usos as $clave => $desc) {
            UsoCfdi::firstOrCreate(
                ['clave' => $clave],
                ['descripcion' => $desc, 'activo' => true, 'orden' => $orden++]
            );
        }

        $formas = [
            '01' => 'Efectivo',
            '02' => 'Cheque nominativo',
            '03' => 'Transferencia electrónica',
            '04' => 'Tarjeta de crédito',
            '28' => 'Tarjeta de débito',
            '99' => 'Por definir',
        ];
        $orden = 0;
        foreach ($formas as $clave => $desc) {
            FormaPago::firstOrCreate(
                ['clave' => $clave],
                ['descripcion' => $desc, 'activo' => true, 'orden' => $orden++]
            );
        }

        MetodoPago::firstOrCreate(
            ['clave' => 'PUE'],
            ['descripcion' => 'Pago en una sola exhibición', 'activo' => true, 'orden' => 0]
        );
        MetodoPago::firstOrCreate(
            ['clave' => 'PPD'],
            ['descripcion' => 'Pago en parcialidades o diferido', 'activo' => true, 'orden' => 1]
        );

        Moneda::firstOrCreate(
            ['clave' => 'MXN'],
            ['descripcion' => 'Peso mexicano', 'activo' => true, 'orden' => 0]
        );
        Moneda::firstOrCreate(
            ['clave' => 'USD'],
            ['descripcion' => 'Dólar estadounidense', 'activo' => true, 'orden' => 1]
        );

        $unidades = [
            'H87' => 'Pieza',
            'E48' => 'Unidad de servicio',
            'EA' => 'Elemento',
            'KGM' => 'Kilogramo',
            'LTR' => 'Litro',
            'MTR' => 'Metro',
            'MTK' => 'Metro cuadrado',
            'MTQ' => 'Metro cúbico',
            'XUN' => 'Paquete',
        ];
        $orden = 0;
        foreach ($unidades as $clave => $desc) {
            UnidadMedidaSat::firstOrCreate(
                ['clave' => $clave],
                ['descripcion' => $desc, 'activo' => true, 'orden' => $orden++]
            );
        }

        // Algunas claves producto/servicio de ejemplo (el usuario cargará el catálogo completo por Excel)
        $clavesProd = [
            '01010101' => 'No existe en el catálogo',
            '43211601' => 'Multímetros',
            '26111702' => 'Cables eléctricos',
            '27112101' => 'Taladros',
            '80101501' => 'Consultoría',
            '81111501' => 'Servicios de instalación',
        ];
        $orden = 0;
        foreach ($clavesProd as $clave => $desc) {
            ClaveProdServicio::firstOrCreate(
                ['clave' => $clave],
                ['descripcion' => $desc, 'activo' => true, 'orden' => $orden++]
            );
        }
    }
}
