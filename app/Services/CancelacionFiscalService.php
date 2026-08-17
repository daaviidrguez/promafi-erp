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
     * Evita consultas duplicadas (doble clic / reenvío) que consumen folio en Facturama.
     */
    public function consultaEstatusReciente(Factura|ComplementoPago $documento, int $segundos = 60): bool
    {
        return $documento->cancelacionEventos()
            ->where('tipo', 'consulta')
            ->where('created_at', '>=', now()->subSeconds(max(1, $segundos)))
            ->exists();
    }

    /**
     * Lock corto contra dos POSTs concurrentes de la misma consulta.
     */
    public function adquirirLockConsultaEstatus(Factura|ComplementoPago $documento, int $segundos = 60): ?\Illuminate\Contracts\Cache\Lock
    {
        $clave = 'cfdi-consulta-estatus:'.$documento::class.':'.$documento->id;
        $lock = \Illuminate\Support\Facades\Cache::lock($clave, max(5, $segundos));

        return $lock->get() ? $lock : null;
    }

    public function mensajeConsultaDuplicada(): string
    {
        return 'Ya se consultó el estatus hace unos segundos. Espere un momento antes de volver a consultar (cada consulta puede consumir folio en Facturama).';
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    public function camposDesdeResultado(Factura|ComplementoPago $documento, array $resultado, bool $esSolicitud = false): array
    {
        $updates = [];
        $statusNuevo = EstatusCancelacionCfdi::normalizarStatusPac($resultado['status_pac'] ?? null);
        $statusPac = EstatusCancelacionCfdi::resolverStatusPac(
            $documento->estatus_cancelacion_pac ?? null,
            $statusNuevo
        );
        $estatusSat = EstatusCancelacionCfdi::normalizarEstatusSat($resultado['estatus_sat'] ?? null);
        $codigo = $resultado['codigo_estatus'] ?? null;
        if ($statusPac === null && FacturamaService::decodificarAcuseCancelacionXml($documento->acuse_cancelacion) !== null
            && EstatusCancelacionCfdi::esCanceladaSat('canceled', $estatusSat, is_string($codigo) ? $codigo : null)) {
            $statusPac = 'canceled';
        }
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
        $acuse = FacturamaService::normalizarAcuseCancelacionXml($resultado['acuse'] ?? null);
        if ($acuse !== null) {
            $updates['acuse_cancelacion'] = $acuse;
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
        } elseif ($esSolicitud && ($resultado['solicitud_aceptada'] ?? false) && $documento instanceof Factura && $esAdmin) {
            $updates['fecha_cancelacion_pac'] = $documento->fecha_cancelacion_pac ?? now();
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
    ): array {
        $resultado = $this->fusionarConEstadoGuardado($documento, $resultado);
        $updates = array_merge($this->camposDesdeResultado($documento, $resultado, $tipoEvento === 'solicitud'), $extraUpdates);
        if ($updates !== []) {
            $documento->update($updates);
            $documento->refresh();
        }

        $this->registrarEvento($documento, $tipoEvento, $resultado);

        return $resultado;
    }

    /**
     * Conserva evidencia PAC ya guardada (canceled, acuse, código) si la consulta SAT/PAC viene incompleta.
     *
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    public function fusionarConEstadoGuardado(Factura|ComplementoPago $documento, array $resultado): array
    {
        $status = EstatusCancelacionCfdi::resolverStatusPac(
            $documento->estatus_cancelacion_pac ?? null,
            $resultado['status_pac'] ?? null
        );
        $acuseGuardado = FacturamaService::normalizarAcuseCancelacionXml($documento->acuse_cancelacion);
        $codigoFusion = $resultado['codigo_estatus'] ?? $documento->codigo_estatus_cancelacion ?? null;
        $estatusSatFusion = $resultado['estatus_sat'] ?? $documento->estatus_sat ?? null;
        if ($status === null && $acuseGuardado !== null && EstatusCancelacionCfdi::esCanceladaSat('canceled', $estatusSatFusion, $codigoFusion)) {
            $status = 'canceled';
        }
        $resultado['status_pac'] = $status;

        $acuseNuevo = FacturamaService::normalizarAcuseCancelacionXml($resultado['acuse'] ?? null);
        if ($acuseNuevo !== null) {
            $resultado['acuse'] = $acuseNuevo;
        } elseif ($acuseGuardado !== null) {
            $resultado['acuse'] = $acuseGuardado;
        } else {
            $resultado['acuse'] = null;
        }

        $codigoNuevo = $resultado['codigo_estatus'] ?? null;
        $codigoGuardado = $documento->codigo_estatus_cancelacion ?? null;
        if (($codigoNuevo === null || $codigoNuevo === '') && $codigoGuardado && $codigoGuardado !== 'ADM') {
            $resultado['codigo_estatus'] = $codigoGuardado;
        }

        if (empty($resultado['request_date']) && ! empty($documento->fecha_solicitud_cancelacion)) {
            $resultado['request_date'] = $documento->fecha_solicitud_cancelacion;
        }
        if (empty($resultado['expiration_date']) && ! empty($documento->fecha_vencimiento_aceptacion)) {
            $resultado['expiration_date'] = $documento->fecha_vencimiento_aceptacion;
        }
        if (empty($resultado['motivo_sat']) && ! empty($documento->motivo_cancelacion)) {
            $resultado['motivo_sat'] = $documento->motivo_cancelacion;
        }
        if (empty($resultado['uuid_sustitucion']) && ! empty($documento->uuid_sustitucion_cancelacion)) {
            $resultado['uuid_sustitucion'] = $documento->uuid_sustitucion_cancelacion;
        }

        $resultado['solicitud_aceptada'] = EstatusCancelacionCfdi::solicitudAceptadaPorPac($status)
            || (bool) ($resultado['solicitud_aceptada'] ?? false);
        $resultado['cancelada_sat'] = EstatusCancelacionCfdi::esCanceladaSat(
            $status,
            $resultado['estatus_sat'] ?? $documento->estatus_sat ?? null,
            $resultado['codigo_estatus'] ?? null
        );
        $esAdmin = $documento instanceof Factura && (bool) $documento->cancelacion_administrativa;
        $resultado['message'] = EstatusCancelacionCfdi::mensajeUsuario($resultado, $esAdmin);

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    public function registrarEvento(Factura|ComplementoPago $documento, string $tipo, array $resultado): void
    {
        $payload = $resultado['payload'] ?? null;
        $payload = is_array($payload) ? $this->sanitizarPayload($payload) : [];
        $extra = array_filter([
            'motivo_sat' => $resultado['motivo_sat'] ?? null,
            'uuid_sustitucion' => $resultado['uuid_sustitucion'] ?? null,
        ]);
        $payload = array_merge($payload, $extra);

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
            'payload' => $payload === [] ? null : $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    public function mensajePara(Factura|ComplementoPago $documento, array $resultado): string
    {
        $esAdmin = $documento instanceof Factura && (bool) $documento->cancelacion_administrativa;
        $resultado = $this->fusionarConEstadoGuardado($documento, $resultado);

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
