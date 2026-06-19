<?php

namespace App\Services;

use App\Models\EntradaAnticipada;
use App\Models\InventarioMovimiento;
use App\Models\Producto;

class EntradaAnticipadaInventarioService
{
    /**
     * Registra entradas de inventario al confirmar una EA.
     */
    public function aplicarEntrada(EntradaAnticipada $ea): void
    {
        $ea->loadMissing(['detalles.producto', 'ordenCompra']);

        foreach ($ea->detalles as $detalle) {
            $producto = $detalle->producto;
            if (! $producto || ! $producto->controla_inventario) {
                continue;
            }

            $cantidad = (float) $detalle->cantidad_recibida;
            $costoUnitario = (float) $detalle->precio_unitario_estimado;
            $this->actualizarCostoPromedio($producto, $cantidad, $costoUnitario);

            InventarioMovimiento::registrar(
                $producto,
                InventarioMovimiento::TIPO_ENTRADA_ANTICIPADA,
                $cantidad,
                auth()->id(),
                null,
                null,
                $ea->orden_compra_id,
                null,
                'Entrada anticipada '.$ea->folio,
                $ea->id
            );
        }
    }

    /**
     * Revierte inventario al cancelar una EA confirmada.
     */
    public function revertirEntrada(EntradaAnticipada $ea): void
    {
        $ea->loadMissing(['detalles.producto']);

        foreach ($ea->detalles as $detalle) {
            $producto = $detalle->producto;
            if (! $producto || ! $producto->controla_inventario) {
                continue;
            }

            $cantidad = (float) $detalle->cantidad_recibida;
            $costoUnitario = (float) $detalle->precio_unitario_estimado;
            $stockActual = (float) $producto->stock;
            $stockDespues = $stockActual - $cantidad;

            if ($stockDespues < 0) {
                throw new \InvalidArgumentException(
                    "Stock insuficiente para revertir «{$producto->nombre}». Disponible: {$stockActual}, se requieren {$cantidad}."
                );
            }

            $costoActual = (float) ($producto->costo_promedio ?? $producto->costo ?? 0);
            if ($stockDespues > 0) {
                $nuevoCosto = round((($stockActual * $costoActual) - ($cantidad * $costoUnitario)) / $stockDespues, 2);
                $producto->update(['costo_promedio' => max(0, $nuevoCosto)]);
            } else {
                $producto->update(['costo_promedio' => 0]);
            }

            InventarioMovimiento::registrar(
                $producto,
                InventarioMovimiento::TIPO_SALIDA_ANTICIPADA,
                $cantidad,
                auth()->id(),
                null,
                null,
                $ea->orden_compra_id,
                null,
                'Reversa entrada anticipada '.$ea->folio,
                $ea->id
            );
        }
    }

    /**
     * Ajusta costo promedio cuando el precio fiscal difiere del estimado en EA.
     */
    public function ajustarCostoPorFactura(
        Producto $producto,
        float $cantidadFacturada,
        float $costoEstimado,
        float $costoFiscal
    ): void {
        if (! $producto->controla_inventario) {
            return;
        }

        $diferencia = round($costoFiscal - $costoEstimado, 6);
        if (abs($diferencia) < 0.0001) {
            return;
        }

        $stockActual = (float) $producto->stock;
        if ($stockActual <= 0) {
            return;
        }

        $costoActual = (float) ($producto->costo_promedio ?? $producto->costo ?? 0);
        $unidadesAjustables = min($cantidadFacturada, $stockActual);
        $nuevoCosto = round(
            (($stockActual * $costoActual) + ($unidadesAjustables * $diferencia)) / $stockActual,
            2
        );
        $producto->update(['costo_promedio' => max(0, $nuevoCosto)]);

        InventarioMovimiento::create([
            'producto_id' => $producto->id,
            'tipo' => InventarioMovimiento::TIPO_AJUSTE_COSTO_FACTURA_COMPRA,
            'cantidad' => 0,
            'stock_anterior' => $stockActual,
            'stock_resultante' => $stockActual,
            'usuario_id' => auth()->id(),
            'observaciones' => sprintf(
                'Ajuste costo: estimado %.2f → fiscal %.2f (%d uds.)',
                $costoEstimado,
                $costoFiscal,
                (int) $unidadesAjustables
            ),
        ]);
    }

    /**
     * Revierte el ajuste de costo aplicado al facturar una compra vinculada a EA.
     */
    public function revertirAjusteCostoPorFactura(
        Producto $producto,
        float $cantidadFacturada,
        float $costoEstimado,
        float $costoFiscal
    ): void {
        if (! $producto->controla_inventario) {
            return;
        }

        $diferencia = round($costoFiscal - $costoEstimado, 6);
        if (abs($diferencia) < 0.0001) {
            return;
        }

        $stockActual = (float) $producto->stock;
        if ($stockActual <= 0) {
            return;
        }

        $costoActual = (float) ($producto->costo_promedio ?? $producto->costo ?? 0);
        $unidadesAjustables = min($cantidadFacturada, $stockActual);
        $nuevoCosto = round(
            (($stockActual * $costoActual) - ($unidadesAjustables * $diferencia)) / $stockActual,
            2
        );
        $producto->update(['costo_promedio' => max(0, $nuevoCosto)]);

        InventarioMovimiento::create([
            'producto_id' => $producto->id,
            'tipo' => InventarioMovimiento::TIPO_AJUSTE_COSTO_FACTURA_COMPRA,
            'cantidad' => 0,
            'stock_anterior' => $stockActual,
            'stock_resultante' => $stockActual,
            'usuario_id' => auth()->id(),
            'observaciones' => sprintf(
                'Reversa ajuste costo: fiscal %.2f → estimado %.2f (%d uds.)',
                $costoFiscal,
                $costoEstimado,
                (int) $unidadesAjustables
            ),
        ]);
    }

    private function actualizarCostoPromedio(Producto $producto, float $cantidad, float $costoUnitario): void
    {
        $stockAnterior = (float) $producto->stock;
        $costoActual = (float) ($producto->costo_promedio ?? $producto->costo ?? 0);
        $denominador = $stockAnterior + $cantidad;

        if ($denominador > 0) {
            $nuevoCosto = round(($stockAnterior * $costoActual + $cantidad * $costoUnitario) / $denominador, 2);
            $producto->update(['costo_promedio' => $nuevoCosto]);
        }
    }
}
