<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\NotaCredito;
use App\Models\DocumentoRelacionadoPago;
use App\Models\CuentaPorCobrar;
use Carbon\Carbon;

class EstadoCuentaService
{
    /**
     * Construye los movimientos del estado de cuenta de un cliente.
     *
     * @param Cliente $cliente
     * @param string|null $fechaDesde Y-m-d
     * @param string|null $fechaHasta Y-m-d
     * @param bool $soloReporteCobranza Si true, solo facturas a crédito con pendiente y sus pagos/NC
     * @return array{ movimientos: array, total_cargos: float, total_abonos: float, saldo_final: float, cliente: Cliente }
     */
    public function movimientosCliente(
        Cliente $cliente,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        bool $soloReporteCobranza = false
    ): array {
        $facturaIdsConPendiente = collect();
        if ($soloReporteCobranza) {
            $facturaIdsConPendiente = CuentaPorCobrar::where('cliente_id', $cliente->id)
                ->where('monto_pendiente', '>', 0)
                ->pluck('factura_id');
            if ($facturaIdsConPendiente->isEmpty()) {
                return [
                    'movimientos' => [],
                    'total_cargos' => 0,
                    'total_abonos' => 0,
                    'saldo_final' => 0,
                    'cliente' => $cliente,
                ];
            }
        }

        $movimientos = [];

        // Facturas (cargos) — no canceladas
        $queryFacturas = Factura::where('cliente_id', $cliente->id)
            ->where('estado', '!=', 'cancelada');
        if ($soloReporteCobranza) {
            $queryFacturas->whereIn('id', $facturaIdsConPendiente);
        }
        if ($fechaDesde) {
            $queryFacturas->whereDate('fecha_emision', '>=', $fechaDesde);
        }
        if ($fechaHasta) {
            $queryFacturas->whereDate('fecha_emision', '<=', $fechaHasta);
        }
        foreach ($queryFacturas->orderBy('fecha_emision')->orderBy('id')->get() as $f) {
            $movimientos[] = [
                'fecha' => Carbon::parse($f->fecha_emision),
                'orden' => 1,
                'tipo' => 'Factura',
                'referencia' => $f->folio_completo,
                'descripcion' => 'Factura ' . $f->folio_completo,
                'cargo' => (float) $f->total,
                'abono' => 0,
                'factura_id' => $f->id,
                'documento_id' => $f->id,
            ];
        }

        // Notas de crédito (abonos)
        $queryNC = NotaCredito::with('factura')->where('cliente_id', $cliente->id);
        if ($soloReporteCobranza) {
            $queryNC->whereIn('factura_id', $facturaIdsConPendiente);
        }
        if ($fechaDesde) {
            $queryNC->whereDate('fecha_emision', '>=', $fechaDesde);
        }
        if ($fechaHasta) {
            $queryNC->whereDate('fecha_emision', '<=', $fechaHasta);
        }
        foreach ($queryNC->orderBy('fecha_emision')->orderBy('id')->get() as $nc) {
            $movimientos[] = [
                'fecha' => Carbon::parse($nc->fecha_emision),
                'orden' => 2,
                'tipo' => 'Nota de Crédito',
                'referencia' => $nc->folio_completo,
                'descripcion' => 'Nota de Crédito ' . $nc->folio_completo . ($nc->factura ? ' (Factura ' . $nc->factura->folio_completo . ')' : ''),
                'cargo' => 0,
                'abono' => (float) $nc->total,
                'factura_id' => $nc->factura_id,
                'documento_id' => $nc->id,
            ];
        }

        // Pagos (abonos) vía documentos relacionados
        $queryDoc = DocumentoRelacionadoPago::whereHas('factura', fn ($q) => $q->where('cliente_id', $cliente->id))
            ->with(['factura', 'pagoRecibido.complementoPago']);
        if ($soloReporteCobranza) {
            $queryDoc->whereIn('factura_id', $facturaIdsConPendiente);
        }
        $docs = $queryDoc->get();
        foreach ($docs as $doc) {
            $pago = $doc->pagoRecibido;
            $cp = $pago->complementoPago ?? null;
            $fechaPago = $pago->fecha_pago ?? $cp->fecha_emision ?? now();
            $fechaPago = Carbon::parse($fechaPago);
            if ($fechaDesde && $fechaPago->format('Y-m-d') < $fechaDesde) {
                continue;
            }
            if ($fechaHasta && $fechaPago->format('Y-m-d') > $fechaHasta) {
                continue;
            }
            $folioCp = $cp ? ($cp->serie . '-' . str_pad($cp->folio, 6, '0', STR_PAD_LEFT)) : '';
            $movimientos[] = [
                'fecha' => $fechaPago,
                'orden' => 3,
                'tipo' => 'Pago',
                'referencia' => $folioCp,
                'descripcion' => 'Pago ' . $folioCp . ' aplicado a Factura ' . ($doc->factura->folio_completo ?? ''),
                'cargo' => 0,
                'abono' => (float) $doc->monto_pagado,
                'factura_id' => $doc->factura_id,
                'documento_id' => $doc->id,
            ];
        }

        // Ordenar por fecha y orden secundario
        usort($movimientos, function ($a, $b) {
            $c = $a['fecha']->getTimestamp() <=> $b['fecha']->getTimestamp();
            return $c !== 0 ? $c : ($a['orden'] <=> $b['orden']);
        });

        // Calcular saldo acumulado
        $saldo = 0.0;
        foreach ($movimientos as &$m) {
            $saldo += $m['cargo'] - $m['abono'];
            $m['saldo'] = round($saldo, 2);
        }
        unset($m);

        $totalCargos = array_sum(array_column($movimientos, 'cargo'));
        $totalAbonos = array_sum(array_column($movimientos, 'abono'));
        $saldoFinal = round($totalCargos - $totalAbonos, 2);

        return [
            'movimientos' => $movimientos,
            'total_cargos' => $totalCargos,
            'total_abonos' => $totalAbonos,
            'saldo_final' => $saldoFinal,
            'cliente' => $cliente,
        ];
    }
}
