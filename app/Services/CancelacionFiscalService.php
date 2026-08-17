<?php

namespace App\Services;

use App\Models\CfdiCancelacionEvento;
use App\Models\ComplementoPago;
use App\Models\Factura;
use App\Models\InventarioMovimiento;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CancelacionFiscalService
{
    /**
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    public function camposDesdeResultado(Factura|ComplementoPago $documento, array $resultado, bool $esSolicitud = false): array
    {
        $updates = [];
        $statusPac = EstatusCancelacionCfdi::normalizarStatusPac($resultado['status_pac'] ?? null);
        $estatusSat = EstatusCancelacionCfdi::normalizarEstatusSat($resultado['estatus_sat'] ?? null);
        $codigo = $resultado['codigo_estatus'] ?? null;
        $esAdmin = $documento instanceof Factura && (bool) $documento->cancelacion_administrativa;

        if ($statusPac !== null) {
            $updates['estatus_cancelacion_pac'] = $statusPac;
        }
        if (! empty($resultado['mensaje_pac'])) {
            $updates['mensaje_cancelacion_pac'] = Str::limit(trim((string) $resultado['mensaje_pac']), 500, '');
        }
        if (! empty($resultado['is_cancelable'])) {
            $updates['is_cancelable'] = Str::limit((string) $resultado['is_cancelable'], 80, '');
        }
        if (! empty($resultado['request_date'])) {
            $updates['fecha_solicitud_cancelacion'] = $this->parseFecha($resultado['request_date']) ?? now();
        } elseif ($esSolicitud && ($resultado['solicitud_aceptada'] ?? false) && empty($documento->fecha_solicitud_cancelacion)) {
            $updates['fecha_solicitud_cancelacion'] = now();
        }
        if (! empty($resultado['expiration_date'])) {
            $updates['fecha_vencimiento_aceptacion'] = $this->parseFecha($resultado['expiration_date']);
        }
        if ($estatusSat !== null) {
            $updates['estatus_sat'] = $estatusSat;
        }
        if (! empty($resultado['acuse'])) {
            $updates['acuse_cancelacion'] = $resultado['acuse'];
        }

        if ($codigo !== null && $codigo !== '') {
            $updates['codigo_estatus_cancelacion'] = (string) $codigo;
        } elseif ($esSolicitud && ($resultado['solicitud_aceptada'] ?? false) && (string) ($documento->codigo_estatus_cancelacion ?? '') === 'ADM') {
            $updates['codigo_estatus_cancelacion'] = null;
        }

        $canceladaSat = EstatusCancelacionCfdi::esCanceladaSat($statusPac, $estatusSat, is_string($codigo) ? $codigo : null);
        if ($canceladaSat) {
            if ($documento instanceof Factura && $esAdmin) {
                $updates['fecha_cancelacion_pac'] = $documento->fecha_cancelacion_pac ?? now();
            } elseif (empty($documento->fecha_cancelacion)) {
                $updates['fecha_cancelacion'] = now();
            }
        }

        return $updates;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $extraUpdates
     */
    public function persistirResultado(
        Factura|ComplementoPago $documento,
        array $resultado,
        string $tipoEvento,
        array $extraUpdates = []
    ): void {
        $updates = array_merge($this->camposDesdeResultado($documento, $resultado, $tipoEvento === 'solicitud'), $extraUpdates);
        if ($updates !== []) {
            $documento->update($updates);
            $documento->refresh();
        }

        $this->registrarEvento($documento, $tipoEvento, $resultado);
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    public function registrarEvento(Factura|ComplementoPago $documento, string $tipo, array $resultado): void
    {
        $payload = $resultado['payload'] ?? null;
        if (is_array($payload)) {
            $payload = $this->sanitizarPayload($payload);
        }

        CfdiCancelacionEvento::create([
            'cancelable_type' => $documento::class,
            'cancelable_id' => $documento->id,
            'tipo' => $tipo,
            'user_id' => auth()->id(),
            'status_pac' => EstatusCancelacionCfdi::normalizarStatusPac($resultado['status_pac'] ?? null),
            'estatus_sat' => EstatusCancelacionCfdi::normalizarEstatusSat($resultado['estatus_sat'] ?? null),
            'codigo_estatus' => $resultado['codigo_estatus'] ?? null,
            'is_cancelable' => $resultado['is_cancelable'] ?? null,
            'mensaje' => $resultado['message'] ?? $resultado['mensaje_pac'] ?? null,
            'payload' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    public function mensajePara(Factura|ComplementoPago $documento, array $resultado): string
    {
        $esAdmin = $documento instanceof Factura && (bool) $documento->cancelacion_administrativa;

        return EstatusCancelacionCfdi::mensajeUsuario($resultado, $esAdmin);
    }

    public function debeRevertirOperacionFactura(Factura $factura, array $resultado): bool
    {
        if ($factura->cancelacion_administrativa) {
            return false;
        }

        return (bool) ($resultado['solicitud_aceptada'] ?? false);
    }

    public function debeRestaurarOperacionFactura(Factura $factura, array $resultado): bool
    {
        if ($factura->cancelacion_administrativa) {
            return false;
        }
        if ($factura->estado !== 'cancelada') {
            return false;
        }

        $statusPac = EstatusCancelacionCfdi::normalizarStatusPac($resultado['status_pac'] ?? null);
        $codigo = $resultado['codigo_estatus'] ?? null;

        return EstatusCancelacionCfdi::esRechazada($statusPac, is_string($codigo) ? $codigo : null)
            || $statusPac === 'active';
    }

    public function revertirOperacionFactura(Factura $factura): void
    {
        $factura->loadMissing(['detalles.producto', 'remisionVinculada', 'cuentaPorCobrar', 'cliente']);

        if (! $factura->inventarioDescontadoEnRemision()) {
            foreach ($factura->detalles as $detalle) {
                if ($detalle->producto && $detalle->producto->controla_inventario) {
                    InventarioMovimiento::registrar(
                        $detalle->producto,
                        InventarioMovimiento::TIPO_DEVOLUCION_FACTURA,
                        (float) $detalle->cantidad,
                        auth()->id(),
                        $factura->id,
                        null,
                        null,
                        null,
                        'Factura cancelada (solicitud fiscal)'
                    );
                }
            }
        }

        if ($factura->cuentaPorCobrar && $factura->cuentaPorCobrar->estado !== 'cancelada') {
            $factura->cuentaPorCobrar->update([
                'estado' => 'cancelada',
                'monto_pendiente' => 0,
            ]);
            $factura->cliente?->actualizarSaldo();
        }
    }

    public function restaurarOperacionFactura(Factura $factura): void
    {
        $factura->loadMissing(['detalles.producto', 'remisionVinculada', 'cuentaPorCobrar', 'cliente']);

        if (! $factura->inventarioDescontadoEnRemision()) {
            foreach ($factura->detalles as $detalle) {
                if ($detalle->producto && $detalle->producto->controla_inventario) {
                    InventarioMovimiento::registrar(
                        $detalle->producto,
                        InventarioMovimiento::TIPO_SALIDA_FACTURA,
                        (float) $detalle->cantidad,
                        auth()->id(),
                        $factura->id,
                        null,
                        null,
                        null,
                        'Rechazo de cancelación SAT (se restaura salida)'
                    );
                }
            }
        }

        $cx = $factura->cuentaPorCobrar;
        if ($cx && $cx->estado === 'cancelada') {
            $pagado = (float) $cx->monto_pagado;
            $total = (float) $cx->monto_total;
            $pendiente = max(0, $total - $pagado);
            $estado = $pendiente <= 0 ? 'pagada' : ($pagado > 0 ? 'parcial' : 'pendiente');
            $cx->update([
                'estado' => $estado,
                'monto_pendiente' => $pendiente,
            ]);
            $factura->cliente?->actualizarSaldo();
        }

        $factura->update(['estado' => 'timbrada']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizarPayload(array $payload): array
    {
        foreach (['AcuseXmlBase64', 'acuseXmlBase64', 'AcuseXml', 'acuseXml'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key]) && strlen($payload[$key]) > 80) {
                $payload[$key] = '[base64 '.strlen($payload[$key]).' chars]';
            }
        }

        return $payload;
    }

    protected function parseFecha(mixed $fecha): ?Carbon
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }
        try {
            return Carbon::parse($fecha);
        } catch (\Throwable) {
            return null;
        }
    }
}
