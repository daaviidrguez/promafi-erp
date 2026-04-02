<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticaEnvioHistorial extends Model
{
    public $timestamps = false;

    protected $table = 'logistica_envio_historial';

    protected static function booted(): void
    {
        static::creating(function (self $row) {
            if ($row->created_at === null) {
                $row->created_at = now();
            }
        });
    }

    protected $fillable = [
        'logistica_envio_id',
        'user_id',
        'estado_anterior',
        'estado_nuevo',
        'nota',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function envio(): BelongsTo
    {
        return $this->belongsTo(LogisticaEnvio::class, 'logistica_envio_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
