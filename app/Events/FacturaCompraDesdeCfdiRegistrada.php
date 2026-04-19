<?php

namespace App\Events;

use App\Models\FacturaCompra;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FacturaCompraDesdeCfdiRegistrada
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public FacturaCompra $facturaCompra
    ) {}
}
