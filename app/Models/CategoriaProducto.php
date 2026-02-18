<?php

namespace App\Models;

// UBICACIÓN: app/Models/CategoriaProducto.php

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoriaProducto extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'categorias_productos';

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'parent_id',
        'color',
        'icono',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    /**
     * Categoría padre
     */
    public function parent()
    {
        return $this->belongsTo(CategoriaProducto::class, 'parent_id');
    }

    /**
     * Subcategorías (hijos)
     */
    public function children()
    {
        return $this->hasMany(CategoriaProducto::class, 'parent_id');
    }

    /**
     * Productos de esta categoría
     */
    public function productos()
    {
        return $this->hasMany(Producto::class, 'categoria_id');
    }

    /**
     * Verificar si es categoría raíz
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Scope para categorías activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para categorías raíz
     */
    public function scopeRaiz($query)
    {
        return $query->whereNull('parent_id');
    }
}