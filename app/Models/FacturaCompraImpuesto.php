<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaCompraImpuesto extends Model
{
    use HasFactory;

    protected $table = 'facturas_compra_impuestos';

    protected $fillable = [
        'factura_compra_detalle_id',
        'tipo',
        'impuesto',
        'tipo_factor',
        'tasa_o_cuota',
        'base',
        'importe',
    ];

    protected $casts = [
        'tasa_o_cuota' => 'decimal:6',
        'base' => 'decimal:2',
        'importe' => 'decimal:2',
    ];

    public function facturaCompraDetalle(): BelongsTo
    {
        return $this->belongsTo(FacturaCompraDetalle::class);
    }
}
