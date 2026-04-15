<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Devolucion extends Model
{
    protected $table = 'devoluciones';

    protected $fillable = [
        'factura_id', 'cliente_id', 'empresa_id', 'fecha_devolucion',
        'motivo', 'estado', 'observaciones', 'usuario_id',
    ];

    protected $casts = [
        'fecha_devolucion' => 'date',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DevolucionDetalle::class);
    }

    public function notasCredito(): HasMany
    {
        return $this->hasMany(NotaCredito::class);
    }

    public function getTotalDevueltoAttribute(): float
    {
        $total = 0;
        foreach ($this->detalles as $d) {
            $fd = $d->facturaDetalle;
            if ($fd) {
                $total += (float) $fd->valor_unitario * (float) $d->cantidad_devuelta;
            }
        }
        return round($total, 2);
    }

    public function puedeGenerarNotaCredito(): bool
    {
        return $this->estado === 'autorizada' && $this->detalles->isNotEmpty();
    }

    public function puedeCancelar(): bool
    {
        if ($this->estado !== 'autorizada') {
            return false;
        }

        return ! $this->notasCredito()->exists();
    }
}
