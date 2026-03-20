<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemisionDetalle extends Model
{
    use HasFactory;

    protected $table = 'remisiones_detalle';

    protected $fillable = [
        'remision_id',
        'producto_id',
        'codigo',
        'descripcion',
        'cantidad',
        'unidad',
        // Snapshot tipo cotización (trazabilidad fiscal/administrativa)
        'precio_unitario',
        'tasa_iva',
        'subtotal',
        'iva_monto',
        'total',
        'orden',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'tasa_iva' => 'decimal:4',
        'subtotal' => 'decimal:2',
        'iva_monto' => 'decimal:2',
        'total' => 'decimal:2',
        'orden' => 'integer',
    ];

    public function remision(): BelongsTo
    {
        return $this->belongsTo(Remision::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
