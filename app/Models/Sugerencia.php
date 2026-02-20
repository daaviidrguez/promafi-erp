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
        $term = '%' . addcslashes($q, '%_\\') . '%';
        $driver = $query->getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            return $query->where(function ($qry) use ($term) {
                $qry->where('codigo', 'ilike', $term)
                    ->orWhere('descripcion', 'ilike', $term);
            });
        }
        return $query->where(function ($qry) use ($term) {
            $qry->whereRaw('LOWER(COALESCE(codigo, "")) LIKE LOWER(?)', [$term])
                ->orWhereRaw('LOWER(descripcion) LIKE LOWER(?)', [$term]);
        });
    }
}
