<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sugerencia extends Model
{
    use HasFactory;

    protected $table = 'sugerencias';

    protected $fillable = [
        'codigo',
        'descripcion',
        'unidad',
        'precio_unitario',
    ];

    protected $casts = [
        'precio_unitario' => 'decimal:2',
    ];

    /**
     * Búsqueda por código o descripción (insensible a mayúsculas/minúsculas).
     * Mínimo 3 caracteres.
     */
    public function scopeBuscar($query, string $q)
    {
        if (strlen($q) < 3) {
            return $query->whereRaw('1 = 0');
        }
        $escaped = addcslashes($q, '%_\\');
        $term = '%'.$escaped.'%';
        $prefix = $escaped.'%';
        $driver = $query->getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            return $query->where(function ($qry) use ($term, $prefix) {
                $qry->where('codigo', 'ilike', $prefix)
                    ->orWhere('codigo', 'ilike', $term)
                    ->orWhere('descripcion', 'ilike', $term);
            });
        }

        // utf8mb4_*_ci ya es case-insensitive: LIKE sin LOWER() permite usar índice en codigo.
        return $query->where(function ($qry) use ($term, $prefix) {
            $qry->where('codigo', 'like', $prefix)
                ->orWhere('codigo', 'like', $term)
                ->orWhere('descripcion', 'like', $term);
        });
    }
}
