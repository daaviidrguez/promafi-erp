<?php

namespace App\Services;

use App\Models\CotizacionCompraDetalle;
use App\Models\CuentaPorPagar;
use App\Models\Empresa;
use App\Models\FacturaCompra;
use App\Models\FacturaCompraDetalle;
use App\Models\FacturaCompraImpuesto;
use App\Models\OrdenCompra;
use Illuminate\Support\Facades\DB;

class FacturaCompraDesdeOrdenCompraService
{
    public function ordenPuedeConvertirse(OrdenCompra $orden): bool
    {
        if ($orden->estado !== 'aceptada') {
            return false;
        }

        return ! $orden->facturaCompra()->exists();
    }

    /**
     * Crea una factura de compra manual (sin CFDI) a partir de una orden aceptada y marca la orden como convertida.
     * Reasigna la cuenta por pagar de la orden a la compra cuando exista.
     */
    public function crearRegistroDesdeOrden(OrdenCompra $orden): FacturaCompra
    {
        if (! $this->ordenPuedeConvertirse($orden)) {
            throw new \RuntimeException('Solo se puede convertir una orden aceptada que aún no tenga compra asociada.');
        }

        $orden->loadMissing(['detalles.producto', 'proveedor', 'cuentaPorPagar', 'empresa']);
        $proveedor = $orden->proveedor;
        if (! $proveedor) {
            throw new \RuntimeException('La orden no tiene proveedor válido.');
        }

        $empresa = Empresa::principal();
        if (! $empresa) {
            throw new \RuntimeException('Configura la empresa primero.');
        }

        return DB::transaction(function () use ($orden, $proveedor, $empresa) {
            $folioInterno = FacturaCompra::generarFolioInterno();
            $diasCredito = (int) ($orden->dias_credito ?? 0);
            $metodoPago = $diasCredito > 0 ? 'PPD' : 'PUE';

            $sumBruto = 0.0;
            $sumDesc = 0.0;
            $sumIva = 0.0;
            foreach ($orden->detalles as $d) {
                $imp = CotizacionCompraDetalle::calcularImportes([
                    'cantidad' => $d->cantidad,
                    'precio_unitario' => $d->precio_unitario,
                    'descuento_porcentaje' => $d->descuento_porcentaje ?? 0,
                    'tasa_iva' => $d->tasa_iva,
                ]);
                $sumBruto += $imp['subtotal'];
                $sumDesc += $imp['descuento_monto'];
                $sumIva += $imp['iva_monto'];
            }
            $totalEncabezado = round($sumBruto - $sumDesc + $sumIva, 2);

            $fc = FacturaCompra::create([
                'serie' => '',
                'folio' => $folioInterno,
                'folio_interno' => $folioInterno,
                'tipo_comprobante' => 'E',
                'estado' => 'registrada',
                'proveedor_id' => $proveedor->id,
                'empresa_id' => $empresa->id,
                'orden_compra_id' => $orden->id,
                'rfc_emisor' => $proveedor->rfc ?? '',
                'nombre_emisor' => $proveedor->nombre,
                'regimen_fiscal_emisor' => $proveedor->regimen_fiscal ?? $orden->proveedor_regimen_fiscal,
                'rfc_receptor' => $empresa->rfc ?? '',
                'nombre_receptor' => $empresa->razon_social ?? '',
                'regimen_fiscal_receptor' => $empresa->regimen_fiscal ?? null,
                'fecha_emision' => $orden->fecha,
                'forma_pago' => null,
                'metodo_pago' => $metodoPago,
                'moneda' => $orden->moneda ?? 'MXN',
                'tipo_cambio' => $orden->tipo_cambio ?? 1,
                'subtotal' => round($sumBruto, 2),
                'descuento' => round($sumDesc, 2),
                'total' => $totalEncabezado,
                'observaciones' => $orden->observaciones,
                'usuario_id' => auth()->id(),
            ]);

            foreach ($orden->detalles as $index => $d) {
                $producto = $d->producto;
                $item = [
                    'cantidad' => $d->cantidad,
                    'precio_unitario' => $d->precio_unitario,
                    'descuento_porcentaje' => $d->descuento_porcentaje ?? 0,
                    'tasa_iva' => $d->tasa_iva,
                ];
                $imp = CotizacionCompraDetalle::calcularImportes($item);
                $detalle = FacturaCompraDetalle::create([
                    'factura_compra_id' => $fc->id,
                    'producto_id' => $d->producto_id,
                    'clave_prod_serv' => $producto?->clave_sat ?? '01010101',
                    'clave_unidad' => $producto?->clave_unidad_sat ?? 'H87',
                    'unidad' => $producto?->unidad ?? 'Pieza',
                    'no_identificacion' => $producto?->codigo ?? $d->codigo,
                    'descripcion' => $d->descripcion,
                    'cantidad' => $d->cantidad,
                    'valor_unitario' => $d->precio_unitario,
                    'importe' => $imp['subtotal'],
                    'descuento' => $imp['descuento_monto'],
                    'base_impuesto' => $imp['base_imponible'],
                    'objeto_impuesto' => $producto && in_array($producto->objeto_impuesto ?? '02', ['02', '03'], true) ? '02' : '01',
                    'orden' => $index,
                ]);
                if ($imp['iva_monto'] > 0) {
                    FacturaCompraImpuesto::create([
                        'factura_compra_detalle_id' => $detalle->id,
                        'tipo' => 'traslado',
                        'impuesto' => '002',
                        'tipo_factor' => 'Tasa',
                        'tasa_o_cuota' => 0.16,
                        'base' => $imp['base_imponible'],
                        'importe' => $imp['iva_monto'],
                    ]);
                }
            }

            $this->migrarOCrearCuentaPorPagar($orden, $fc, $diasCredito, $metodoPago);

            $orden->update(['estado' => 'convertida_compra']);

            return $fc->fresh();
        });
    }

