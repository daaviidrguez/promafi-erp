<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Factura;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FacturaFolioLiberacionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $clienteId;

    private int $empresaId;

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();

        $this->empresaId = DB::table('empresas')->insertGetId([
            'rfc' => 'EKU9003173C9',
            'razon_social' => 'EMPRESA PRUEBA',
            'regimen_fiscal' => '601',
            'calle' => 'Calle Prueba',
            'numero_exterior' => '100',
            'colonia' => 'Centro',
            'estado' => 'Nuevo León',
            'codigo_postal' => '64000',
            'serie_factura_credito' => 'FB',
            'folio_factura_credito' => 1,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->clienteId = DB::table('clientes')->insertGetId([
            'codigo' => 'C0001',
            'nombre' => 'CLIENTE PRUEBA',
            'rfc' => 'XIA190128J61',
            'regimen_fiscal' => '601',
            'codigo_postal' => '64000',
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('formas_pago')->insert(['clave' => '01', 'descripcion' => 'Efectivo', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('metodos_pago')->insert(['clave' => 'PUE', 'descripcion' => 'Pago en una sola exhibición', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('usos_cfdi')->insert(['clave' => 'G03', 'descripcion' => 'Gastos en general', 'created_at' => $now, 'updated_at' => $now]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => 'secret123',
            'role_id' => DB::table('roles')->where('name', 'admin')->value('id'),
            'activo' => true,
        ]);
    }

    private function crearBorrador(): Factura
    {
        $this->actingAs($this->admin)
            ->post(route('facturas.store'), [
                'cliente_id' => $this->clienteId,
                'fecha_emision' => now()->toDateString(),
                'forma_pago' => '01',
                'metodo_pago' => 'PUE',
                'uso_cfdi' => 'G03',
                'observaciones' => null,
                'productos' => [
                    ['descripcion' => 'Servicio de prueba', 'cantidad' => 1, 'valor_unitario' => 100, 'descuento' => 0],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error')
            ->assertRedirect();

        return Factura::latest('id')->firstOrFail();
    }

    private function eliminar(Factura $factura): void
    {
        $this->actingAs($this->admin)
            ->delete(route('facturas.destroy', $factura))
            ->assertRedirect(route('facturas.index'))
            ->assertSessionMissing('error');
    }

    private function contador(): int
    {
        return (int) DB::table('empresas')->where('id', $this->empresaId)->value('folio_factura_credito');
    }

    public function test_eliminar_ultimo_borrador_regresa_el_contador_y_se_reutiliza_el_folio(): void
    {
        $this->crearBorrador();
        $segunda = $this->crearBorrador();
        $this->assertSame(2, $segunda->folio);
        $this->assertSame(3, $this->contador());

        $this->eliminar($segunda);
        $this->assertSame(2, $this->contador());

        $nueva = $this->crearBorrador();
        $this->assertSame(2, $nueva->folio);
        $this->assertSame(3, $this->contador());
    }

    public function test_eliminar_borrador_intermedio_reutiliza_el_hueco_antes_de_avanzar(): void
    {
        $this->crearBorrador();
        $segunda = $this->crearBorrador();
        $this->crearBorrador();

        $this->eliminar($segunda);
        $this->assertSame(4, $this->contador());
        $this->assertDatabaseHas('folios_factura_liberados', ['serie' => 'FB', 'folio' => 2]);

        $this->assertSame(2, $this->crearBorrador()->folio);
        $this->assertDatabaseMissing('folios_factura_liberados', ['serie' => 'FB', 'folio' => 2]);
        $this->assertSame(4, $this->crearBorrador()->folio);
    }

    public function test_eliminar_intermedio_y_luego_el_ultimo_regresa_el_contador_en_cascada(): void
    {
        $this->crearBorrador();
        $segunda = $this->crearBorrador();
        $tercera = $this->crearBorrador();

        $this->eliminar($segunda);
        $this->eliminar($tercera);

        $this->assertSame(2, $this->contador());
        $this->assertSame(0, DB::table('folios_factura_liberados')->count());
        $this->assertSame(2, $this->crearBorrador()->folio);
        $this->assertSame(3, $this->crearBorrador()->folio);
    }

    public function test_contador_apuntando_a_folio_ocupado_salta_al_siguiente_libre(): void
    {
        $this->crearBorrador();
        $this->crearBorrador();
        DB::table('empresas')->where('id', $this->empresaId)->update(['folio_factura_credito' => 1]);

        $nueva = $this->crearBorrador();

        $this->assertSame(3, $nueva->folio);
        $this->assertSame(4, $this->contador());
    }

    public function test_si_cambio_la_serie_no_se_mueve_el_contador_de_la_serie_actual(): void
    {
        $borrador = $this->crearBorrador();
        DB::table('empresas')->where('id', $this->empresaId)->update(['serie_factura_credito' => 'FC', 'folio_factura_credito' => 10]);

        $this->eliminar($borrador);

        $this->assertSame(10, $this->contador());
        $this->assertSame(10, $this->crearBorrador()->folio);
    }

    public function test_pantalla_de_crear_muestra_el_folio_liberado(): void
    {
        $this->crearBorrador();
        $segunda = $this->crearBorrador();
        $this->crearBorrador();
        $this->eliminar($segunda);

        $this->assertSame('FB-0002', Empresa::principal()->obtenerSiguienteFolioFacturaCredito());
        $this->actingAs($this->admin)
            ->get(route('facturas.create'))
            ->assertOk()
            ->assertSee('FB-0002');
    }
}
