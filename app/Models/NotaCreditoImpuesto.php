<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaCreditoImpuesto extends Model
{
    protected $table = 'notas_credito_impuestos';

    protected $fillable = [
        'nota_credito_detalle_id',
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

    public function notaCreditoDetalle(): BelongsTo
    {
        return $this->belongsTo(NotaCreditoDetalle::class);
    }

    /**
     * Nombre del impuesto para desglose (coherente con FacturaImpuesto).
     */
    public function getNombreImpuestoAttribute(): string
    {
        $nombres = config('impuestos_sat.nombres_display', [
            '001' => 'ISR',
            '002' => 'IVA',
            '003' => 'IEPS',
        ]);

        return $nombres[$this->impuesto] ?? $this->impuesto;
    }
}
