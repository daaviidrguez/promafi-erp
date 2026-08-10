<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\EntradaAnticipada;
use App\Models\EntradaAnticipadaDetalle;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\ProductoProveedor;
use Illuminate\Support\Facades\DB;

class EntradaAnticipadaService
{
    public function __construct(
        private EntradaAnticipadaInventarioService $inventarioService
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     */
    public function crearDesdeOrden(OrdenCompra $orden, array $lineas, array $encabezado = []): EntradaAnticipada
    {
        if (! $orden->puedeCrearEntradaAnticipada()) {
            throw new \RuntimeException('La orden de compra no admite crear entrada anticipada.');
        }

        $orden->loadMissing(['detalles.producto', 'proveedor', 'empresa']);
        $empresa = Empresa::principal();
        if (! $empresa) {
            throw new \RuntimeException('Configura la empresa primero.');
        }

        return DB::transaction(function () use ($orden, $lineas, $encabezado, $empresa) {
            $ea = EntradaAnticipada::create([
                'folio' => EntradaAnticipada::generarFolio(),
                'estado' => 'borrador',
                'orden_compra_id' => $orden->id,
                'proveedor_id' => $orden->proveedor_id,
                'empresa_id' => $empresa->id,
                'fecha_recepcion' => $encabezado['fecha_recepcion'] ?? now()->toDateString(),
                'moneda' => $orden->moneda ?? 'MXN',
                'tipo_cambio' => $orden->tipo_cambio ?? 1,
                'observaciones' => $encabezado['observaciones'] ?? null,
                'usuario_id' => auth()->id(),
            ]);

            $this->guardarDetalles($ea, $lineas, $orden);
            $this->recalcularTotales($ea);

            return $ea->fresh(['detalles.producto']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     */
    public function crearDirecta(int $proveedorId, array $lineas, array $encabezado = []): EntradaAnticipada
    {
        $empresa = Empresa::principal();
        if (! $empresa) {
            throw new \RuntimeException('Configura la empresa primero.');
        }

        return DB::transaction(function () use ($proveedorId, $lineas, $encabezado, $empresa) {
            $ea = EntradaAnticipada::create([
                'folio' => EntradaAnticipada::generarFolio(),
                'estado' => 'borrador',
                'proveedor_id' => $proveedorId,
                'cotizacion_id' => $encabezado['cotizacion_id'] ?? null,
                'empresa_id' => $empresa->id,
                'fecha_recepcion' => $encabezado['fecha_recepcion'] ?? now()->toDateString(),
                'moneda' => $encabezado['moneda'] ?? 'MXN',
                'tipo_cambio' => $encabezado['tipo_cambio'] ?? 1,
                'observaciones' => $encabezado['observaciones'] ?? null,
                'usuario_id' => auth()->id(),
            ]);

            $this->guardarDetalles($ea, $lineas);
            $this->recalcularTotales($ea);

            return $ea->fresh(['detalles.producto']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     */
    public function actualizarBorrador(EntradaAnticipada $ea, array $lineas, array $encabezado = []): EntradaAnticipada
    {
        if (! $ea->puedeEditarse()) {
            throw new \RuntimeException('Solo se puede editar una entrada anticipada en borrador.');
        }

        return DB::transaction(function () use ($ea, $lineas, $encabezado) {
            $ea->update([
                'fecha_recepcion' => $encabezado['fecha_recepcion'] ?? $ea->fecha_recepcion,
                'observaciones' => $encabezado['observaciones'] ?? $ea->observaciones,
            ]);

            $ea->detalles()->delete();
            $orden = $ea->orden_compra_id ? OrdenCompra::with('detalles')->find($ea->orden_compra_id) : null;
            $this->guardarDetalles($ea, $lineas, $orden);
            $this->recalcularTotales($ea);

            return $ea->fresh(['detalles.producto']);
        });
    }

    public function confirmar(EntradaAnticipada $ea): EntradaAnticipada
    {
        if (! $ea->puedeConfirmarse()) {
            throw new \RuntimeException('Solo se puede confirmar una entrada anticipada en borrador.');
        }

        $ea->loadMissing(['detalles.producto', 'ordenCompra']);

        $this->normalizarImportesDetalle($ea);
        $ea->load('detalles.producto');

        if ($ea->detalles->isEmpty()) {
            throw new \RuntimeException('Agrega al menos una línea con producto.');
        }

        foreach ($ea->detalles as $detalle) {
            if ((float) $detalle->cantidad_recibida <= 0) {
                throw new \RuntimeException('Todas las líneas deben tener cantidad recibida mayor a cero.');
            }
            if ($ea->orden_compra_id && $detalle->orden_compra_detalle_id) {
                $ordenDetalle = $detalle->ordenCompraDetalle;
                $maxPermitido = (float) $ordenDetalle->cantidad - $ea->ordenCompra->cantidadRecibidaEnEntradas($detalle->orden_compra_detalle_id);
                if ((float) $detalle->cantidad_recibida > $maxPermitido + 0.001) {
                    throw new \RuntimeException(
                        "La cantidad recibida de «{$detalle->descripcion}» excede el saldo pendiente de la orden ({$maxPermitido})."
                    );
                }
            }
        }

        return DB::transaction(function () use ($ea) {
            $this->inventarioService->aplicarEntrada($ea);
            $ea->update(['estado' => 'confirmada']);

            if ($ea->ordenCompra && in_array($ea->ordenCompra->estado, ['aceptada', 'en_recepcion'], true)) {
                $ea->ordenCompra->update(['estado' => 'en_recepcion', 'fecha_recepcion' => $ea->fecha_recepcion]);
            }

            return $ea->fresh(['detalles.producto', 'ordenCompra']);
        });
    }

    public function cancelar(EntradaAnticipada $ea): EntradaAnticipada
    {
        if (! $ea->puedeCancelarse()) {
            throw new \RuntimeException('Esta entrada anticipada no puede cancelarse.');
        }

        return DB::transaction(function () use ($ea) {
            if ($ea->estado === 'confirmada') {
                $this->inventarioService->revertirEntrada($ea);
            }
            $ea->update(['estado' => 'cancelada']);

            return $ea->fresh();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     */
    private function guardarDetalles(EntradaAnticipada $ea, array $lineas, ?OrdenCompra $orden = null): void
    {
        foreach ($lineas as $index => $linea) {
            $productoId = (int) ($linea['producto_id'] ?? 0);
            if ($productoId <= 0) {
                throw new \InvalidArgumentException('Todas las líneas requieren un producto del catálogo.');
            }

            $ordenDetalleId = isset($linea['orden_compra_detalle_id']) ? (int) $linea['orden_compra_detalle_id'] : null;
            $cotizacionDetalleId = isset($linea['cotizacion_detalle_id']) ? (int) $linea['cotizacion_detalle_id'] : null;
            $ordenDetalle = null;
            if ($orden && $ordenDetalleId) {
                $ordenDetalle = $orden->detalles->firstWhere('id', $ordenDetalleId);
            }

            $cantidadOrdenada = $ordenDetalle ? (float) $ordenDetalle->cantidad : (float) ($linea['cantidad_ordenada'] ?? 0);
            $precio = $ordenDetalle
                ? (float) $ordenDetalle->precio_unitario
                : (float) ($linea['precio_unitario_estimado'] ?? 0);
            $descuento = $ordenDetalle
                ? (float) ($ordenDetalle->descuento_porcentaje ?? 0)
                : (float) ($linea['descuento_porcentaje'] ?? 0);

            $producto = Producto::find($productoId);
            $tasaIva = EntradaAnticipadaDetalle::resolverTasaIva(
                $producto,
                $linea['tasa_iva'] ?? $ordenDetalle?->tasa_iva
            );

            $codigoProveedor = $linea['codigo_proveedor'] ?? null;
            if (! $codigoProveedor && $ordenDetalle) {
                $codigoProveedor = $ordenDetalle->codigo_proveedor;
            }
            if (! $codigoProveedor) {
                $codigoProveedor = ProductoProveedor::where('producto_id', $productoId)
                    ->where('proveedor_id', $ea->proveedor_id)
                    ->value('codigo');
            }

            $imp = EntradaAnticipadaDetalle::calcularImportes([
                'cantidad_recibida' => $linea['cantidad_recibida'] ?? $linea['cantidad'] ?? 0,
                'precio_unitario_estimado' => $precio,
                'descuento_porcentaje' => $descuento,
                'tasa_iva' => $tasaIva,
            ]);

            EntradaAnticipadaDetalle::create([
                'entrada_anticipada_id' => $ea->id,
                'orden_compra_detalle_id' => $ordenDetalleId,
                'cotizacion_detalle_id' => $cotizacionDetalleId ?: null,
                'producto_id' => $productoId,
                'codigo_proveedor' => $codigoProveedor,
                'descripcion' => $linea['descripcion'] ?? $ordenDetalle?->descripcion ?? '',
                'cantidad_ordenada' => $cantidadOrdenada,
                'cantidad_recibida' => (float) ($linea['cantidad_recibida'] ?? $linea['cantidad'] ?? 0),
                'precio_unitario_estimado' => $precio,
                'descuento_porcentaje' => $descuento,
                'tasa_iva' => $tasaIva,
                'subtotal' => $imp['subtotal'],
                'descuento_monto' => $imp['descuento_monto'],
                'iva_monto' => $imp['iva_monto'],
                'total' => $imp['total'],
                'orden' => $index,
            ]);
        }
    }

    private function recalcularTotales(EntradaAnticipada $ea): void
    {
        $ea->load('detalles');
        $ea->update([
            'subtotal' => round($ea->detalles->sum('subtotal'), 2),
            'descuento' => round($ea->detalles->sum('descuento_monto'), 2),
            'iva' => round($ea->detalles->sum('iva_monto'), 2),
            'total' => round($ea->detalles->sum('total'), 2),
        ]);
    }

    /**
     * Recalcula IVA y totales por línea (p. ej. al confirmar si faltó tasa en borrador).
     */
    public function normalizarImportesDetalle(EntradaAnticipada $ea): void
    {
        $ea->loadMissing('detalles.producto');

        foreach ($ea->detalles as $detalle) {
            $tasaIva = EntradaAnticipadaDetalle::resolverTasaIva($detalle->producto, $detalle->tasa_iva);
            $imp = EntradaAnticipadaDetalle::calcularImportes([
                'cantidad_recibida' => $detalle->cantidad_recibida,
                'precio_unitario_estimado' => $detalle->precio_unitario_estimado,
                'descuento_porcentaje' => $detalle->descuento_porcentaje,
                'tasa_iva' => $tasaIva,
            ]);

            $detalle->update([
                'tasa_iva' => $tasaIva,
                'subtotal' => $imp['subtotal'],
                'descuento_monto' => $imp['descuento_monto'],
                'iva_monto' => $imp['iva_monto'],
                'total' => $imp['total'],
            ]);
        }

        $this->recalcularTotales($ea);
    }

    public function actualizarEstadoOrdenTrasFacturar(OrdenCompra $orden): void
    {
        $orden->loadMissing('detalles');

        $todasRecibidas = true;
        foreach ($orden->detalles as $d) {
            $recibida = $orden->cantidadRecibidaEnEntradas($d->id);
            if ($recibida + 0.001 < (float) $d->cantidad) {
                $todasRecibidas = false;
                break;
            }
        }

        $eaPendientes = $orden->entradasAnticipadas()
            ->whereIn('estado', ['confirmada', 'parcialmente_facturada'])
            ->exists();

        if ($todasRecibidas && ! $eaPendientes) {
            $orden->update(['estado' => 'convertida_compra']);
        } elseif (! $eaPendientes && $orden->estado === 'en_recepcion') {
            // Mantiene en_recepcion si aún hay saldo por recibir
        }
    }

    public function revertirOrdenTrasCancelarFacturacionEa(?OrdenCompra $orden): void
    {
        if (! $orden) {
            return;
        }

        if ($orden->estado === 'convertida_compra') {
            $orden->update(['estado' => 'en_recepcion']);
        }
    }
}
