<?php

namespace App\Models;

// UBICACIÓN: app/Models/PagoRecibido.php
// REEMPLAZA el contenido actual con este

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PagoRecibido extends Model
{
    use HasFactory;

    protected $table = 'pagos_recibidos';

    protected $fillable = [
        'complemento_pago_id',
        'fecha_pago',
        'forma_pago',
        'moneda',
        'tipo_cambio',
        'monto',
        'num_operacion',
        'rfc_banco_ordenante',
        'nombre_banco_ordenante',
        'cuenta_ordenante',
        'rfc_banco_beneficiario',
        'cuenta_beneficiario',
        'observaciones',
    ];

    protected $casts = [
        'fecha_pago' => 'datetime',
        'tipo_cambio' => 'decimal:6',
        'monto' => 'decimal:2',
    ];

    /**
     * Relación con Complemento de Pago
     */
    public function complementoPago(): BelongsTo
    {
        return $this->belongsTo(ComplementoPago::class);
    }

    /**
     * Relación con Documentos Relacionados
     */
    public function documentosRelacionados(): HasMany
    {
        return $this->hasMany(DocumentoRelacionadoPago::class);
    }
}