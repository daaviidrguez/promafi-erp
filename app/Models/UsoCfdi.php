<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsoCfdi extends Model
{
    protected $table = 'usos_cfdi';

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
