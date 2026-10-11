<?php

namespace App\Services;

use App\Models\AuditoriaExcepcion;
use App\Support\Rendimiento\RendimientoMysqlErrores;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\ErrorHandler\Error\FatalError;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Bitácora de excepciones no controladas (errores 500) para Auditoría → Errores del sistema.
 */
class AuditoriaExcepcionService
{
    public const DIAS_RETENCION = 90;

    /** Tope por petición: un error dentro de un ciclo no debe llenar la tabla. */
    private const MAX_POR_PETICION = 5;

    /** 1 de cada N inserciones depura los registros vencidos (no hay cron en el hosting). */
    private const LOTERIA_DEPURACION = 100;

    private int $registradas = 0;

    private bool $fatalRegistrado = false;

    /**
     * Respaldo para errores fatales que el reporte nativo de Laravel no alcanzó a procesar
     * (con memoria agotada, sus 32 KB reservados no bastan).
     *
     * @param  array{type: int, message: string, file: string, line: int}  $error  Resultado de error_get_last().
     */
    public function registrarErrorFatal(array $error): void
    {
        if (! $this->fatalRegistrado) {
            $this->registrar(new FatalError($error['message'], 0, $error, 0));
        }
    }

    public function registrar(Throwable $e): void
    {
        if ($this->registradas >= self::MAX_POR_PETICION || $this->esIgnorada($e)) {
            return;
        }
        $this->registradas++;
        if ($e instanceof FatalError) {
            $this->fatalRegistrado = true;
        }

        $enConsola = app()->runningInConsole();
        $request = $enConsola ? null : request();

        AuditoriaExcepcion::create([
            'user_id' => Auth::hasUser() ? Auth::id() : null,
            'excepcion' => mb_substr($e::class, 0, 255),
            'mensaje' => $e->getMessage() !== '' ? mb_substr($e->getMessage(), 0, 1000) : null,
            'archivo' => mb_substr(str_replace(base_path().DIRECTORY_SEPARATOR, '', $e->getFile()).':'.$e->getLine(), 0, 255),
            'metodo' => $request?->method(),
            'ruta' => $request !== null ? mb_substr('/'.ltrim($request->path(), '/'), 0, 255) : 'consola',
            'ruta_nombre' => $request?->route()?->getName(),
            'ip' => $request?->ip(),
        ]);

        if (random_int(1, self::LOTERIA_DEPURACION) === 1) {
            AuditoriaExcepcion::query()
                ->where('created_at', '<', now()->subDays(self::DIAS_RETENCION))
                ->delete();
        }
    }

    private function esIgnorada(Throwable $e): bool
    {
        // No intentar escribir en MySQL cuando justo está saturado o caído.
        if (RendimientoMysqlErrores::esSinConexion($e)) {
            return true;
        }

        if ($e instanceof ValidationException
            || $e instanceof AuthenticationException
            || $e instanceof AuthorizationException
            || $e instanceof ModelNotFoundException
            || $e instanceof TokenMismatchException
        ) {
            return true;
        }

        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode() < 500;
        }

        return false;
    }
}
