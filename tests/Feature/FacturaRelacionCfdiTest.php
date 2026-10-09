<?php

namespace Tests\Feature;

use App\Models\Factura;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FacturaRelacionCfdiTest extends TestCase
{
    use RefreshDatabase;

    private const UUID_A = 'aaaaaaaa-1111-2222-3333-444444444444';

    private const UUID_B = 'bbbbbbbb-5555-6666-7777-888888888888';

    private User $admin;

    private int $clienteId;

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();

        DB::table('empresas')->insert([
            'rfc' => 'EKU9003173C9',
            'razon_social' => 'EMPRESA PRUEBA',
            'regimen_fiscal' => '601',
            'calle' => 'Calle Prueba',
            'numero_exterior' => '100',
            'colonia' => 'Centro',
            'estado' => 'Nuevo León',
            'codigo_postal' => '64000',
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

    private function payload(array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $this->clienteId,
            'fecha_emision' => now()->toDateString(),
            'forma_pago' => '01',
            'metodo_pago' => 'PUE',
            'uso_cfdi' => 'G03',
            'observaciones' => null,
            'tipo_relacion' => '04',
            'productos' => [
                ['descripcion' => 'Servicio de prueba', 'cantidad' => 1, 'valor_unitario' => 100, 'descuento' => 0],
            ],
        ], $extra);
    }

    private function crearBorrador(array $extra = []): Factura
    {
        $this->actingAs($this->admin)
            ->post(route('facturas.store'), $this->payload($extra))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return Factura::latest('id')->firstOrFail();
    }

    public function test_crear_sin_relacion_y_relacionar_al_editar_persiste(): void
    {
        $factura = $this->crearBorrador(['uuid_referencia' => '']);

        $this->assertSame('borrador', $factura->estado);
        $this->assertNull($factura->uuid_referencia);
        $this->assertNull($factura->tipo_relacion);

        $this->actingAs($this->admin)
            ->put(route('facturas.update', $factura), $this->payload([
                'sustituir_cfdi' => '1',
                'uuid_referencia' => self::UUID_A,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('facturas.show', $factura));

        $factura->refresh();
        $this->assertSame(self::UUID_A, $factura->uuid_referencia);
        $this->assertSame('04', $factura->tipo_relacion);
    }

    public function test_crear_con_relacion_y_editar_sin_tocarla_se_conserva(): void
    {
        $factura = $this->crearBorrador([
            'sustituir_cfdi' => '1',
            'uuid_referencia' => self::UUID_A,
        ]);

        $this->assertSame(self::UUID_A, $factura->uuid_referencia);
        $this->assertSame('04', $factura->tipo_relacion);

        $this->actingAs($this->admin)
            ->get(route('facturas.edit', $factura))
            ->assertOk()
            ->assertSee('id="checkSustituirCfdi" name="sustituir_cfdi" value="1"', false)
            ->assertSee('value="'.self::UUID_A.'"', false);

        $this->actingAs($this->admin)
            ->put(route('facturas.update', $factura), $this->payload([
                'sustituir_cfdi' => '1',
                'uuid_referencia' => self::UUID_A,
                'observaciones' => 'Cambio de observaciones',
            ]))
            ->assertSessionHasNoErrors();

        $factura->refresh();
        $this->assertSame(self::UUID_A, $factura->uuid_referencia);
        $this->assertSame('04', $factura->tipo_relacion);
        $this->assertSame('Cambio de observaciones', $factura->observaciones);
    }

    public function test_agregar_otro_uuid_al_editar_normaliza_y_persiste(): void
    {
        $factura = $this->crearBorrador([
            'sustituir_cfdi' => '1',
            'uuid_referencia' => self::UUID_A,
        ]);

        $this->actingAs($this->admin)
            ->put(route('facturas.update', $factura), $this->payload([
                'sustituir_cfdi' => '1',
                'uuid_referencia' => ' '.self::UUID_A.',  '.self::UUID_B.', '.self::UUID_A,
            ]))
            ->assertSessionHasNoErrors();

        $factura->refresh();
        $this->assertSame(self::UUID_A.', '.self::UUID_B, $factura->uuid_referencia);
        $this->assertSame('04', $factura->tipo_relacion);
    }

    public function test_quitar_relacion_al_editar_la_elimina(): void
    {
        $factura = $this->crearBorrador([
            'sustituir_cfdi' => '1',
            'uuid_referencia' => self::UUID_A,
        ]);

        $this->actingAs($this->admin)
            ->put(route('facturas.update', $factura), $this->payload([
                'sustituir_cfdi' => '1',
                'uuid_referencia' => '',
            ]))
            ->assertSessionHasNoErrors();

        $factura->refresh();
        $this->assertNull($factura->uuid_referencia);
        $this->assertNull($factura->tipo_relacion);
    }

    public function test_desmarcar_casilla_al_editar_elimina_relacion_aunque_llegue_uuid(): void
    {
        $factura = $this->crearBorrador([
            'sustituir_cfdi' => '1',
            'uuid_referencia' => self::UUID_A,
        ]);

        $this->actingAs($this->admin)
            ->put(route('facturas.update', $factura), $this->payload([
                'uuid_referencia' => self::UUID_A,
            ]))
            ->assertSessionHasNoErrors();

        $factura->refresh();
        $this->assertNull($factura->uuid_referencia);
        $this->assertNull($factura->tipo_relacion);
    }
}
