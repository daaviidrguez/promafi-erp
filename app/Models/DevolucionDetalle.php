<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevolucionDetalle extends Model
{
    protected $table = 'devolucion_detalle';

    protected $fillable = [
        'devolucion_id',
        'factura_detalle_id',
        'producto_id',
        'cantidad_devuelta',
        'motivo_linea',
    ];

    protected $casts = [
        'cantidad_devuelta' => 'decimal:4',
    ];

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class);
    }

    public function facturaDetalle(): BelongsTo
    {
        return $this->belongsTo(FacturaDetalle::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
