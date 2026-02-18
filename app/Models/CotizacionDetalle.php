<?php

namespace App\Models;

// UBICACIÓN: app/Models/CotizacionDetalle.php

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionDetalle extends Model
{
    use HasFactory;

    protected $table = 'cotizaciones_detalle';

    protected $fillable = [
        'cotizacion_id',
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

    /**
     * Relación con Cotización
     */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    /**
     * Relación con Producto
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Calcular importes automáticamente
     */
    public static function calcularImportes(array $datos): array
    {
        $cantidad = floatval($datos['cantidad']);
        $precioUnitario = floatval($datos['precio_unitario']);
        $descuentoPorcentaje = floatval($datos['descuento_porcentaje'] ?? 0);
        $tasaIva = isset($datos['tasa_iva']) ? floatval($datos['tasa_iva']) : null;

        // Subtotal
        $subtotal = $cantidad * $precioUnitario;

        // Descuento
        $descuentoMonto = $subtotal * ($descuentoPorcentaje / 100);

        // Base imponible
        $baseImponible = $subtotal - $descuentoMonto;

        // IVA
        $ivaMonto = 0;
        if ($tasaIva !== null) {
            $ivaMonto = $baseImponible * $tasaIva;
        }

        // Total
        $total = $baseImponible + $ivaMonto;

        return [
            'subtotal' => round($subtotal, 2),
            'descuento_monto' => round($descuentoMonto, 2),
            'base_imponible' => round($baseImponible, 2),
            'iva_monto' => round($ivaMonto, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Boot del modelo para calcular importes automáticamente
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($detalle) {
            if (!$detalle->subtotal) {
                $importes = self::calcularImportes($detalle->toArray());
                $detalle->fill($importes);
            }
        });

        static::updating(function ($detalle) {
            $importes = self::calcularImportes($detalle->toArray());
            $detalle->fill($importes);
        });
    }
}