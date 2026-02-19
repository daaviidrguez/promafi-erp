<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClienteContacto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cliente_id',
        'nombre',
        'puesto',
        'departamento',
        'email',
        'telefono',
        'celular',
        'principal',
        'activo',
        'notas',
    ];

    protected $casts = [
        'principal' => 'boolean',
        'activo' => 'boolean',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
}