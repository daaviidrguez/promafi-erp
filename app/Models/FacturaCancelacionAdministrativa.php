<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaCancelacionAdministrativa extends Model
{
    protected $table = 'factura_cancelaciones_administrativas';

    protected $fillable = [
        'factura_id',
        'user_id',
        'motivo',
        'ip_address',
        'user_agent',
        'detalle',
    ];

    protected $casts = [
        'detalle' => 'array',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
