<?php

namespace App\Models;

// UBICACIÓN: app/Models/FacturaDetalle.php

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacturaDetalle extends Model
{
    use HasFactory;

    protected $table = 'facturas_detalle';

    protected $fillable = [
        'factura_id',
        'producto_id',
        'clave_prod_serv',
        'clave_unidad',
        'unidad',
        'no_identificacion',
        'descripcion',
        'cantidad',
        'valor_unitario',
        'importe',
        'descuento',
        'base_impuesto',
        'objeto_impuesto',
        'orden',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'valor_unitario' => 'decimal:6',
        'importe' => 'decimal:2',
        'descuento' => 'decimal:2',
        'base_impuesto' => 'decimal:2',
        'costo_unitario_timbrado' => 'decimal:6',
        'orden' => 'integer',
    ];

    /**
     * Relación con Factura
     */
    public function factura()
    {
        return $this->belongsTo(Factura::class);
    }

    /**
     * Relación con Producto
     */
    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Relación con Impuestos
     */
    public function impuestos()
    {
        return $this->hasMany(FacturaImpuesto::class);
    }

    /**
     * Costo unitario para reportes de utilidad: snapshot al timbrado si existe; si no, catálogo (facturas legacy).
     */
    public function costoUnitarioParaReporteUtilidad(): float
    {
        if ($this->costo_unitario_timbrado !== null) {
            return (float) $this->costo_unitario_timbrado;
        }
        $this->loadMissing('producto');
        if ($this->producto_id && $this->producto) {
            return (float) ($this->producto->costo ?? $this->producto->costo_promedio ?? 0);
        }

        return 0.0;
    }

    /**
     * Persiste el costo unitario vigente del producto (o 0 sin producto) una sola vez al timbrar.
     */
    public function aplicarSnapshotCostoAlTimbrado(): void
    {
        if ($this->costo_unitario_timbrado !== null) {
            return;
        }
        $this->loadMissing('producto');
        if ($this->producto_id && $this->producto) {
            $this->costo_unitario_timbrado = (float) ($this->producto->costo ?? $this->producto->costo_promedio ?? 0);
        } else {
            $this->costo_unitario_timbrado = 0.0;
        }
        $this->save();
    }

    /**
     * Calcular subtotal (cantidad * valor_unitario)
     */
    public function getSubtotalAttribute(): float
    {
        return $this->cantidad * $this->valor_unitario;
    }

    /**
     * Calcular total (subtotal - descuento)
     */
    public function getTotalAttribute(): float
    {
        return $this->subtotal - $this->descuento;
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-calcular importes antes de guardar
        static::saving(function ($detalle) {
            $detalle->importe = $detalle->cantidad * $detalle->valor_unitario;
            $detalle->base_impuesto = $detalle->importe - ($detalle->descuento ?? 0);
        });
    }
}