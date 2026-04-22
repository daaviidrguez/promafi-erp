<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionCompraDetalle extends Model
{
    use HasFactory;

    protected $table = 'cotizaciones_compra_detalle';

    protected $fillable = [
        'cotizacion_compra_id',
        'producto_id',
        'codigo',
        'descripcion',
        'es_producto_manual',
        'cantidad',
        'precio_unitario',
        'descuento_porcentaje',
        'tasa_iva',
        'subtotal',
        'descuento_monto',
        'base_imponible',
        'iva_monto',
        'total',
        'orden',
    ];

    protected $casts = [
        'es_producto_manual' => 'boolean',
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'descuento_porcentaje' => 'decimal:2',
        'tasa_iva' => 'decimal:4',
        'subtotal' => 'decimal:2',
        'descuento_monto' => 'decimal:2',
        'base_imponible' => 'decimal:2',
        'iva_monto' => 'decimal:2',
        'total' => 'decimal:2',
        'orden' => 'integer',
    ];

    public function cotizacionCompra(): BelongsTo
    {
        return $this->belongsTo(CotizacionCompra::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Importes alineados con el criterio usual de CFDI / facturación: importe de partida
     * = redondeo a 2 decimales de (cantidad × precio unitario), luego descuento, base e IVA
     * redondeados por partida (misma idea que en notas de crédito y líneas de compra por XML).
     */
    public static function calcularImportes(array $datos): array
    {
        $cantidad = floatval($datos['cantidad']);
        $precioUnitario = floatval($datos['precio_unitario']);
        $descuentoPorcentaje = floatval($datos['descuento_porcentaje'] ?? 0);
        $tasaIva = isset($datos['tasa_iva']) ? floatval($datos['tasa_iva']) : null;

        $importePartida = round($cantidad * $precioUnitario, 2);
        $descuentoMonto = round($importePartida * ($descuentoPorcentaje / 100), 2);
        $baseImponible = round($importePartida - $descuentoMonto, 2);
        $ivaMonto = $tasaIva !== null ? round($baseImponible * $tasaIva, 2) : 0.0;
        $total = round($baseImponible + $ivaMonto, 2);

        return [
            'subtotal' => $importePartida,
            'descuento_monto' => $descuentoMonto,
            'base_imponible' => $baseImponible,
            'iva_monto' => $ivaMonto,
            'total' => $total,
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($detalle) {
            if (!$detalle->subtotal) {
                $detalle->fill(self::calcularImportes($detalle->toArray()));
            }
        });
        static::updating(function ($detalle) {
            $detalle->fill(self::calcularImportes($detalle->toArray()));
        });
    }
}
