<?php

namespace App\Models;

// UBICACIÓN: app/Models/DocumentoRelacionadoPago.php
// REEMPLAZA el contenido actual con este

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoRelacionadoPago extends Model
{
    use HasFactory;

    protected $table = 'documentos_relacionados_pago';

    protected $fillable = [
        'pago_recibido_id',
        'factura_id',
        'factura_uuid',       // ← CRÍTICO - debe estar
        'serie',
        'folio',
        'moneda',
        'monto_total',        // ← debe estar
        'parcialidad',        // ← debe estar
        'saldo_anterior',     // ← debe estar
        'monto_pagado',       // ← debe estar
        'saldo_insoluto',     // ← debe estar
    ];

    protected $casts = [
        'monto_total' => 'decimal:2',
        'saldo_anterior' => 'decimal:2',
        'monto_pagado' => 'decimal:2',
        'saldo_insoluto' => 'decimal:2',
    ];

    /**
     * Relación con Pago Recibido
     */
    public function pagoRecibido(): BelongsTo
    {
        return $this->belongsTo(PagoRecibido::class);
    }

    /**
     * Relación con Factura
     */
    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }
}