<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenCompraDetalle extends Model
{
    use HasFactory;

    protected $table = 'ordenes_compra_detalle';

    protected $fillable = [
        'orden_compra_id',
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
        'iva_monto',
        'total',
        'orden',
    ];

    protected $casts = [
        'es_producto_manual' => 'boolean',
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'descuento_monto' => 'decimal:2',
        'iva_monto' => 'decimal:2',
        'total' => 'decimal:2',
        'orden' => 'integer',
    ];

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
