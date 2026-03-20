<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteDireccionEntrega extends Model
{
    use HasFactory;

    protected $table = 'clientes_direcciones_entrega';

    protected $fillable = [
        'cliente_id',
        'sucursal_almacen',
        'direccion_completa',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}

