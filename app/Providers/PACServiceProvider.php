<?php

namespace App\Providers;

// UBICACIÓN: app/Providers/PACServiceProvider.php

use Illuminate\Support\ServiceProvider;
use App\Services\PACServiceInterface;
use App\Services\PACService;

class PACServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Registrar el PACService como singleton
        $this->app->singleton(PACServiceInterface::class, function ($app) {
            return new PACService();
        });

        // También registrar con alias corto
        $this->app->alias(PACServiceInterface::class, 'pac');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}