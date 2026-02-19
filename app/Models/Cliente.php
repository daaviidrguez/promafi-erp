<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'codigo',
        'nombre',
        'nombre_comercial',
        'rfc',
        'regimen_fiscal',
        'uso_cfdi_default',
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'ciudad',
        'estado',
        'codigo_postal',
        'pais',
        'email',
        'telefono',
        'celular',
        'contacto_nombre',
        'contacto_puesto',
        'dias_credito',
        'limite_credito',
        'saldo_actual',
        'descuento_porcentaje',
        'banco',
        'cuenta_bancaria',
        'clabe',
        'activo',
        'notas',
    ];

    protected $casts = [
        'dias_credito' => 'integer',
        'limite_credito' => 'decimal:2',
        'saldo_actual' => 'decimal:2',
        'descuento_porcentaje' => 'decimal:2',
        'activo' => 'boolean',
    ];

     /**
     * Relación con contactos del cliente
     */
    public function contactos()
    {
        return $this->hasMany(ClienteContacto::class);
    }

    public function contactoPrincipal()
    {
        return $this->hasOne(ClienteContacto::class)
                    ->where('principal', true)
                    ->where('activo', true);
    }

    /**
     * Relación con Facturas
     */
    public function facturas()
    {
        return $this->hasMany(Factura::class);
    }

    /**
     * Relación con Cuentas por Cobrar
     */
    public function cuentasPorCobrar()
    {
        return $this->hasMany(CuentaPorCobrar::class);
    }

    /**
     * Facturas vencidas del cliente
     */
    public function facturasVencidas()
    {
        return $this->facturas()
            ->whereHas('cuentaPorCobrar', function($q) {
                $q->where('estado', 'vencida');
            });
    }

    /**
     * Cuentas vencidas
     */
    public function cuentasVencidas()
    {
        return $this->cuentasPorCobrar()
            ->where('estado', 'vencida')
            ->where('monto_pendiente', '>', 0);
    }

    /**
     * Verificar si es cliente a crédito
     */
    public function esCredito(): bool
    {
        return $this->dias_credito > 0;
    }

    /**
     * Verificar si tiene crédito disponible
     */
    public function tieneCreditoDisponible(float $monto = 0): bool
    {
        if (!$this->esCredito()) {
            return false;
        }

        $disponible = $this->limite_credito - $this->saldo_actual;
        return $disponible >= $monto;
    }

    /**
     * Actualizar saldo actual
     */
    public function actualizarSaldo(): void
    {
        $this->saldo_actual = $this->cuentasPorCobrar()
            ->whereIn('estado', ['pendiente', 'parcial', 'vencida'])
            ->sum('monto_pendiente');
        
        $this->save();
    }

    /**
     * Domicilio completo
     */
    public function getDomicilioCompletoAttribute(): string
    {
        if (!$this->calle) {
            return 'Sin domicilio registrado';
        }

        $domicilio = $this->calle . ' ' . $this->numero_exterior;
        
        if ($this->numero_interior) {
            $domicilio .= ' Int. ' . $this->numero_interior;
        }
        
        $domicilio .= ', ' . $this->colonia;
        $domicilio .= ', ' . $this->ciudad;
        $domicilio .= ', ' . $this->estado;
        $domicilio .= ' CP ' . $this->codigo_postal;
        
        return $domicilio;
    }

    /**
     * Scope para clientes activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para búsqueda
     */
    public function scopeBuscar($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('nombre', 'like', "%{$search}%")
              ->orWhere('rfc', 'like', "%{$search}%")
              ->orWhere('codigo', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }
}