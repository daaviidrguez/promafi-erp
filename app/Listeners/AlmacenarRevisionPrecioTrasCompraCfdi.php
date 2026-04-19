<?php

namespace App\Listeners;

use App\Events\FacturaCompraDesdeCfdiRegistrada;
use App\Services\RevisionPrecioTrasCompraCfdiService;
use Illuminate\Support\Facades\Session;

class AlmacenarRevisionPrecioTrasCompraCfdi
{
    public function __construct(
        protected RevisionPrecioTrasCompraCfdiService $revisionPrecioTrasCompraCfdiService
    ) {}

    public function handle(FacturaCompraDesdeCfdiRegistrada $event): void
    {
        $compra = $event->facturaCompra;

        if (empty(trim((string) ($compra->xml_content ?? '')))) {
            return;
        }

        $items = $this->revisionPrecioTrasCompraCfdiService->detectLineasParaRevision($compra);

        if ($items === []) {
            return;
        }

        Session::put('revision_precio_post_compra', [
            'factura_compra_id' => $compra->id,
            'count' => count($items),
            'items' => $items,
            'banner_dismissed' => false,
        ]);
    }
}
