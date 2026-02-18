<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'codigo',
        'codigo_barras',
        'nombre',
        'descripcion',
        'clave_sat',
        'clave_unidad_sat',
        'unidad',
        'costo',
        'precio_venta',
        'precio_mayoreo',
        'precio_minimo',
        'tasa_iva',
        'aplica_iva',
        'tasa_ieps',
        'stock',
        'stock_minimo',
        'stock_maximo',
        'controla_inventario',
        'categoria_id',
        'imagen_principal',
        'activo',
        'notas',
    ];

    protected $casts = [
        'costo' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'precio_mayoreo' => 'decimal:2',
        'precio_minimo' => 'decimal:2',
        'tasa_iva' => 'decimal:4',
        'aplica_iva' => 'boolean',
        'tasa_ieps' => 'decimal:4',
        'stock' => 'decimal:2',
        'stock_minimo' => 'decimal:2',
        'stock_maximo' => 'decimal:2',
        'controla_inventario' => 'boolean',
        'activo' => 'boolean',
    ];

    /**
     * Relación con Categoría
     */
    public function categoria()
    {
        return $this->belongsTo(CategoriaProducto::class, 'categoria_id');
    }

    /**
     * Calcular precio con IVA
     */
    public function getPrecioConIvaAttribute(): float
    {
        if (!$this->aplica_iva) {
            return (float) $this->precio_venta;
        }

        return (float) ($this->precio_venta * (1 + $this->tasa_iva));
    }

    /**
     * Calcular margen de ganancia
     */
    public function getMargenAttribute(): float
    {
        if ($this->costo == 0) {
            return 0;
        }

        return (($this->precio_venta - $this->costo) / $this->costo) * 100;
    }

    /**
     * Verificar si hay stock disponible
     */
    public function tieneStock(float $cantidad = 1): bool
    {
        if (!$this->controla_inventario) {
            return true;
        }

        return $this->stock >= $cantidad;
    }

    /**
     * Verificar si está bajo en stock
     */
    public function bajoEnStock(): bool
    {
        return $this->controla_inventario && $this->stock <= $this->stock_minimo;
    }

    /**
     * Descontar stock
     */
    public function descontarStock(float $cantidad): void
    {
        if ($this->controla_inventario) {
            $this->decrement('stock', $cantidad);
        }
    }

    /**
     * Aumentar stock
     */
    public function aumentarStock(float $cantidad): void
    {
        if ($this->controla_inventario) {
            $this->increment('stock', $cantidad);
        }
    }

    /**
     * Scope para productos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para productos con stock bajo
     */
    public function scopeBajoStock($query)
    {
        return $query->where('controla_inventario', true)
                    ->whereColumn('stock', '<=', 'stock_minimo');
    }

    /**
     * Scope para búsqueda
     */
    public function scopeBuscar($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('nombre', 'like', "%{$search}%")
              ->orWhere('codigo', 'like', "%{$search}%")
              ->orWhere('codigo_barras', 'like', "%{$search}%")
              ->orWhere('clave_sat', 'like', "%{$search}%");
        });
    }
}