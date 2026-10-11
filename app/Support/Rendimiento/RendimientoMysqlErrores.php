<?php

namespace App\Support\Rendimiento;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PDOException;
use Throwable;

/**
 * Registra en el canal `rendimiento` los errores de MySQL que indican saturación o bloqueo.
 * No consulta la base de datos: debe funcionar justo cuando MySQL no responde.
 */
final class RendimientoMysqlErrores
{
    /** @var array<int, string> */
    public const CODIGOS = [
        1040 => 'Demasiadas conexiones (servidor)',
        1203 => 'Demasiadas conexiones del usuario',
        1205 => 'Espera de bloqueo agotada',
        1213 => 'Deadlock',
        1226 => 'Límite de recursos del usuario excedido',
        2002 => 'No se pudo conectar a MySQL',
        2006 => 'MySQL se desconectó',
        2013 => 'Conexión perdida durante la consulta',
        3024 => 'Consulta cancelada por tiempo máximo',
    ];

    /** Códigos con los que escribir en MySQL solo empeoraría la situación. */
    public const CODIGOS_SIN_CONEXION = [1040, 1203, 1226, 2002, 2006, 2013];

    public static function registrarSiAplica(Throwable $e): void
    {
        $codigo = self::codigoMysql($e);
        if ($codigo === null || ! isset(self::CODIGOS[$codigo])) {
            return;
        }

        $request = app()->runningInConsole() ? null : request();

        try {
            Log::channel('rendimiento')->error('mysql_error', [
                'codigo' => $codigo,
                'tipo' => self::CODIGOS[$codigo],
                'mensaje' => mb_substr($e->getMessage(), 0, 300),
                'method' => $request?->method(),
                'path' => $request !== null ? '/'.ltrim($request->path(), '/') : 'consola',
                'route' => $request?->route()?->getName(),
                'user_id' => Auth::hasUser() ? Auth::id() : null,
            ]);
        } catch (Throwable) {
            // Nunca interferir con el reporte nativo de la excepción.
        }
    }

    public static function esSinConexion(Throwable $e): bool
    {
        return in_array(self::codigoMysql($e), self::CODIGOS_SIN_CONEXION, true);
    }

    public static function codigoMysql(Throwable $e): ?int
    {
        for ($actual = $e; $actual !== null; $actual = $actual->getPrevious()) {
            if (! $actual instanceof QueryException && ! $actual instanceof PDOException) {
                continue;
            }

            $driverCode = $actual->errorInfo[1] ?? null;
            if (is_numeric($driverCode)) {
                return (int) $driverCode;
            }

            if (preg_match('/\[(\d{4})\]/', $actual->getMessage(), $m) === 1) {
                return (int) $m[1];
            }
        }

        return null;
    }
}
