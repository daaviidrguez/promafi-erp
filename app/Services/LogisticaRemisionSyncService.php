<?php

namespace App\Services;

use App\Models\LogisticaEnvio;
use App\Models\LogisticaEnvioItem;
use App\Models\Remision;
use Illuminate\Support\Facades\DB;

class LogisticaRemisionSyncService
{
    public function syncFromRemision(Remision $remision): void
    {
        $remision->loadMissing('detalles');

        if ($remision->estado === 'cancelada') {
            $envios = LogisticaEnvio::query()->where('remision_id', $remision->id)->get();
            foreach ($envios as $envio) {
                if ($envio->estado !== 'cancelado') {
                    $envio->aplicarEstado('cancelado', auth()->id(), 'Sincronizado: remisión cancelada');
                }
            }

            return;
        }

        if (! in_array($remision->estado, ['enviada', 'entregada'], true)) {
            return;
        }

        $mapEstado = $remision->estado === 'entregada' ? 'entregado' : 'enviado';

        DB::transaction(function () use ($remision, $mapEstado) {
            $envios = LogisticaEnvio::query()->where('remision_id', $remision->id)->lockForUpdate()->get();

            if ($envios->isEmpty()) {
                $envio = new LogisticaEnvio([
                    'remision_id' => $remision->id,
                    'cliente_id' => $remision->cliente_id,
                    'factura_id' => $remision->factura_id,
                    'direccion_entrega' => $remision->direccion_entrega,
                    'usuario_id' => auth()->id(),
                ]);
                $envio->folio = LogisticaEnvio::siguienteFolioEnTransaccion();
                $envio->estado = $mapEstado;
                $envio->save();

                foreach ($remision->detalles as $d) {
                    LogisticaEnvioItem::create([
                        'logistica_envio_id' => $envio->id,
                        'remision_detalle_id' => $d->id,
                        'producto_id' => $d->producto_id,
                        'descripcion' => $d->descripcion,
                        'cantidad' => $d->cantidad,
                    ]);
                }

                $envio->registrarHistorial(null, $mapEstado, auth()->id(), 'Registro generado al cambiar estado de la remisión');

                if ($mapEstado === 'entregado') {
                    $this->marcarTodasLasPartidasEntregadas($envio->id);
                }

                return;
            }

            foreach ($envios as $envio) {
                if ($envio->estado !== $mapEstado) {
                    $envio->aplicarEstado($mapEstado, auth()->id(), 'Sincronizado desde remisión');
                }

                if ($mapEstado === 'entregado') {
                    $this->marcarTodasLasPartidasEntregadas($envio->id);
                }
            }
        });
    }

    /**
     * Remisión marcada como entregada: todas las líneas del envío de logística quedan como entregadas (coherente con estado final).
     */
    private function marcarTodasLasPartidasEntregadas(int $logisticaEnvioId): void
    {
        LogisticaEnvioItem::query()
            ->where('logistica_envio_id', $logisticaEnvioId)
            ->update(['linea_entregada' => true]);
    }
}
