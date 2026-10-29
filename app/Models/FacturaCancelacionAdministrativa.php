<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaCancelacionAdministrativa extends Model
{
    public const TIPO_CANCELACION = 'cancelacion';

    public const TIPO_REVERSION = 'reversion';

    protected $table = 'factura_cancelaciones_administrativas';

    protected $fillable = [
        'factura_id',
        'tipo',
        'user_id',
        'motivo',
        'ip_address',
        'user_agent',
        'detalle',
    ];

    protected $casts = [
        'detalle' => 'array',
    ];

    public function esCancelacion(): bool
    {
        return ($this->tipo ?? self::TIPO_CANCELACION) === self::TIPO_CANCELACION;
    }

    public function esReversion(): bool
    {
        return $this->tipo === self::TIPO_REVERSION;
    }

    /**
     * Estado operativo de una cancelación: vigente si no hay reversión posterior.
     */
    public function estadoOperacion(): string
    {
        if ($this->esReversion()) {
            return 'reversion';
        }

        if (array_key_exists('estado_operacion_attr', $this->attributes)) {
            return (string) $this->attributes['estado_operacion_attr'];
        }

        return $this->tieneReversionPosterior() ? 'revertida' : 'vigente';
    }

    public function estaVigente(): bool
    {
        return $this->estadoOperacion() === 'vigente';
    }

    public function tieneReversionPosterior(): bool
    {
        return static::query()
            ->where('factura_id', $this->factura_id)
            ->where('tipo', self::TIPO_REVERSION)
            ->where('id', '>', $this->id)
            ->exists();
    }

    public function reversionPosterior(): ?self
    {
        return static::query()
            ->where('factura_id', $this->factura_id)
            ->where('tipo', self::TIPO_REVERSION)
            ->where('id', '>', $this->id)
            ->orderBy('id')
            ->first();
    }

    public function scopeSoloCancelaciones(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('tipo', self::TIPO_CANCELACION)->orWhereNull('tipo');
        });
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
