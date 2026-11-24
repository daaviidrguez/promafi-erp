<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormaPago extends Model
{
    protected $table = 'formas_pago';

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

    /**
     * Etiqueta por clave sin query por fila (mapa en memoria / request).
     */
    public static function etiquetaDe(?string $clave): string
    {
        $clave = trim((string) $clave);
        if ($clave === '') {
            return '';
        }

        static $map = null;
        if ($map === null) {
            $map = self::query()->get(['clave', 'descripcion'])->keyBy('clave');
        }

        $fp = $map->get($clave);

        return $fp ? $fp->etiqueta : $clave;
    }
}
