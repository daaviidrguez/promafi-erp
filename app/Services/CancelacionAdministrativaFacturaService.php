<?php

namespace App\Services;

use App\Models\Factura;
use App\Models\FacturaCancelacionAdministrativa;
use App\Models\InventarioMovimiento;
use App\Models\Remision;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancelación administrativa solo en ERP (sin PAC).
 * Revierte saldo del cliente cuando aplica; inventario solo si hubo salida por timbrado en el ERP.
 * Incluye reactivación (reverso) cuando el CFDI sigue vigente y no hubo envío fiscal.
 */
class CancelacionAdministrativaFacturaService
{
    public function __construct(
        protected PDFService $pdfService
    ) {}

    public function puedeEjecutar(Factura $factura): array
    {
        if (! $factura->estaTimbrada()) {
            return ['ok' => false, 'mensaje' => 'Solo se pueden cancelar administrativamente facturas timbradas.'];
        }
        if ($factura->estaCancelada()) {
            return ['ok' => false, 'mensaje' => 'La factura ya está cancelada.'];
        }
        if ($factura->tieneDocumentosRelacionados()) {
            return [
                'ok' => false,
                'mensaje' => 'No aplica: existen complementos de pago, notas de crédito o devoluciones vinculadas. Use el flujo fiscal correspondiente.',
            ];
        }
        $cx = $factura->cuentaPorCobrar;
        if ($cx && (float) $cx->monto_pagado > 0) {
            return [
                'ok' => false,
                'mensaje' => 'No aplica: la cuenta por cobrar tiene pagos registrados. Regularice primero los complementos de pago o use el flujo fiscal.',
            ];
        }

        return ['ok' => true, 'mensaje' => null];
    }

    /**
     * Reactivar factura cancelada solo en ERP (espejo de ejecutar).
     * No aplica si ya hubo solicitud/envío al PAC o el SAT canceló el CFDI.
     *
     * @return array{ok: bool, mensaje: ?string}
     */
    public function puedeReactivar(Factura $factura): array
    {
        if (! $factura->cancelacion_administrativa) {
            return ['ok' => false, 'mensaje' => 'La factura no tiene una cancelación administrativa activa.'];
        }
        if ($factura->estado !== 'cancelada') {
            return ['ok' => false, 'mensaje' => 'Solo se pueden reactivar facturas en estado cancelada.'];
        }
        if (empty($factura->uuid)) {
            return ['ok' => false, 'mensaje' => 'La factura no tiene UUID; no se puede reactivar de forma segura.'];
        }
        if ($factura->canceladaAnteSat()) {
            return ['ok' => false, 'mensaje' => 'El CFDI ya está cancelado ante el SAT. No se puede reactivar en el ERP.'];
        }
        if ($factura->solicitudFiscalPendiente()) {
            return ['ok' => false, 'mensaje' => 'Hay una solicitud de cancelación pendiente ante el SAT. Consulte el estatus o espere la respuesta.'];
        }
        if ($factura->cancelacionFiscalRechazada()) {
            return [
                'ok' => false,
                'mensaje' => 'Ya hubo un intento de cancelación fiscal rechazado. No se puede reactivar; use reenvío al SAT o el flujo de sustitución.',
            ];
        }
        if (! empty($factura->fecha_solicitud_cancelacion) || ! empty($factura->estatus_cancelacion_pac)) {
            return [
                'ok' => false,
                'mensaje' => 'Ya se registró actividad de cancelación ante el PAC. No se puede reactivar la cancelación administrativa.',
            ];
        }
        if (! empty($factura->uuid_sustitucion_cancelacion) || $factura->resolverFacturaSustituta()) {
            return [
                'ok' => false,
                'mensaje' => 'Existe una factura sustituta o UUID de sustitución. Use el flujo fiscal; no aplica reactivación.',
            ];
        }
        if ($this->tieneRemisionRefacturada($factura)) {
            return [
                'ok' => false,
                'mensaje' => 'La remisión vinculada ya tiene otra factura vigente. No se puede reactivar esta cancelación administrativa.',
            ];
        }
        if ($factura->tieneDocumentosRelacionados()) {
            return [
                'ok' => false,
                'mensaje' => 'Existen complementos, notas de crédito o devoluciones vinculadas. No se puede reactivar.',
            ];
        }
        $cx = $factura->cuentaPorCobrar;
        if ($cx && (float) $cx->monto_pagado > 0) {
            return [
                'ok' => false,
                'mensaje' => 'La cuenta por cobrar tiene pagos registrados. No se puede reactivar.',
            ];
        }

        return ['ok' => true, 'mensaje' => null];
    }

