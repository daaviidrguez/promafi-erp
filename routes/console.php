<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// En Hostinger: cron → * * * * * php /ruta/al/proyecto/artisan schedule:run
Schedule::command('cotizaciones:marcar-vencidas')->everyFifteenMinutes();
