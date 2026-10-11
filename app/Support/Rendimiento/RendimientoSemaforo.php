<?php

namespace App\Support\Rendimiento;

/**
 * Semáforo de riesgo de la pantalla Auditoría → Rendimiento, en tres niveles: petición, ruta y periodo.
 * Cada color trae sus motivos con una acción sugerida. Los umbrales viven solo aquí.
 *
 * @phpstan-type Motivo array{clave: string, nivel: string, texto: string, accion: string, valor: float}
 * @phpstan-type Semaforo array{nivel: string, motivos: list<Motivo>}
 */
final class RendimientoSemaforo
{
    public const VERDE = 'verde';

    public const AMARILLO = 'amarillo';

    public const ROJO = 'rojo';

    public const SEGUNDOS_ROJO = 10.0;

    public const CONSULTAS_AMARILLO = 300;

    public const CONSULTAS_ROJO = 500;

    /** % del tiempo en SQL a partir del cual la base de datos es la causa principal. */
    public const PCT_SQL_DOMINANTE = 50;

    public const PROMEDIO_RUTA_ROJO = 8.0;

    public const VECES_POR_DIA_RUTA_ROJO = 3.0;

    public const LENTAS_POR_DIA_AMARILLO = 5.0;

    private const PESO = [self::VERDE => 0, self::AMARILLO => 1, self::ROJO => 2];

    private const ETIQUETAS = [self::VERDE => 'Sin riesgo', self::AMARILLO => 'Riesgo medio', self::ROJO => 'Riesgo alto'];

    private const CODIGOS_CONEXIONES = [1040, 1203, 1226, 2002];

    private const CODIGOS_BLOQUEO = [1205, 1213];

    public static function etiqueta(string $nivel): string
    {
        return self::ETIQUETAS[$nivel] ?? $nivel;
    }

    /**
     * @param  array<string, mixed>  $datos  Contexto de una entrada `request_lento` o `request_abortado`.
     */
    public static function esAbortada(array $datos): bool
    {
        return ! empty($datos['error_fatal']);
    }

    /**
     * @param  array<string, mixed>  $datos  Contexto de una entrada `request_lento` o `request_abortado`.
     * @return Semaforo
     */
    public static function peticion(array $datos): array
    {
        $seg = (float) ($datos['segundos'] ?? 0);
        $consultas = (int) ($datos['sql_consultas'] ?? 0);
        $pctSql = isset($datos['sql_total_ms']) && $seg > 0
            ? (int) min(100, round((float) $datos['sql_total_ms'] / ($seg * 1000) * 100))
            : null;

        $motivos = [];

        if (self::esAbortada($datos)) {
            [$texto, $accion] = self::textosAbortada((string) $datos['error_fatal']);
            $motivos[] = self::motivo('abortada', self::ROJO, $texto, $accion, 1);
        }

        if ($consultas >= self::CONSULTAS_AMARILLO) {
            $motivos[] = self::motivo(
                'consultas',
                $consultas >= self::CONSULTAS_ROJO ? self::ROJO : self::AMARILLO,
                number_format($consultas).' consultas SQL en una sola petición',
                'Probable N+1 (consultas dentro de un ciclo): precarga relaciones con with() o agrupa en una sola consulta. Empieza por el archivo de la consulta más lenta.',
                $consultas,
            );
        }

        $sqlLentas = (int) ($datos['sql_lentas'] ?? 0);
        if ($sqlLentas > 0) {
            $motivos[] = self::motivo(
                'sql_lentas',
                self::AMARILLO,
                $sqlLentas === 1 ? '1 consulta de más de 1 s' : "{$sqlLentas} consultas de más de 1 s",
                'Revisa índices y filtros de la consulta más lenta (EXPLAIN).',
                $sqlLentas,
            );
        }

        foreach (is_array($datos['http_externas'] ?? null) ? $datos['http_externas'] : [] as $x) {
            $host = (string) ($x['host'] ?? '?');
            $motivos[] = empty($x['error'])
                ? self::motivo(
                    'api:'.$host,
                    self::AMARILLO,
                    "API externa lenta: {$host} (".number_format((float) ($x['ms'] ?? 0) / 1000, 1).' s)',
                    'La petición queda esperando al proveedor: baja el timeout o evita llamarlo dentro de esta pantalla.',
                    (float) ($x['ms'] ?? 0),
                )
                : self::motivo(
                    'api:'.$host,
                    self::ROJO,
                    "Falló la API externa {$host}",
                    'La petición esperó al proveedor hasta fallar: baja el timeout o reintenta fuera de la petición del usuario.',
                    PHP_FLOAT_MAX,
                );
        }

        $status = (int) ($datos['status'] ?? 0);
        if ($status >= 500) {
            $motivos[] = self::motivo(
                'http_error',
                self::ROJO,
                "Respondió con error HTTP {$status}",
                'Revisa la pestaña «Errores del sistema» o laravel.log a esa hora para ver la excepción.',
                $status,
            );
        }

        // Al final: dentro del mismo color, las causas concretas se muestran antes que el tiempo.
        $motivos[] = self::motivo(
            'tiempo',
            $seg >= self::SEGUNDOS_ROJO ? self::ROJO : self::AMARILLO,
            'Tardó '.number_format($seg, 1).' s',
            self::accionTiempo($pctSql),
            $seg,
        );

        return self::semaforo($motivos);
    }

