<?php

namespace App\Providers;

use App\Events\FacturaCompraDesdeCfdiRegistrada;
use App\Listeners\AlmacenarRevisionPrecioTrasCompraCfdi;
use App\Models\Permission;
use App\Models\User;
use App\Services\AuditoriaExcepcionService;
use App\Support\Rendimiento\RendimientoRecolector;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RendimientoRecolector::class);
        $this->app->singleton(AuditoriaExcepcionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(FacturaCompraDesdeCfdiRegistrada::class, AlmacenarRevisionPrecioTrasCompraCfdi::class);

        // CA bundle del proyecto para SSL (evita error 60 en laptops/servidores sin bundle del sistema)
        $cacert = base_path('certs/cacert.pem');
        if (file_exists($cacert) && ! getenv('CURL_CA_BUNDLE') && ! getenv('SSL_CERT_FILE')) {
            putenv('CURL_CA_BUNDLE='.$cacert);
            putenv('SSL_CERT_FILE='.$cacert);
        }

        Route::bind('usuario', fn ($value) => User::findOrFail($value));

        // Admin tiene todos los permisos
        Gate::before(function (User $user, string $ability) {
            if ($user->isAdmin()) {
                return true;
            }

            return null;
        });

        // Definir Gate por cada permiso (para @can y middleware)
        try {
            foreach (Permission::pluck('key') as $key) {
                Gate::define($key, function (User $user) use ($key) {
                    return $user->hasPermission($key);
                });
            }
        } catch (\Throwable $e) {
            // Si las tablas no existen (migración pendiente), no definir Gates
        }

        $this->registrarMetricasRendimiento();
    }

    private function registrarMetricasRendimiento(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            $this->app->make(RendimientoRecolector::class)->registrarConsulta($query->sql, (float) $query->time);
        });

        Event::listen(function (ResponseReceived $event): void {
            $segundos = $event->response->transferStats?->getTransferTime();

            $this->app->make(RendimientoRecolector::class)->registrarLlamadaExterna(
                $event->request->url(),
                $event->request->method(),
                $segundos !== null ? $segundos * 1000 : null,
            );
        });

        Event::listen(function (ConnectionFailed $event): void {
            $this->app->make(RendimientoRecolector::class)->registrarLlamadaExterna(
                $event->request->url(),
                $event->request->method(),
                null,
                $event->exception->getMessage(),
            );
        });
    }
}
