<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticaEnvioItem extends Model
{
    protected $table = 'logistica_envio_items';

    protected $fillable = [
        'logistica_envio_id',
        'factura_detalle_id',
        'remision_detalle_id',
        'producto_id',
        'descripcion',
        'cantidad',
        'linea_entregada',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'linea_entregada' => 'boolean',
    ];

    public function envio(): BelongsTo
    {
        return $this->belongsTo(LogisticaEnvio::class, 'logistica_envio_id');
    }

    public function facturaDetalle(): BelongsTo
    {
        return $this->belongsTo(FacturaDetalle::class, 'factura_detalle_id');
    }

    public function remisionDetalle(): BelongsTo
    {
        return $this->belongsTo(RemisionDetalle::class, 'remision_detalle_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
