<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaCancelacionAdministrativa extends Model
{
    public const TIPO_CANCELACION = 'cancelacion';

    public const TIPO_REVERSION = 'reversion';

    protected $table = 'factura_cancelaciones_administrativas';

    protected $fillable = [
        'factura_id',
        'tipo',
        'user_id',
        'motivo',
        'ip_address',
        'user_agent',
        'detalle',
    ];

    protected $casts = [
        'detalle' => 'array',
    ];

    public function esReversion(): bool
    {
        return $this->tipo === self::TIPO_REVERSION;
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
