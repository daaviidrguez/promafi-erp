<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntradaAnticipadaDetalle extends Model
{
    protected $table = 'entradas_anticipadas_detalle';

    protected $fillable = [
        'entrada_anticipada_id',
        'orden_compra_detalle_id',
        'cotizacion_detalle_id',
        'producto_id',
        'codigo_proveedor',
        'descripcion',
        'cantidad_ordenada',
        'cantidad_recibida',
        'cantidad_facturada',
        'precio_unitario_estimado',
        'descuento_porcentaje',
        'tasa_iva',
        'subtotal',
        'descuento_monto',
        'iva_monto',
        'total',
        'orden',
    ];

    protected $casts = [
        'cantidad_ordenada' => 'decimal:2',
        'cantidad_recibida' => 'decimal:2',
        'cantidad_facturada' => 'decimal:2',
        'precio_unitario_estimado' => 'decimal:2',
        'descuento_porcentaje' => 'decimal:2',
        'tasa_iva' => 'decimal:4',
        'subtotal' => 'decimal:2',
        'descuento_monto' => 'decimal:2',
        'iva_monto' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function entradaAnticipada(): BelongsTo
    {
        return $this->belongsTo(EntradaAnticipada::class);
    }

    public function ordenCompraDetalle(): BelongsTo
    {
        return $this->belongsTo(OrdenCompraDetalle::class);
    }

    public function cotizacionDetalle(): BelongsTo
    {
        return $this->belongsTo(CotizacionDetalle::class, 'cotizacion_detalle_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function cantidadPendienteFacturar(): float
    {
        return max(0, round((float) $this->cantidad_recibida - (float) $this->cantidad_facturada, 4));
    }

    public function tieneSaldoPorFacturar(): bool
    {
        return $this->cantidadPendienteFacturar() > 0.001;
    }

    public function factorSaldoPorFacturar(): float
    {
        $recibida = (float) $this->cantidad_recibida;
        if ($recibida <= 0.0001) {
            return 0.0;
        }

        return min(1, $this->cantidadPendienteFacturar() / $recibida);
    }

    public static function calcularImportes(array $item): array
    {
        $tasa = $item['tasa_iva'] ?? null;
        if ($tasa === '' || $tasa === false) {
            $tasa = null;
        }

        return CotizacionCompraDetalle::calcularImportes([
            'cantidad' => $item['cantidad'] ?? $item['cantidad_recibida'] ?? 0,
            'precio_unitario' => $item['precio_unitario'] ?? $item['precio_unitario_estimado'] ?? 0,
            'descuento_porcentaje' => $item['descuento_porcentaje'] ?? 0,
            'tasa_iva' => $tasa,
        ]);
    }

    /**
     * Tasa IVA para compra: precio unitario sin IVA; el total de línea/encabezado incluye IVA (como CFDI).
     */
    public static function resolverTasaIva(?Producto $producto, mixed $tasaExplicita): ?float
    {
        if ($tasaExplicita !== null && $tasaExplicita !== '') {
            return (float) $tasaExplicita;
        }

        if (! $producto) {
            return null;
        }

        if (($producto->tipo_factor ?? 'Tasa') === 'Exento' || ! $producto->aplica_iva) {
            return null;
        }

        $tasaProducto = $producto->tasa_iva;
        if ($tasaProducto !== null && $tasaProducto !== '') {
            return (float) $tasaProducto;
        }

        return 0.16;
    }

    public function etiquetaTasaIva(): string
    {
        if ($this->tasa_iva === null) {
            return 'Exento';
        }

        return number_format((float) $this->tasa_iva * 100, 0).'%';
    }
}
