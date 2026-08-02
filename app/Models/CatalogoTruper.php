<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogoTruper extends Model
{
    use HasFactory;

    protected $table = 'catalogo_truper';

    protected $fillable = [
        'codigo',
        'clave',
        'descripcion',
        'unidad',
        'costo',
        'venta',
        'codigo_sat',
        'peso_kg',
        'volumen_cm3',
    ];

    protected $casts = [
        'costo' => 'decimal:4',
        'venta' => 'decimal:4',
        'peso_kg' => 'decimal:4',
        'volumen_cm3' => 'decimal:4',
    ];

    /**
     * Búsqueda por código, clave o descripción.
     * Mínimo 3 caracteres.
     */
    public function scopeBuscar($query, string $q)
    {
        if (strlen($q) < 3) {
            return $query->whereRaw('1 = 0');
        }
        $term = '%' . addcslashes($q, '%_\\') . '%';
        $driver = $query->getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            return $query->where(function ($qry) use ($term) {
                $qry->where('codigo', 'ilike', $term)
                    ->orWhere('clave', 'ilike', $term)
                    ->orWhere('descripcion', 'ilike', $term)
                    ->orWhere('codigo_sat', 'ilike', $term);
            });
        }

        return $query->where(function ($qry) use ($term) {
            $qry->whereRaw('LOWER(COALESCE(codigo, \'\')) LIKE LOWER(?)', [$term])
                ->orWhereRaw('LOWER(COALESCE(clave, \'\')) LIKE LOWER(?)', [$term])
                ->orWhereRaw('LOWER(descripcion) LIKE LOWER(?)', [$term])
                ->orWhereRaw('LOWER(COALESCE(codigo_sat, \'\')) LIKE LOWER(?)', [$term]);
        });
    }
}