    /**
     * Vincula una compra ya guardada desde CFDI con la orden indicada (misma sesión de flujo).
     */
    public function vincularFacturaCreadaDesdeCfdi(OrdenCompra $orden, FacturaCompra $fc): void
    {
        if (! $this->ordenPuedeConvertirse($orden)) {
            throw new \RuntimeException('La orden no admite vinculación con esta compra.');
        }
        if ((int) $fc->proveedor_id !== (int) $orden->proveedor_id) {
            throw new \RuntimeException('El proveedor del CFDI no coincide con el de la orden de compra.');
        }
        // Tolerancia por posibles diferencias residuales vs XML timbrado (redondeos SAT por partida).
        if (abs((float) $orden->total - (float) $fc->total) > 0.05) {
            throw new \RuntimeException('El total del CFDI no coincide con el total de la orden de compra.');
        }

        DB::transaction(function () use ($orden, $fc) {
            $fc->update(['orden_compra_id' => $orden->id]);
            $orden->loadMissing('cuentaPorPagar');
            if ($cpp = $orden->cuentaPorPagar) {
                $cpp->update([
                    'factura_compra_id' => $fc->id,
                    'orden_compra_id' => null,
                ]);
            }
            $orden->update(['estado' => 'convertida_compra']);
        });
    }

    private function migrarOCrearCuentaPorPagar(OrdenCompra $orden, FacturaCompra $fc, int $diasCredito, string $metodoPago): void
    {
        if ($cpp = $orden->cuentaPorPagar) {
            $cpp->update([
                'factura_compra_id' => $fc->id,
                'orden_compra_id' => null,
            ]);

            return;
        }

        if ($metodoPago === 'PPD' && $diasCredito > 0) {
            $fechaEmision = $fc->fecha_emision;
            $fechaVencimiento = $fechaEmision->copy()->addDays($diasCredito);
            CuentaPorPagar::create([
                'factura_compra_id' => $fc->id,
                'orden_compra_id' => null,
                'proveedor_id' => $fc->proveedor_id,
                'monto_total' => $fc->total,
                'monto_pagado' => 0,
                'monto_pendiente' => $fc->total,
                'fecha_emision' => $fechaEmision,
                'fecha_vencimiento' => $fechaVencimiento,
                'estado' => 'pendiente',
            ]);
        }
    }
}
