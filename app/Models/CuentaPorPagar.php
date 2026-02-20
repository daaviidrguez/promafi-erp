<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuentaPorPagar extends Model
{
    use HasFactory;

    protected $table = 'cuentas_por_pagar';

    protected $fillable = [
        'orden_compra_id',
        'proveedor_id',
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

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function registrarPago(float $monto): void
    {
        $this->monto_pagado += $monto;
        $this->monto_pendiente -= $monto;
        if ($this->monto_pendiente <= 0) {
            $this->estado = 'pagada';
            $this->monto_pendiente = 0;
        } elseif ($this->monto_pagado > 0) {
            $this->estado = 'parcial';
        }
        $this->save();
    }

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
            if (in_array($this->estado, ['pendiente', 'parcial'])) {
                $this->estado = 'vencida';
            }
        } else {
            $this->dias_vencido = 0;
        }
    }

    public function scopePendientes($query)
    {
        return $query->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->where('monto_pendiente', '>', 0);
    }

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($cuenta) {
            $cuenta->calcularDiasVencido();
        });
    }
}
