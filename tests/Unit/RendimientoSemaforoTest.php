<?php

namespace Tests\Unit;

use App\Support\Rendimiento\RendimientoSemaforo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RendimientoSemaforoTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function datos(float $segundos, int $consultas, float $sqlMs, array $extra = []): array
    {
        return $extra + [
            'segundos' => $segundos,
            'status' => 200,
            'sql_consultas' => $consultas,
            'sql_total_ms' => $sqlMs,
            'sql_lentas' => 0,
            'http_externas' => [],
        ];
    }

    /**
     * @param  array{motivos: list<array{clave: string}>}  $semaforo
     * @return list<string>
     */
    private function claves(array $semaforo): array
    {
        return array_column($semaforo['motivos'], 'clave');
    }

    #[Test]
    public function peticion_con_muchas_consultas_es_roja_y_prioriza_la_causa_sobre_el_tiempo(): void
    {
        $s = RendimientoSemaforo::peticion($this->datos(10.8, 1091, 8900.0));

        $this->assertSame(RendimientoSemaforo::ROJO, $s['nivel']);
        $this->assertSame(['consultas', 'tiempo'], $this->claves($s));
        $this->assertSame('1,091 consultas SQL en una sola petición', $s['motivos'][0]['texto']);
        $this->assertStringContainsString('82% del tiempo fue SQL', $s['motivos'][1]['accion']);
    }

    #[Test]
    public function peticion_lenta_por_php_es_amarilla_y_lo_indica(): void
    {
        $s = RendimientoSemaforo::peticion($this->datos(6.7, 366, 214.0));

        $this->assertSame(RendimientoSemaforo::AMARILLO, $s['nivel']);
        $this->assertSame(['consultas', 'tiempo'], $this->claves($s));
        $this->assertStringContainsString('Solo el 3% fue SQL', $s['motivos'][1]['accion']);
    }

    #[Test]
    public function api_externa_fallida_o_error_5xx_son_rojos(): void
    {
        $api = RendimientoSemaforo::peticion($this->datos(6.0, 10, 50.0, [
            'http_externas' => [
                ['host' => 'api.facturama.mx', 'metodo' => 'POST', 'ms' => null, 'error' => 'cURL error 28'],
                ['host' => 'maps.googleapis.com', 'metodo' => 'GET', 'ms' => 5200.0, 'error' => null],
            ],
        ]));
        $this->assertSame(RendimientoSemaforo::ROJO, $api['nivel']);
        $this->assertSame('Falló la API externa api.facturama.mx', $api['motivos'][0]['texto']);
        $this->assertContains('api:maps.googleapis.com', $this->claves($api));

        $error = RendimientoSemaforo::peticion($this->datos(5.5, 10, 50.0, ['status' => 500]));
        $this->assertSame(RendimientoSemaforo::ROJO, $error['nivel']);
        $this->assertSame('http_error', $error['motivos'][0]['clave']);
    }

    #[Test]
    public function ruta_toma_el_peor_motivo_de_sus_peticiones_y_suma_reglas_propias(): void
    {
        $peticiones = [
            RendimientoSemaforo::peticion($this->datos(5.9, 1090, 4000.0)),
            RendimientoSemaforo::peticion($this->datos(10.8, 1091, 8900.0)),
        ];

        $s = RendimientoSemaforo::ruta(['ruta' => 'api.alertas.bootstrap', 'veces' => 6, 'promedio' => 8.4], $peticiones, true, 2);

        $this->assertSame(RendimientoSemaforo::ROJO, $s['nivel']);
        $this->assertSame(['consultas', 'tiempo', 'saturacion', 'promedio', 'frecuencia'], $this->claves($s));
        $this->assertSame('1,091 consultas SQL en una sola petición', $s['motivos'][0]['texto']);
        $this->assertSame('Tardó 10.8 s', $s['motivos'][1]['texto']);
    }

    #[Test]
    public function ruta_aislada_y_moderada_queda_en_amarillo(): void
    {
        $s = RendimientoSemaforo::ruta(
            ['ruta' => 'reportes.x', 'veces' => 2, 'promedio' => 6.8],
            [RendimientoSemaforo::peticion($this->datos(6.8, 100, 200.0))],
            false,
            14,
        );

        $this->assertSame(RendimientoSemaforo::AMARILLO, $s['nivel']);
        $this->assertSame(['tiempo'], $this->claves($s));
    }

    #[Test]
    public function periodo_sin_registros_es_verde_y_sin_prioridad(): void
    {
        $s = RendimientoSemaforo::global([], [], 0, [], 14);

        $this->assertSame(RendimientoSemaforo::VERDE, $s['nivel']);
        $this->assertSame([], $s['motivos']);
        $this->assertNull($s['prioridad']);
    }

    #[Test]
    public function periodo_con_ruta_roja_es_amarillo_y_sugiere_por_donde_empezar(): void
    {
        $roja = RendimientoSemaforo::ruta(
            ['ruta' => 'api.alertas.bootstrap', 'veces' => 1, 'promedio' => 7.9],
            [RendimientoSemaforo::peticion($this->datos(7.9, 1091, 6000.0))],
            false,
            14,
        );
        $amarilla = RendimientoSemaforo::ruta(
            ['ruta' => 'reportes.x', 'veces' => 1, 'promedio' => 7.5],
            [RendimientoSemaforo::peticion($this->datos(7.5, 10, 100.0))],
            false,
            14,
        );
        $resumen = [
            ['ruta' => 'reportes.x', 'semaforo' => $amarilla],
            ['ruta' => 'api.alertas.bootstrap', 'semaforo' => $roja],
        ];

        $s = RendimientoSemaforo::global([['datos' => []], ['datos' => []]], [], 0, $resumen, 14);

        $this->assertSame(RendimientoSemaforo::AMARILLO, $s['nivel']);
        $this->assertSame(['rutas_rojas'], $this->claves($s));
        $this->assertSame('api.alertas.bootstrap', $s['prioridad']['ruta']);
        $this->assertSame('consultas', $s['prioridad']['motivo']['clave']);
    }

    #[Test]
    public function periodo_con_saturacion_o_errores_de_conexion_es_rojo(): void
    {
        $errores = [['datos' => ['codigo' => 1203, 'tipo' => 'Demasiadas conexiones del usuario']]];

        $s = RendimientoSemaforo::global([], $errores, 2, [], 1);

        $this->assertSame(RendimientoSemaforo::ROJO, $s['nivel']);
        $this->assertSame(['saturacion', 'mysql'], $this->claves($s));
        $this->assertSame('1 error de MySQL: Demasiadas conexiones del usuario', $s['motivos'][1]['texto']);
        $this->assertStringContainsString('max_user_connections', $s['motivos'][1]['accion']);
    }

    #[Test]
    public function peticion_cortada_por_memoria_es_roja_y_lo_explica_primero(): void
    {
        $s = RendimientoSemaforo::peticion($this->datos(3.2, 40, 300.0, [
            'status' => 500,
            'error_fatal' => 'Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes)',
        ]));

        $this->assertSame(RendimientoSemaforo::ROJO, $s['nivel']);
        $this->assertSame('abortada', $s['motivos'][0]['clave']);
        $this->assertSame('PHP la cortó: memoria agotada', $s['motivos'][0]['texto']);
    }

    #[Test]
    public function periodo_con_peticion_cortada_por_php_es_rojo(): void
    {
        $peticiones = [
            ['datos' => ['error_fatal' => 'Maximum execution time of 120 seconds exceeded']],
            ['datos' => []],
        ];

        $s = RendimientoSemaforo::global($peticiones, [], 0, [], 14);

        $this->assertSame(RendimientoSemaforo::ROJO, $s['nivel']);
        $this->assertSame(['abortadas'], $this->claves($s));
        $this->assertSame('1 petición cortada por PHP', $s['motivos'][0]['texto']);
    }

    #[Test]
    public function muchas_lentas_por_dia_ponen_el_periodo_en_amarillo(): void
    {
        $peticiones = array_fill(0, 10, ['datos' => []]);

        $s = RendimientoSemaforo::global($peticiones, [], 0, [], 2);

        $this->assertSame(RendimientoSemaforo::AMARILLO, $s['nivel']);
        $this->assertSame('~5.0 peticiones lentas por día', $s['motivos'][0]['texto']);
    }
}
