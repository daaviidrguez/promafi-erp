<?php

namespace App\Models;

// UBICACIÓN: app/Models/CuentaPorCobrar.php

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CuentaPorCobrar extends Model
{
    use HasFactory;

    protected $table = 'cuentas_por_cobrar';

    protected $fillable = [
        'factura_id',
        'cliente_id',
        'monto_total',
        'monto_pagado',
        'monto_pendiente',
        'fecha_emision',
        'fecha_vencimiento',
        'dias_vencido',
        'estado',
        'notas',
    ];

    protected $casts = [
        'monto_total' => 'decimal:2',
        'monto_pagado' => 'decimal:2',
        'monto_pendiente' => 'decimal:2',
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'dias_vencido' => 'integer',
    ];

    /**
     * Relación con Factura
     */
    public function factura()
    {
        return $this->belongsTo(Factura::class);
    }

    /**
     * Relación con Cliente
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Registrar un pago
     */
    public function registrarPago(float $monto): void
    {
        $this->monto_pagado += $monto;
        $this->monto_pendiente -= $monto;

        // Actualizar estado
        if ($this->monto_pendiente <= 0) {
            $this->estado = 'pagada';
            $this->monto_pendiente = 0;
        } elseif ($this->monto_pagado > 0) {
            $this->estado = 'parcial';
        }

        $this->save();

        // Actualizar saldo del cliente
        $this->cliente->actualizarSaldo();
    }

    /**
     * Calcular días de vencimiento
     */
    public function calcularDiasVencido(): void
    {
        if (!$this->fecha_vencimiento) {
            $this->dias_vencido = 0;
            return;
        }

        $hoy = Carbon::today();
        $vencimiento = Carbon::parse($this->fecha_vencimiento);

        if ($hoy->greaterThan($vencimiento)) {
            $this->dias_vencido = $hoy->diffInDays($vencimiento);
            
            // Marcar como vencida si está pendiente o parcial
            if (in_array($this->estado, ['pendiente', 'parcial'])) {
                $this->estado = 'vencida';
            }
        } else {
            $this->dias_vencido = 0;
        }
    }

    /**
     * Verificar si está vencida
     */
    public function estaVencida(): bool
    {
        return $this->estado === 'vencida' || 
               ($this->fecha_vencimiento && $this->fecha_vencimiento->isPast());
    }

    /**
     * Verificar si está pagada
     */
    public function estaPagada(): bool
    {
        return $this->estado === 'pagada';
    }

    /**
     * Scope para cuentas vencidas
     */
    public function scopeVencidas($query)
    {
        return $query->where('estado', 'vencida')
                    ->where('monto_pendiente', '>', 0);
    }

    /**
     * Scope para cuentas pendientes
     */
    public function scopePendientes($query)
    {
        return $query->whereIn('estado', ['pendiente', 'parcial', 'vencida'])
                    ->where('monto_pendiente', '>', 0);
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-calcular días vencido al guardar
        static::saving(function ($cuenta) {
            $cuenta->calcularDiasVencido();
        });
    }
}