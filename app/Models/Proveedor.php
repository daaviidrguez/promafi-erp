<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}