    /**
     * @param  array{ruta: string, veces: int, promedio: float}  $fila  Fila de `RendimientoLogLector::resumenPorRuta()`.
     * @param  list<Semaforo>  $semaforosPeticiones  Semáforos de las peticiones de esa ruta.
     * @return Semaforo
     */
    public static function ruta(array $fila, array $semaforosPeticiones, bool $enSaturacion, int $dias): array
    {
        $peores = [];
        foreach ($semaforosPeticiones as $s) {
            foreach ($s['motivos'] as $m) {
                $actual = $peores[$m['clave']] ?? null;
                if ($actual === null || [self::PESO[$m['nivel']], $m['valor']] > [self::PESO[$actual['nivel']], $actual['valor']]) {
                    $peores[$m['clave']] = $m;
                }
            }
        }
        $motivos = array_values($peores);

        if ($enSaturacion) {
            $motivos[] = self::motivo(
                'saturacion',
                self::ROJO,
                'Coincidió con otras peticiones lentas en un minuto con saturación',
                'Mientras corre ocupa un proceso PHP y una conexión a MySQL; bajar su tiempo es lo que más ayuda al resto de usuarios.',
                1,
            );
        }

        if ($fila['promedio'] >= self::PROMEDIO_RUTA_ROJO) {
            $motivos[] = self::motivo(
                'promedio',
                self::ROJO,
                'Promedia '.number_format($fila['promedio'], 1).' s por petición',
                'Es lenta de forma constante, no por un caso aislado: conviene optimizarla o cachear su resultado.',
                $fila['promedio'],
            );
        }

        $porDia = $fila['veces'] / max(1, $dias);
        if ($porDia >= self::VECES_POR_DIA_RUTA_ROJO) {
            $motivos[] = self::motivo(
                'frecuencia',
                self::ROJO,
                'Se repite ~'.number_format($porDia, 1).' veces por día',
                'Es recurrente: su costo se multiplica por cada usuario que la abre.',
                $porDia,
            );
        }

        return self::semaforo($motivos);
    }

