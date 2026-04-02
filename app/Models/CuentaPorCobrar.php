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
     * Revertir un pago (p. ej. al cancelar un complemento de pago).
     */
    public function revertirPago(float $monto): void
    {
        $this->monto_pagado = max(0, (float) $this->monto_pagado - $monto);
        $this->monto_pendiente = min((float) $this->monto_total, (float) $this->monto_pendiente + $monto);

        if ($this->monto_pendiente >= (float) $this->monto_total) {
            $this->estado = 'pendiente';
            $this->monto_pendiente = (float) $this->monto_total;
        } elseif ($this->monto_pagado > 0) {
            $this->estado = 'parcial';
        } else {
            $this->estado = 'pendiente';
        }

        $this->save();
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
     * Días contra vencimiento en tiempo real.
     * Positivo/0: días para vencer. Negativo: días vencidos.
     * Cuando está pagada, se devuelve null para evitar que siga “actualizándose”.
     */
    public function getDiasContraVencimientoRealtimeAttribute(): ?int
    {
        if ($this->estaPagada()) {
            return null;
        }
        if (!$this->fecha_vencimiento) {
            return null;
        }
        $hoy = Carbon::today();
        $vencimiento = Carbon::parse($this->fecha_vencimiento);
        return (int) $hoy->diffInDays($vencimiento, false);
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
     * Saldo pendiente real (considerando notas de crédito timbradas).
     * Coherente con complemento de pago y estado de cuenta.
     */
    public function getSaldoPendienteRealAttribute(): float
    {
        $montoCubiertoPorNC = (float) \App\Models\NotaCredito::where('factura_id', $this->factura_id)
            ->where('estado', 'timbrada')
            ->sum('total');
        return max(0, (float) $this->monto_pendiente - $montoCubiertoPorNC);
    }

    /**
     * Estado para mostrar (considera saldo_pendiente_real).
     * Coherente con factura show, complemento show, dashboard y tablero.
     */
    public function getEstadoDisplayAttribute(): string
    {
        if ($this->estado === 'cancelada') {
            return 'cancelada';
        }
        if ($this->saldo_pendiente_real <= 0) {
            return 'pagada';
        }
        if ($this->estaVencida()) {
            return 'vencida';
        }
        if ((float) $this->monto_pagado > 0) {
            return 'parcial';
        }
        return 'pendiente';
    }

    /**
     * Excluir cuentas cuya factura está en borrador.
     */
    public function scopeExcluirFacturaBorrador($query)
    {
        return $query->whereHas('factura', fn ($q) => $q->where('estado', '!=', 'borrador'));
    }

    /**
     * Scope para cuentas vencidas (cobranza).
     * Incluye filas con estado vencida en BD y las que siguen pendiente/parcial pero
     * ya superaron fecha_vencimiento — coherente con estado_display y calcularDiasVencido()
     * (el estado en BD no se actualiza a vencida hasta el próximo save).
     */
    public function scopeVencidas($query)
    {
        $hoy = Carbon::today()->toDateString();

        return $query
            ->whereIn('estado', ['pendiente', 'parcial', 'vencida'])
            ->where('monto_pendiente', '>', 0)
            ->where(function ($q) use ($hoy) {
                $q->where('estado', 'vencida')
                    ->orWhere(function ($q2) use ($hoy) {
                        $q2->whereNotNull('fecha_vencimiento')
                            ->whereDate('fecha_vencimiento', '<', $hoy);
                    });
            });
    }

    /**
     * Cuentas al corriente: estado pendiente en BD y aún no vencidas por calendario.
     */
    public function scopePendienteAlCorriente($query)
    {
        $hoy = Carbon::today()->toDateString();

        return $query
            ->where('estado', 'pendiente')
            ->where(function ($q) use ($hoy) {
                $q->whereNull('fecha_vencimiento')
                    ->orWhereDate('fecha_vencimiento', '>=', $hoy);
            });
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