<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Permission;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // CA bundle del proyecto para SSL (evita error 60 en laptops/servidores sin bundle del sistema)
        $cacert = base_path('certs/cacert.pem');
        if (file_exists($cacert) && !getenv('CURL_CA_BUNDLE') && !getenv('SSL_CERT_FILE')) {
            putenv('CURL_CA_BUNDLE=' . $cacert);
            putenv('SSL_CERT_FILE=' . $cacert);
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
    }
}