    public function ejecutar(Factura $factura, string $motivo, ?Request $request = null): Factura
    {
        $motivo = trim($motivo);
        if (strlen($motivo) < 10) {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo debe tener al menos 10 caracteres (auditoría).',
            ]);
        }

        $check = $this->puedeEjecutar($factura);
        if (! $check['ok']) {
            throw ValidationException::withMessages(['factura' => $check['mensaje']]);
        }

        return DB::transaction(function () use ($factura, $motivo, $request) {
            $factura = Factura::lockForUpdate()->with(['detalles.producto', 'cuentaPorCobrar', 'cliente'])->findOrFail($factura->id);

            $check2 = $this->puedeEjecutar($factura);
            if (! $check2['ok']) {
                throw ValidationException::withMessages(['factura' => $check2['mensaje']]);
            }

            $cx = $factura->cuentaPorCobrar;
            $reversaInv = $this->debeReversarInventario($factura);

            $detalleAuditoria = [
                'folio_completo' => $factura->folio_completo,
                'uuid' => $factura->uuid,
                'total' => (float) $factura->total,
                'metodo_pago' => $factura->metodo_pago,
                'cliente_id' => $factura->cliente_id,
                'tenia_cuenta_por_cobrar' => $cx !== null,
                'monto_pendiente_antes' => $cx ? (float) $cx->monto_pendiente : null,
                'reversa_inventario' => $reversaInv,
                'inventario_por_remision' => $factura->inventarioDescontadoEnRemision(),
            ];

            if ($reversaInv) {
                foreach ($factura->detalles as $detalle) {
                    $producto = $detalle->producto;
                    if ($producto && $producto->controla_inventario) {
                        InventarioMovimiento::registrar(
                            $producto,
                            InventarioMovimiento::TIPO_DEVOLUCION_FACTURA,
                            (float) $detalle->cantidad,
                            auth()->id(),
                            $factura->id,
                            null,
                            null,
                            null,
                            'Cancelación administrativa (ERP)'
                        );
                    }
                }
            }

            if ($cx && $cx->estado !== 'cancelada') {
                $cx->update([
                    'estado' => 'cancelada',
                    'monto_pendiente' => 0,
                ]);
                if ($factura->cliente) {
                    $factura->cliente->actualizarSaldo();
                }
            }

            $factura->update([
                'estado' => 'cancelada',
                'fecha_cancelacion' => now(),
                'motivo_cancelacion' => null,
                'acuse_cancelacion' => null,
                'codigo_estatus_cancelacion' => 'ADM',
                'cancelacion_administrativa' => true,
                'cancelacion_administrativa_motivo' => $motivo,
                'cancelacion_administrativa_at' => now(),
                'cancelacion_administrativa_user_id' => auth()->id(),
            ]);

            $pdfPath = $this->pdfService->generarFacturaPDF($factura->fresh());
            $factura->update(['pdf_path' => $pdfPath]);

            FacturaCancelacionAdministrativa::create([
                'factura_id' => $factura->id,
                'tipo' => FacturaCancelacionAdministrativa::TIPO_CANCELACION,
                'user_id' => auth()->id(),
                'motivo' => $motivo,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 2000) : null,
                'detalle' => $detalleAuditoria,
            ]);

            return $factura->fresh(['cliente', 'cuentaPorCobrar']);
        });
    }

    /**
     * Deshace una cancelación administrativa: vuelve a timbrada en ERP, reaplica CxC e inventario.
     * El CFDI nunca se canceló ante el SAT; solo se corrige el estado operativo.
     */
    public function revertir(Factura $factura, string $motivo, ?Request $request = null): Factura
    {
        $motivo = trim($motivo);
        if (strlen($motivo) < 10) {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo debe tener al menos 10 caracteres (auditoría).',
            ]);
        }

        $check = $this->puedeReactivar($factura);
        if (! $check['ok']) {
            throw ValidationException::withMessages(['factura' => $check['mensaje']]);
        }

        return DB::transaction(function () use ($factura, $motivo, $request) {
            $factura = Factura::lockForUpdate()
                ->with(['detalles.producto', 'cuentaPorCobrar', 'cliente', 'cancelacionesAdministrativas'])
                ->findOrFail($factura->id);

            $check2 = $this->puedeReactivar($factura);
            if (! $check2['ok']) {
                throw ValidationException::withMessages(['factura' => $check2['mensaje']]);
            }

            $cx = $factura->cuentaPorCobrar;
            $restauraInv = $this->debeRestaurarInventario($factura);
            $motivoAdminAnterior = $factura->cancelacion_administrativa_motivo;
            $canceladaEn = $factura->cancelacion_administrativa_at;
            $usuarioAdminId = $factura->cancelacion_administrativa_user_id;

            $detalleAuditoria = [
                'accion' => 'reversion',
                'folio_completo' => $factura->folio_completo,
                'uuid' => $factura->uuid,
                'total' => (float) $factura->total,
                'metodo_pago' => $factura->metodo_pago,
                'cliente_id' => $factura->cliente_id,
                'tenia_cuenta_por_cobrar' => $cx !== null,
                'cxc_estado_antes' => $cx?->estado,
                'restaura_inventario' => $restauraInv,
                'inventario_por_remision' => $factura->inventarioDescontadoEnRemision(),
                'motivo_admin_anterior' => $motivoAdminAnterior,
                'cancelacion_administrativa_at' => $canceladaEn?->toIso8601String(),
                'cancelacion_administrativa_user_id' => $usuarioAdminId,
            ];

            if ($restauraInv) {
                foreach ($factura->detalles as $detalle) {
                    $producto = $detalle->producto;
                    if ($producto && $producto->controla_inventario) {
                        InventarioMovimiento::registrar(
                            $producto,
                            InventarioMovimiento::TIPO_SALIDA_FACTURA,
                            (float) $detalle->cantidad,
                            auth()->id(),
                            $factura->id,
                            null,
                            null,
                            null,
                            'Reactivación cancelación administrativa (ERP)'
                        );
                    }
                }
            }

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

            $factura->update([
                'estado' => 'timbrada',
                'fecha_cancelacion' => null,
                'motivo_cancelacion' => null,
                'acuse_cancelacion' => null,
                'codigo_estatus_cancelacion' => null,
                'cancelacion_administrativa' => false,
                'cancelacion_administrativa_motivo' => null,
                'cancelacion_administrativa_at' => null,
                'cancelacion_administrativa_user_id' => null,
            ]);

            $pdfPath = $this->pdfService->generarFacturaPDF($factura->fresh());
            $factura->update(['pdf_path' => $pdfPath]);

            FacturaCancelacionAdministrativa::create([
                'factura_id' => $factura->id,
                'tipo' => FacturaCancelacionAdministrativa::TIPO_REVERSION,
                'user_id' => auth()->id(),
                'motivo' => $motivo,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 2000) : null,
                'detalle' => $detalleAuditoria,
            ]);

            return $factura->fresh(['cliente', 'cuentaPorCobrar']);
        });
    }

    /**
     * Solo revierte inventario si en su momento se registró salida por timbrado en el ERP.
     * Importador CFDI y otros casos sin movimiento de salida no generan reversa.
     */
    public function debeReversarInventario(Factura $factura): bool
    {
        if ($factura->inventarioDescontadoEnRemision()) {
            return false;
        }

        return InventarioMovimiento::where('factura_id', $factura->id)
            ->where('tipo', InventarioMovimiento::TIPO_SALIDA_FACTURA)
            ->exists();
    }

    /**
     * Al reactivar, solo re-sale inventario si la cancelación admin lo había devuelto.
     */
    public function debeRestaurarInventario(Factura $factura): bool
    {
        if ($factura->inventarioDescontadoEnRemision()) {
            return false;
        }

        $ultimaCancelacion = $factura->cancelacionesAdministrativas()
            ->where(function ($q) {
                $q->where('tipo', FacturaCancelacionAdministrativa::TIPO_CANCELACION)
                    ->orWhereNull('tipo');
            })
            ->orderByDesc('id')
            ->first();

        if ($ultimaCancelacion && is_array($ultimaCancelacion->detalle) && array_key_exists('reversa_inventario', $ultimaCancelacion->detalle)) {
            return (bool) $ultimaCancelacion->detalle['reversa_inventario'];
        }

        return InventarioMovimiento::where('factura_id', $factura->id)
            ->where('tipo', InventarioMovimiento::TIPO_DEVOLUCION_FACTURA)
            ->exists();
    }

    protected function tieneRemisionRefacturada(Factura $factura): bool
    {
        return Remision::query()
            ->where('factura_id_cancelada', $factura->id)
            ->whereNotNull('factura_id')
            ->where('factura_id', '!=', $factura->id)
            ->exists();
    }
}
