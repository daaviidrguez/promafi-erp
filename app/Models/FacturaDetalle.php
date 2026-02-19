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