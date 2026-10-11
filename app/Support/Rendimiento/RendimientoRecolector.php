<?php

namespace App\Support\Rendimiento;

/**
 * Métricas de la petición web en curso (SQL y HTTP externo) para el log de rendimiento.
 * Solo acumula entre iniciar() y detener(); fuera de eso (artisan, comandos) no guarda nada.
 */
final class RendimientoRecolector
{
    public const UMBRAL_HTTP_EXTERNO_MS = 5000.0;

    /** Solo consultas a partir de este tiempo pagan el costo del backtrace para ubicar su origen. */
    private const UMBRAL_ORIGEN_SQL_MS = 100.0;

    private const UMBRAL_SQL_LENTA_MS = 1000.0;

    private const MAX_LLAMADAS_EXTERNAS = 10;

    private const MAX_SQL_CHARS = 500;

    private bool $activo = false;

    private int $consultas = 0;

    private float $sqlTotalMs = 0.0;

    private int $consultasLentas = 0;

    /** @var array{ms: float, sql: string, origen: ?string}|null */
    private ?array $consultaMasLenta = null;

    /** @var list<array{host: string, metodo: string, ms: ?float, error: ?string}> */
    private array $llamadasExternas = [];

    public function iniciar(): void
    {
        $this->activo = true;
        $this->consultas = 0;
        $this->sqlTotalMs = 0.0;
        $this->consultasLentas = 0;
        $this->consultaMasLenta = null;
        $this->llamadasExternas = [];
    }

    public function detener(): void
    {
        $this->activo = false;
    }

    public function registrarConsulta(string $sql, float $ms): void
    {
        if (! $this->activo) {
            return;
        }

        $this->consultas++;
        $this->sqlTotalMs += $ms;

        if ($ms >= self::UMBRAL_SQL_LENTA_MS) {
            $this->consultasLentas++;
        }

        if ($this->consultaMasLenta !== null && $ms <= $this->consultaMasLenta['ms']) {
            return;
        }

        $this->consultaMasLenta = [
            'ms' => round($ms, 1),
            'sql' => mb_substr(preg_replace('/\s+/', ' ', trim($sql)) ?? $sql, 0, self::MAX_SQL_CHARS),
            'origen' => $ms >= self::UMBRAL_ORIGEN_SQL_MS ? self::origenEnApp() : null,
        ];
    }

    public function registrarLlamadaExterna(string $url, string $metodo, ?float $ms, ?string $error = null): void
    {
        if (! $this->activo || count($this->llamadasExternas) >= self::MAX_LLAMADAS_EXTERNAS) {
            return;
        }

        if ($error === null && ($ms === null || $ms < self::UMBRAL_HTTP_EXTERNO_MS)) {
            return;
        }

        $this->llamadasExternas[] = [
            'host' => (string) (parse_url($url, PHP_URL_HOST) ?: $url),
            'metodo' => strtoupper($metodo),
            'ms' => $ms !== null ? round($ms, 1) : null,
            'error' => $error !== null ? mb_substr($error, 0, 300) : null,
        ];
    }

    /**
     * @return array{
     *     sql_consultas: int,
     *     sql_total_ms: float,
     *     sql_lentas: int,
     *     sql_mas_lenta: array{ms: float, sql: string, origen: ?string}|null,
     *     http_externas: list<array{host: string, metodo: string, ms: ?float, error: ?string}>
     * }
     */
    public function resumen(): array
    {
        return [
            'sql_consultas' => $this->consultas,
            'sql_total_ms' => round($this->sqlTotalMs, 1),
            'sql_lentas' => $this->consultasLentas,
            'sql_mas_lenta' => $this->consultaMasLenta,
            'http_externas' => $this->llamadasExternas,
        ];
    }

    private static function origenEnApp(): ?string
    {
        $base = base_path().DIRECTORY_SEPARATOR;
        $app = $base.'app'.DIRECTORY_SEPARATOR;
        $ignorar = [
            $app.'Support'.DIRECTORY_SEPARATOR.'Rendimiento'.DIRECTORY_SEPARATOR,
            $app.'Providers'.DIRECTORY_SEPARATOR,
        ];

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 60) as $frame) {
            $file = $frame['file'] ?? '';
            if ($file === '' || ! str_starts_with($file, $app)) {
                continue;
            }
            foreach ($ignorar as $prefijo) {
                if (str_starts_with($file, $prefijo)) {
                    continue 2;
                }
            }

            return str_replace($base, '', $file).':'.($frame['line'] ?? 0);
        }

        return null;
    }
}
