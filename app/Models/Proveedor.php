<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'proveedores';

    protected $fillable = [
        'codigo',
        'nombre',
        'nombre_comercial',
        'rfc',
        'regimen_fiscal',
        'uso_cfdi',
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'municipio',
        'estado',
        'codigo_postal',
        'pais',
        'email',
        'telefono',
        'contacto_nombre',
        'dias_credito',
        'banco',
        'cuenta_bancaria',
        'clabe',
        'activo',
        'notas',
    ];

    protected $casts = [
        'dias_credito' => 'integer',
        'activo' => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeBuscar($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('nombre', 'like', "%{$search}%")
                ->orWhere('codigo', 'like', "%{$search}%")
                ->orWhere('rfc', 'like', "%{$search}%");
        });
    }

    public function ordenesCompra(): HasMany
    {
        return $this->hasMany(OrdenCompra::class);
    }

    public function facturasCompra(): HasMany
    {
        return $this->hasMany(FacturaCompra::class);
    }

    public function cuentasPorPagar(): HasMany
    {
        return $this->hasMany(CuentaPorPagar::class);
    }

    /**
     * Registros donde este proveedor tiene un código para el producto.
     */
    public function productoProveedores(): HasMany
    {
        return $this->hasMany(ProductoProveedor::class, 'proveedor_id')
            ->orderByDesc('created_at');
    }

    public function esContado(): bool
    {
        return (int) ($this->dias_credito ?? 0) === 0;
    }
}
