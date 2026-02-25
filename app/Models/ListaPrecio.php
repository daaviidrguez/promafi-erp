<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListaPrecio extends Model
{
    protected $table = 'listas_precios';

    protected $fillable = ['nombre', 'descripcion', 'cliente_id', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(ListaPrecioDetalle::class)->orderBy('orden')->orderBy('id');
    }

    public function detallesActivos(): HasMany
    {
        return $this->hasMany(ListaPrecioDetalle::class)->where('activo', true)->orderBy('orden')->orderBy('id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function scopeParaCliente($query, ?int $clienteId)
    {
        if ($clienteId === null) {
            return $query->whereNull('cliente_id');
        }
        return $query->where(function ($q) use ($clienteId) {
            $q->where('cliente_id', $clienteId)->orWhereNull('cliente_id');
        });
    }
}
