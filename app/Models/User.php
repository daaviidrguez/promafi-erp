<?php

namespace App\Models;

// UBICACIÓN: app/Models/User.php
// Este archivo YA EXISTE en Laravel, solo reemplaza su contenido

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // Para tokens de API

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Los atributos que se pueden asignar masivamente.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'activo',
    ];

    /**
     * Los atributos que deben ocultarse en JSON.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Convertir atributos a tipos nativos.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed', // Laravel 11 automáticamente hashea
            'activo' => 'boolean',
        ];
    }

    /**
     * Verificar si es administrador
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Verificar si es vendedor
     */
    public function isVendedor(): bool
    {
        return $this->role === 'vendedor';
    }

    /**
     * Verificar si es contador
     */
    public function isContador(): bool
    {
        return $this->role === 'contador';
    }

    /**
     * Verificar si tiene un rol específico
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Verificar si tiene alguno de los roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    /**
     * Scope para usuarios activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}