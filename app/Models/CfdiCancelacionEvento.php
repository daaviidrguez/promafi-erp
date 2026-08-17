<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CfdiCancelacionEvento extends Model
{
    protected $table = 'cfdi_cancelacion_eventos';

    protected $fillable = [
        'cancelable_type',
        'cancelable_id',
        'tipo',
        'user_id',
        'status_pac',
        'estatus_sat',
        'codigo_estatus',
        'is_cancelable',
        'mensaje',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function cancelable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function etiquetaTipo(): string
    {
        return match ($this->tipo) {
            'solicitud' => 'Solicitud al PAC/SAT',
            'consulta' => 'Consulta de estatus',
            'error' => 'Error',
            default => $this->tipo,
        };
    }
}
