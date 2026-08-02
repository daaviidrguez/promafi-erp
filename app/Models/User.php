<?php

namespace App\Models;

// UBICACIÓN: app/Models/User.php
// Este archivo YA EXISTE en Laravel, solo reemplaza su contenido

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** Nombre del rol (para compatibilidad API y vistas que esperan string) */
    public function getRoleNameAttribute(): ?string
    {
        return $this->role ? $this->role->name : null;
    }

    public function hasPermission(string $key): bool
    {
        if (!$this->role) {
            return false;
        }
        return $this->role->hasPermission($key);
    }

    /**
     * Los atributos que se pueden asignar masivamente.
     */
    protected $fillable = [
        'name',
        'email',
        'avatar',
        'password',
        'role_id',
        'activo',
        'meta_ventas_mensual',
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
            'meta_ventas_mensual' => 'decimal:2',
        ];
    }

    /**
     * Meta mensual de ventas sin IVA (MXN). Sin valor → 0.
     */
    public function metaVentasMensual(): float
    {
        $meta = $this->meta_ventas_mensual;

        return $meta !== null && (float) $meta > 0
            ? (float) $meta
            : 0.0;
    }

    /**
     * ¿Puede tener meta comercial? (rol vendedor).
     */
    public function puedeTenerMetaComercial(): bool
    {
        return $this->isVendedor();
    }

    /**
     * Verificar si es administrador
     */
    public function isAdmin(): bool
    {
        return $this->role && $this->role->name === 'admin';
    }

    /**
     * Verificar si es vendedor
     */
    public function isVendedor(): bool
    {
        return $this->role && $this->role->name === 'vendedor';
    }

    /**
     * Verificar si es contador
     */
    public function isContador(): bool
    {
        return $this->role && $this->role->name === 'contador';
    }

    /**
     * Verificar si tiene un rol específico
     */
    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->name === $roleName;
    }

    /**
     * Verificar si tiene alguno de los roles
     */
    public function hasAnyRole(array $roleNames): bool
    {
        return $this->role && in_array($this->role->name, $roleNames);
    }

    /**
     * Scope para usuarios activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}