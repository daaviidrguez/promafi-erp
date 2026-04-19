<?php

namespace App\Services;

use App\Models\FacturaCompra;
use App\Models\Producto;

/**
 * Detecta productos que conviene revisar en precio de venta tras registrar una compra desde CFDI.
 * No modifica precios ni costos.
 */
class RevisionPrecioTrasCompraCfdiService
{
    /** Incremento mínimo sobre el costo de referencia para marcar por alza (10%). */
    public const UMBRAL_INCREMENTO_COSTO = 0.10;

    /**
     * @return list<array{
     *   producto_id:int,
     *   codigo:string,
     *   nombre:string,
     *   costo_anterior:float,
     *   nuevo_costo:float,
     *   precio_venta_actual:float,
     *   margen_porcentaje:float,
     *   motivo:string
     * }>
     */
    public function detectLineasParaRevision(FacturaCompra $compra): array
    {
        $compra->loadMissing(['detalles.producto']);

        $porProducto = [];

        foreach ($compra->detalles as $detalle) {
            if (! $detalle->producto_id || ! $detalle->producto) {
                continue;
            }

            $pid = (int) $detalle->producto_id;
            $nuevo = (float) $detalle->valor_unitario;

            if (! isset($porProducto[$pid])) {
                $porProducto[$pid] = $nuevo;
            } else {
                $porProducto[$pid] = max($porProducto[$pid], $nuevo);
            }
        }

        $items = [];

        foreach ($porProducto as $productoId => $nuevoCosto) {
            /** @var Producto|null $p */
            $p = Producto::query()->find($productoId);
            if (! $p) {
                continue;
            }

            $ultimoRef = $this->costoReferenciaProducto($p);
            $pv = (float) $p->precio_venta;
            $motivo = null;

            if ($p->requiere_revision_precio) {
                $motivo = 'producto_nuevo_cfdi';
            } elseif ($ultimoRef > 0.0001 && $nuevoCosto >= $ultimoRef * (1 + self::UMBRAL_INCREMENTO_COSTO)) {
                $motivo = 'aumento_costo';
            } elseif ($ultimoRef <= 0.0001 && $nuevoCosto > 0) {
                $motivo = 'primer_costo';
            } elseif ($this->precioVentaIgualCostoUnitarioCompra($pv, $nuevoCosto)) {
                $motivo = 'precio_venta_igual_costo_compra';
            }

            if ($motivo === null) {
                continue;
            }
            $margen = $this->margenPorcentajePorDefecto($nuevoCosto, $pv);

            $items[$productoId] = [
                'producto_id' => $productoId,
                'codigo' => (string) $p->codigo,
                'nombre' => (string) $p->nombre,
                'costo_anterior' => round($ultimoRef, 4),
                'nuevo_costo' => round($nuevoCosto, 4),
                'precio_venta_actual' => round($pv, 2),
                'margen_porcentaje' => $margen,
                'motivo' => $motivo,
            ];
        }

        return array_values($items);
    }

    private function costoReferenciaProducto(Producto $p): float
    {
        $cp = (float) ($p->costo_promedio ?? 0);
        $c = (float) ($p->costo ?? 0);

        if ($cp > 0.0001) {
            return $cp;
        }

        return $c;
    }

    /**
     * Precio de venta actual alineado con el costo unitario de esta compra (margen ~0 % sobre el nuevo costo).
     */
    private function precioVentaIgualCostoUnitarioCompra(float $precioVenta, float $nuevoCostoUnitario): bool
    {
        if ($nuevoCostoUnitario <= 0.0001) {
            return false;
        }

        return round($precioVenta, 2) === round($nuevoCostoUnitario, 2);
    }

    private function margenPorcentajePorDefecto(float $nuevoCosto, float $precioVenta): float
    {
        if ($nuevoCosto > 0.0001 && $precioVenta > 0.0001) {
            return round((($precioVenta / $nuevoCosto) - 1) * 100, 2);
        }

        return 30.0;
    }
}
