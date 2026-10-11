<?php

namespace App\Http\Middleware;

use App\Services\AuditoriaExcepcionService;
use App\Support\Rendimiento\RendimientoLogLector;
use App\Support\Rendimiento\RendimientoRecolector;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Instrumenta requests lentos para detectar rutas que saturan los procesos PHP del hosting.
 * Escribe en el canal `rendimiento` (nivel propio, independiente de LOG_LEVEL).
 * Si PHP corta la petición con un error fatal (memoria o tiempo agotado), la registra al apagarse.
 */
class LogSlowRequests
{
    private const ERRORES_FATALES = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE];

    /** Memoria extra para poder escribir el log cuando la petición murió por memoria agotada. */
    private const MEMORIA_EXTRA_BYTES = 32 * 1024 * 1024;

    private bool $terminada = false;

    public static function umbralSegundos(): float
    {
        return (float) config('logging.channels.rendimiento.umbral_segundos', 5.0);
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
        $this->terminada = false;
        $this->recolector->iniciar();
        register_shutdown_function(fn () => $this->registrarSiFueAbortada($request, $inicio));

        try {
            $response = $next($request);
        } finally {
            $this->terminada = true;
            $this->recolector->detener();
        }

        $segundos = microtime(true) - $inicio;

        if ($segundos < self::umbralSegundos()) {
            return $response;
        }

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

    private function registrarSiFueAbortada(Request $request, float $inicio): void
    {
        if ($this->terminada) {
            return;
        }

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
