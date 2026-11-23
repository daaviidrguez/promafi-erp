<?php

namespace App\Console\Commands;

use App\Models\Cotizacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class MarcarCotizacionesVencidasCommand extends Command
{
    protected $signature = 'cotizaciones:marcar-vencidas';

    protected $description = 'Marca como vencidas las cotizaciones cuya fecha_vencimiento ya pasó (sin tocar facturadas/rechazadas).';

    public function handle(): int
    {
        $actualizadas = Cotizacion::vencidas()->update(['estado' => 'vencida']);

        Cache::put('cotizaciones:marcar_vencidas_at', now()->toIso8601String(), now()->addHours(6));

        $this->info("Cotizaciones marcadas como vencidas: {$actualizadas}");

        return self::SUCCESS;
    }
}
