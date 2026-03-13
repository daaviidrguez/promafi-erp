<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacturaCompraDetalle extends Model
{
    use HasFactory;

    protected $table = 'facturas_compra_detalle';

    protected $fillable = [
        'factura_compra_id',
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

    public function facturaCompra(): BelongsTo
    {
        return $this->belongsTo(FacturaCompra::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function impuestos(): HasMany
    {
        return $this->hasMany(FacturaCompraImpuesto::class);
    }
}
