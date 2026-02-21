<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetodoPago extends Model
{
    protected $table = 'metodos_pago';

    protected $fillable = ['clave', 'descripcion', 'activo', 'orden'];

    protected $casts = ['activo' => 'boolean'];

    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('orden')->orderBy('clave');
    }

    public function getEtiquetaAttribute(): string
    {
        return $this->clave . ' - ' . $this->descripcion;
    }
}
