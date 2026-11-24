<?php

namespace App\Http\Middleware;

use App\Services\AuditoriaExcepcionService;
use App\Support\Rendimiento\RendimientoLogLector;
use App\Support\Rendimiento\RendimientoRecolector;
use App\Support\Rendimiento\RendimientoSemaforo;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Instrumenta requests lentos para detectar rutas que saturan los procesos PHP del hosting.
 * Además muestrea navegaciones HTML (carga de página UX: verde / amarillo / rojo).
 * Escribe en el canal `rendimiento` (nivel propio, independiente de LOG_LEVEL).
 * Si PHP corta la petición con un error fatal (memoria o tiempo agotado), la registra al apagarse.
 */
class LogSlowRequests
{
    private const ERRORES_FATALES = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE];

    /** Memoria extra para poder escribir el log cuando la petición murió por memoria agotada. */
    private const MEMORIA_EXTRA_BYTES = 32 * 1024 * 1024;

    private static bool $apagadoRegistrado = false;

    /**
     * Petición en curso para el registro al apagarse PHP. Una sola función de apagado por proceso:
     * registrar una por petición retendría cada Request en procesos largos (suite de pruebas).
     *
     * @var array{middleware: self, request: Request, inicio: float}|null
     */
    private static ?array $enCurso = null;

    public static function umbralSegundos(): float
    {
        return (float) config('logging.channels.rendimiento.umbral_segundos', 5.0);
    }

    /** Porcentaje 0–100 de muestras verdes (&lt; 2 s) en navegaciones HTML. */
    public static function muestraPaginaPct(): int
    {
        return max(0, min(100, (int) config('logging.channels.rendimiento.muestra_pagina_pct', 10)));
    }

    public static function liberarMemoriaSiSeAgoto(string $mensajeError): void
    {
        if (str_contains($mensajeError, 'Allowed memory size')) {
            @ini_set('memory_limit', (string) (memory_get_usage(true) + self::MEMORIA_EXTRA_BYTES));
        }
    }

    public function __construct(
        private readonly RendimientoRecolector $recolector,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $inicio = microtime(true);
        $this->recolector->iniciar();
        self::$enCurso = ['middleware' => $this, 'request' => $request, 'inicio' => $inicio];
        if (! self::$apagadoRegistrado) {
            register_shutdown_function([self::class, 'alApagar']);
            self::$apagadoRegistrado = true;
        }

        try {
            $response = $next($request);
        } finally {
            self::$enCurso = null;
            $this->recolector->detener();
        }

        $segundos = microtime(true) - $inicio;

        if ($segundos >= self::umbralSegundos()) {
            try {
                Log::channel('rendimiento')->warning(RendimientoLogLector::TIPO_LENTA, [
                    'segundos' => round($segundos, 2),
                    ...$this->contexto($request),
                    'status' => $response->getStatusCode(),
                    ...$this->recolector->resumen(),
                ]);
            } catch (Throwable) {
                // El registro de métricas nunca debe romper la respuesta.
            }

            return $response;
        }

        $this->registrarMuestraPaginaSiAplica($request, $response, $segundos);

        return $response;
    }

    public static function alApagar(): void
    {
        $enCurso = self::$enCurso;
        if ($enCurso !== null) {
            $enCurso['middleware']->registrarSiFueAbortada($enCurso['request'], $enCurso['inicio']);
        }
    }

    private function registrarSiFueAbortada(Request $request, float $inicio): void
    {
        $error = error_get_last();
        if ($error === null || ! in_array($error['type'], self::ERRORES_FATALES, true)) {
            return;
        }

        self::liberarMemoriaSiSeAgoto($error['message']);

        try {
            Log::channel('rendimiento')->critical(RendimientoLogLector::TIPO_ABORTADA, [
                'segundos' => round(microtime(true) - $inicio, 2),
                ...$this->contexto($request),
                'status' => 500,
                'error_fatal' => mb_substr($error['message'], 0, 300),
                'archivo' => str_replace(base_path().DIRECTORY_SEPARATOR, '', $error['file']).':'.$error['line'],
                ...$this->recolector->resumen(),
            ]);
        } catch (Throwable) {
            // En pleno apagado de PHP no hay a quién reportar.
        }

        try {
            app(AuditoriaExcepcionService::class)->registrarErrorFatal($error);
        } catch (Throwable) {
            // Idem: la bitácora es de mejor esfuerzo durante el apagado.
        }
    }

    /**
     * Navegación HTML: siempre registra ≥ 2 s (amarillo/rojo UX); verdes solo por muestreo.
     * No sustituye `request_lento` (≥ umbral de saturación).
     */
    private function registrarMuestraPaginaSiAplica(Request $request, Response $response, float $segundos): void
    {
        if (! $this->esNavegacionHtml($request, $response)) {
            return;
        }

        $nivel = RendimientoSemaforo::nivelPorSegundosPagina($segundos);
        if ($nivel === RendimientoSemaforo::VERDE) {
            $pct = self::muestraPaginaPct();
            if ($pct <= 0 || random_int(1, 100) > $pct) {
                return;
            }
        }

        try {
            $resumen = $this->recolector->resumen();
            Log::channel('rendimiento')->info(RendimientoLogLector::TIPO_PAGINA_SERVIDOR, [
                'origen' => 'servidor',
                'nivel' => $nivel,
                'segundos' => round($segundos, 2),
                ...$this->contexto($request),
                'status' => $response->getStatusCode(),
                'sql_consultas' => $resumen['sql_consultas'] ?? 0,
                'sql_total_ms' => $resumen['sql_total_ms'] ?? 0,
            ]);
        } catch (Throwable) {
            // Nunca romper la respuesta por telemetría.
        }
    }

    private function esNavegacionHtml(Request $request, Response $response): bool
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return false;
        }
        if ($request->ajax() || $request->expectsJson()) {
            return false;
        }
        if (! $request->accepts(['text/html', 'application/xhtml+xml'])) {
            return false;
        }

        $ruta = (string) ($request->route()?->getName() ?? '');
        if ($ruta === 'rendimiento.pagina' || str_starts_with($ruta, 'auditoria.')) {
            return false;
        }

        $disposition = (string) $response->headers->get('Content-Disposition', '');
        if ($disposition !== '' && stripos($disposition, 'attachment') !== false) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return false;
        }

        return $response->getStatusCode() < 500;
    }

    /**
     * @return array{method: string, path: string, route: ?string, user_id: mixed}
     */
    private function contexto(Request $request): array
    {
        try {
            $userId = $request->user()?->id;
        } catch (Throwable) {
            $userId = null;
        }

        return [
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route' => $request->route()?->getName(),
            'user_id' => $userId,
        ];
    }
}
