<?php

namespace App\Services;

use App\Models\Factura;
use App\Models\FacturaCancelacionAdministrativa;
use App\Models\InventarioMovimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancelación fiscal/administrativa solo en ERP (sin PAC).
 * Revierte saldo del cliente cuando aplica; inventario solo si hubo salida por timbrado en el ERP.
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
}
