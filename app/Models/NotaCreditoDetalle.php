<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaCreditoDetalle extends Model
{
    protected $table = 'notas_credito_detalle';

    protected $fillable = [
        'nota_credito_id',
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
    ];

    public function notaCredito(): BelongsTo
    {
        return $this->belongsTo(NotaCredito::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function impuestos(): HasMany
    {
        return $this->hasMany(NotaCreditoImpuesto::class);
    }
}
