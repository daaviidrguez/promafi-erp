<?php

use App\Http\Middleware\LogSlowRequests;
use App\Services\AuditoriaExcepcionService;
use App\Support\Rendimiento\RendimientoMysqlErrores;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\ErrorHandler\Error\FatalError;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            LogSlowRequests::class,
        ]);

        $middleware->alias([
            'permiso' => \App\Http\Middleware\CheckPermission::class,
            'route.permission' => \App\Http\Middleware\EnsureUserHasRoutePermission::class,
            'catalogo.api' => \App\Http\Middleware\ValidateCatalogoApiToken::class,
        ]);
    })
    ->withProviders([
        App\Providers\PACServiceProvider::class, // ← REGISTRADO AQUÍ
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (Throwable $e): void {
            if ($e instanceof FatalError) {
                LogSlowRequests::liberarMemoriaSiSeAgoto($e->getMessage());
            }

            RendimientoMysqlErrores::registrarSiAplica($e);

            try {
                app(AuditoriaExcepcionService::class)->registrar($e);
            } catch (Throwable) {
                // La bitácora no debe interferir con el reporte nativo.
            }
        });
    })
    ->create();
