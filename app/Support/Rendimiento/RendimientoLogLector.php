<?php

namespace App\Support\Rendimiento;

use Carbon\CarbonImmutable;
use SplFileObject;
use Throwable;

/**
 * Lee los archivos diarios `storage/logs/rendimiento-YYYY-MM-DD.log` (JSON por línea)
 * y arma los agregados de la pantalla Auditoría → Rendimiento. Sin base de datos.
 */
final class RendimientoLogLector
{
    public const MAX_DIAS = 14;

    public const MAX_ENTRADAS = 5000;

    /** Peticiones lentas en el mismo minuto a partir de las cuales se considera pico de saturación. */
    public const MIN_PETICIONES_PICO = 3;

    public const TIPO_LENTA = 'request_lento';

    public const TIPO_ABORTADA = 'request_abortado';

    public const TIPO_MYSQL = 'mysql_error';

    public function __construct(
        private readonly ?string $directorio = null,
    ) {}

    /**
     * @return list<array{tipo: string, fecha: CarbonImmutable, datos: array<string, mixed>}>
     */
    public function leer(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $desde = $desde->startOfDay();
        $hasta = $hasta->startOfDay();
        if ($hasta->lessThan($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        if ($desde->diffInDays($hasta) >= self::MAX_DIAS) {
            $desde = $hasta->subDays(self::MAX_DIAS - 1);
        }

        $entradas = [];
        for ($dia = $hasta; $dia->greaterThanOrEqualTo($desde); $dia = $dia->subDay()) {
            $ruta = $this->rutaArchivo($dia);
            if (! is_file($ruta) || ! is_readable($ruta)) {
                continue;
            }

            foreach ($this->leerArchivo($ruta) as $entrada) {
                $entradas[] = $entrada;
                if (count($entradas) >= self::MAX_ENTRADAS) {
                    break 2;
                }
            }
        }

        usort($entradas, fn (array $a, array $b) => $b['fecha'] <=> $a['fecha']);

        return $entradas;
    }

    /**
     * @param  list<array{tipo: string, fecha: CarbonImmutable, datos: array<string, mixed>}>  $entradas
     * @return list<array{
     *     ruta: string, path: string, veces: int, promedio: float, maximo: float,
     *     total: float, pct_sql: ?float, usuarios: int, ultima: CarbonImmutable
     * }>
     */
    public static function resumenPorRuta(array $entradas): array
    {
        $grupos = [];
        foreach (self::soloPeticiones($entradas) as $e) {
            $d = $e['datos'];
            $clave = self::claveRuta($d);
            $seg = (float) ($d['segundos'] ?? 0);

            $g = $grupos[$clave] ?? [
                'ruta' => $clave,
                'path' => (string) ($d['path'] ?? ''),
                'veces' => 0,
                'total' => 0.0,
                'maximo' => 0.0,
                'sql_ms' => 0.0,
                'seg_con_sql' => 0.0,
                'usuarios' => [],
                'ultima' => $e['fecha'],
            ];

            $g['veces']++;
            $g['total'] += $seg;
            $g['maximo'] = max($g['maximo'], $seg);
            if (isset($d['sql_total_ms'])) {
                $g['sql_ms'] += (float) $d['sql_total_ms'];
                $g['seg_con_sql'] += $seg;
            }
            if (isset($d['user_id'])) {
                $g['usuarios'][(int) $d['user_id']] = true;
            }
            if ($e['fecha']->greaterThan($g['ultima'])) {
                $g['ultima'] = $e['fecha'];
            }

            $grupos[$clave] = $g;
        }

        $resultado = array_map(fn (array $g) => [
            'ruta' => $g['ruta'],
            'path' => $g['path'],
            'veces' => $g['veces'],
            'promedio' => round($g['total'] / $g['veces'], 1),
            'maximo' => round($g['maximo'], 1),
            'total' => round($g['total'], 1),
            'pct_sql' => $g['seg_con_sql'] > 0
                ? min(100.0, round($g['sql_ms'] / ($g['seg_con_sql'] * 1000) * 100, 0))
                : null,
            'usuarios' => count($g['usuarios']),
            'ultima' => $g['ultima'],
        ], array_values($grupos));

        usort($resultado, fn (array $a, array $b) => $b['total'] <=> $a['total']);

        return $resultado;
    }

    /**
     * Minutos con varias peticiones lentas simultáneas: señal de workers PHP saturados.
     *
     * @param  list<array{tipo: string, fecha: CarbonImmutable, datos: array<string, mixed>}>  $entradas
     * @return list<array{minuto: CarbonImmutable, peticiones: int, maximo: float, usuarios: int, rutas: list<string>}>
     */
    public static function picos(array $entradas, int $limite = 20): array
    {
        $picos = [];
        foreach (self::minutosSaturados($entradas) as $p) {
            arsort($p['rutas']);
            $picos[] = [
                'minuto' => $p['minuto'],
                'peticiones' => $p['peticiones'],
                'maximo' => round($p['maximo'], 1),
                'usuarios' => count($p['usuarios']),
                'rutas' => array_map(
                    fn (string $ruta, int $n) => $n > 1 ? "{$ruta} ×{$n}" : $ruta,
                    array_keys($p['rutas']),
                    array_values($p['rutas']),
                ),
            ];
        }

        usort($picos, fn (array $a, array $b) => [$b['peticiones'], $b['minuto']] <=> [$a['peticiones'], $a['minuto']]);

        return array_slice($picos, 0, $limite);
    }

    /**
     * Rutas que participaron en al menos un minuto con saturación (sin el límite de `picos()`).
     *
     * @param  list<array{tipo: string, fecha: CarbonImmutable, datos: array<string, mixed>}>  $entradas
     * @return array<string, true>
     */
    public static function rutasEnSaturacion(array $entradas): array
    {
        $rutas = [];
        foreach (self::minutosSaturados($entradas) as $p) {
            foreach (array_keys($p['rutas']) as $ruta) {
                $rutas[(string) $ruta] = true;
            }
        }

        return $rutas;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function claveRuta(array $datos): string
    {
        return (string) ($datos['route'] ?? '') ?: (string) ($datos['path'] ?? '—');
    }

    /**
     * @param  list<array{tipo: string, fecha: CarbonImmutable, datos: array<string, mixed>}>  $entradas
     * @return list<array{minuto: CarbonImmutable, peticiones: int, maximo: float, usuarios: array<int, true>, rutas: array<string, int>}>
     */
    private static function minutosSaturados(array $entradas): array
    {
        $porMinuto = [];
        foreach (self::soloPeticiones($entradas) as $e) {
            $clave = $e['fecha']->format('Y-m-d H:i');
            $d = $e['datos'];

            $p = $porMinuto[$clave] ?? [
                'minuto' => $e['fecha']->startOfMinute(),
                'peticiones' => 0,
                'maximo' => 0.0,
                'usuarios' => [],
                'rutas' => [],
            ];
            $p['peticiones']++;
            $p['maximo'] = max($p['maximo'], (float) ($d['segundos'] ?? 0));
            if (isset($d['user_id'])) {
                $p['usuarios'][(int) $d['user_id']] = true;
            }
            $ruta = self::claveRuta($d);
            $p['rutas'][$ruta] = ($p['rutas'][$ruta] ?? 0) + 1;

            $porMinuto[$clave] = $p;
        }

        return array_values(array_filter(
            $porMinuto,
            fn (array $p) => $p['peticiones'] >= self::MIN_PETICIONES_PICO
        ));
    }

    /**
     * Peticiones lentas y abortadas por PHP (error fatal): ambas ocuparon un proceso PHP.
     *
     * @param  list<array{tipo: string, fecha: CarbonImmutable, datos: array<string, mixed>}>  $entradas
     * @return list<array{tipo: string, fecha: CarbonImmutable, datos: array<string, mixed>}>
     */
    public static function soloPeticiones(array $entradas): array
    {
        return array_values(array_filter(
            $entradas,
            fn (array $e) => $e['tipo'] === self::TIPO_LENTA || $e['tipo'] === self::TIPO_ABORTADA
        ));
    }

    /**
     * @param  list<array{tipo: string, fecha: CarbonImmutable, datos: array<string, mixed>}>  $entradas
     * @return list<array{tipo: string, fecha: CarbonImmutable, datos: array<string, mixed>}>
     */
    public static function soloErroresMysql(array $entradas): array
    {
        return array_values(array_filter($entradas, fn (array $e) => $e['tipo'] === self::TIPO_MYSQL));
    }

    private function rutaArchivo(CarbonImmutable $dia): string
    {
        $dir = $this->directorio ?? storage_path('logs');

        return rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'rendimiento-'.$dia->format('Y-m-d').'.log';
    }

    /**
     * @return iterable<array{tipo: string, fecha: CarbonImmutable, datos: array<string, mixed>}>
     */
    private function leerArchivo(string $ruta): iterable
    {
        try {
            $archivo = new SplFileObject($ruta, 'r');
        } catch (Throwable) {
            return;
        }

        $zona = config('app.timezone');

        while (! $archivo->eof()) {
            $linea = trim((string) $archivo->fgets());
            if ($linea === '') {
                continue;
            }

            $json = json_decode($linea, true);
            if (! is_array($json) || ! is_string($json['message'] ?? null) || ! is_string($json['datetime'] ?? null)) {
                continue;
            }

            try {
                $fecha = CarbonImmutable::parse($json['datetime'])->setTimezone($zona);
            } catch (Throwable) {
                continue;
            }

            yield [
                'tipo' => $json['message'],
                'fecha' => $fecha,
                'datos' => is_array($json['context'] ?? null) ? $json['context'] : [],
            ];
        }
    }
}
