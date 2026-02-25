<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListaPrecioDetalle extends Model
{
    protected $table = 'listas_precios_detalle';

    protected $fillable = ['lista_precio_id', 'producto_id', 'tipo_utilidad', 'valor_utilidad', 'orden', 'activo'];

    protected $casts = [
        'valor_utilidad' => 'decimal:4',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Precio resultante según costo del producto y tipo de utilidad.
     * Factorizado (Markup): precio = costo * valor (ej. 1.30 → 30% markup)
     * Margen (Utilidad Real): precio = costo / valor (ej. 0.70 → 30% margen)
     */
    public function getPrecioResultanteAttribute(): float
    {
        $producto = $this->producto;
        if (!$producto) {
            return 0;
        }
        $costo = (float) ($producto->costo_promedio_mostrar ?? $producto->costo ?? 0);
        $pct = max(1, min(99, (float) $this->valor_utilidad)) / 100;
        if ($this->tipo_utilidad === 'factorizado') {
            return round($costo * (1 + $pct), 2);
        }
        if ($pct >= 1) {
            return round($costo, 2);
        }
        return round($costo / (1 - $pct), 2);
    }
}
