<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaExcepcion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'auditoria_excepciones';

    protected $fillable = [
        'user_id',
        'excepcion',
        'mensaje',
        'archivo',
        'metodo',
        'ruta',
        'ruta_nombre',
        'ip',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