    /**
     * @param  list<array{datos: array<string, mixed>}>  $peticiones
     * @param  list<array{datos: array<string, mixed>}>  $erroresMysql
     * @param  list<array{ruta: string, semaforo: Semaforo}>  $resumen  Ordenado por tiempo total (el de `resumenPorRuta()`).
     * @return array{nivel: string, motivos: list<Motivo>, prioridad: ?array{ruta: string, nivel: string, motivo: Motivo}}
     */
    public static function global(array $peticiones, array $erroresMysql, int $minutosSaturados, array $resumen, int $dias): array
    {
        $motivos = [];

        $abortadas = count(array_filter($peticiones, fn (array $e) => self::esAbortada($e['datos'])));
        if ($abortadas > 0) {
            $motivos[] = self::motivo(
                'abortadas',
                self::ROJO,
                $abortadas === 1 ? '1 petición cortada por PHP' : "{$abortadas} peticiones cortadas por PHP",
                'Se agotó la memoria o el tiempo máximo de PHP: el usuario vio un error y el proceso quedó ocupado hasta el corte. Atiende primero esas rutas.',
                $abortadas,
            );
        }

        if ($minutosSaturados > 0) {
            $motivos[] = self::motivo(
                'saturacion',
                self::ROJO,
                $minutosSaturados === 1 ? '1 minuto con saturación' : "{$minutosSaturados} minutos con saturación",
                'Varias peticiones lentas a la vez llenaron los procesos PHP: revisa qué rutas coincidieron en la tabla «Minutos con saturación».',
                $minutosSaturados,
            );
        }

        if ($erroresMysql !== []) {
            $codigos = array_map(fn (array $e) => (int) ($e['datos']['codigo'] ?? 0), $erroresMysql);
            $tipos = array_unique(array_filter(array_map(fn (array $e) => (string) ($e['datos']['tipo'] ?? ''), $erroresMysql)));
            $n = count($erroresMysql);
            $motivos[] = self::motivo(
                'mysql',
                self::ROJO,
                ($n === 1 ? '1 error de MySQL' : "{$n} errores de MySQL").($tipos !== [] ? ': '.implode(', ', $tipos) : ''),
                self::accionMysql($codigos),
                $n,
            );
        }

        $porDia = count($peticiones) / max(1, $dias);
        if ($porDia >= self::LENTAS_POR_DIA_AMARILLO) {
            $motivos[] = self::motivo(
                'lentas_por_dia',
                self::AMARILLO,
                '~'.number_format($porDia, 1).' peticiones lentas por día',
                'Hay lentitud recurrente: atiende primero las rutas en rojo del resumen.',
                $porDia,
            );
        }

        $rojas = count(array_filter($resumen, fn (array $r) => $r['semaforo']['nivel'] === self::ROJO));
        if ($rojas > 0) {
            $motivos[] = self::motivo(
                'rutas_rojas',
                self::AMARILLO,
                $rojas === 1 ? '1 ruta en riesgo alto' : "{$rojas} rutas en riesgo alto",
                'Cada petición larga retiene un proceso PHP y una conexión a MySQL; si coinciden varias, el resto de usuarios espera.',
                $rojas,
            );
        }

        $prioridad = null;
        foreach ($resumen as $r) {
            $s = $r['semaforo'];
            if ($s['motivos'] === [] || $s['nivel'] === self::VERDE) {
                continue;
            }
            if ($prioridad === null || self::PESO[$s['nivel']] > self::PESO[$prioridad['nivel']]) {
                $prioridad = ['ruta' => $r['ruta'], 'nivel' => $s['nivel'], 'motivo' => $s['motivos'][0]];
            }
        }

        return self::semaforo($motivos) + ['prioridad' => $prioridad];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function textosAbortada(string $error): array
    {
        if (str_contains($error, 'Allowed memory size')) {
            return [
                'PHP la cortó: memoria agotada',
                'Procesa por bloques (chunk o cursor) y evita cargar todos los registros, PDFs o archivos de Excel grandes en una sola petición.',
            ];
        }

        if (str_contains($error, 'Maximum execution time')) {
            return [
                'PHP la cortó: tiempo máximo de ejecución',
                'Superó el límite de PHP: optimiza la ruta o divide el trabajo; mientras corrió bloqueó un proceso PHP.',
            ];
        }

        return [
            'PHP la cortó por un error fatal',
            'Revisa la pestaña «Errores del sistema» o laravel.log a esa hora.',
        ];
    }

    private static function accionTiempo(?int $pctSql): string
    {
        if ($pctSql === null) {
            return 'Sin datos de SQL: revisa el código de la ruta.';
        }

        return $pctSql >= self::PCT_SQL_DOMINANTE
            ? "El {$pctSql}% del tiempo fue SQL: revisa cuántas consultas hace y la consulta más lenta."
            : "Solo el {$pctSql}% fue SQL: el tiempo se fue en PHP (ciclos, colecciones, PDF, Excel), API externa o esperando turno.";
    }

    /**
     * @param  list<int>  $codigos
     */
    private static function accionMysql(array $codigos): string
    {
        if (array_intersect($codigos, self::CODIGOS_CONEXIONES) !== []) {
            return 'MySQL rechazó conexiones: hay demasiadas peticiones largas abiertas a la vez. Pide a Hostinger tu límite max_user_connections y reduce las rutas en rojo.';
        }
        if (array_intersect($codigos, self::CODIGOS_BLOQUEO) !== []) {
            return 'Transacciones compitiendo por las mismas filas: revisa las rutas de la tabla «Errores de MySQL» y acorta sus transacciones.';
        }

        return 'Consultas cortadas o conexión perdida, normalmente por una consulta demasiado larga: revisa la ruta indicada en «Errores de MySQL».';
    }

    /**
     * @return Motivo
     */
    private static function motivo(string $clave, string $nivel, string $texto, string $accion, float $valor): array
    {
        return ['clave' => $clave, 'nivel' => $nivel, 'texto' => $texto, 'accion' => $accion, 'valor' => $valor];
    }

    /**
     * @param  list<Motivo>  $motivos
     * @return Semaforo
     */
    private static function semaforo(array $motivos): array
    {
        usort($motivos, fn (array $a, array $b) => self::PESO[$b['nivel']] <=> self::PESO[$a['nivel']]);

        $nivel = self::VERDE;
        foreach ($motivos as $m) {
            if (self::PESO[$m['nivel']] > self::PESO[$nivel]) {
                $nivel = $m['nivel'];
            }
        }

        return ['nivel' => $nivel, 'motivos' => $motivos];
    }
}
