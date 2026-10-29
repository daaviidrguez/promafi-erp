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

    /**
     * Etiqueta compacta del eje fiscal (SAT/PAC) para el index.
     * Distinto de estadoOperacion() (Vigente/Revertida de la operación ERP).
     *
     * @return array{label: string, badge: string, title: ?string}
     */
    public function estadoSatListado(): array
    {
        if ($this->estadoOperacion() === 'revertida') {
            return [
                'label' => 'CFDI vigente (reactivada)',
                'badge' => 'secondary',
                'title' => 'La cancelación administrativa fue deshecha; el CFDI no se canceló ante el SAT.',
            ];
        }

        $factura = $this->factura;
        if (! $factura) {
            return [
                'label' => '—',
                'badge' => 'secondary',
                'title' => null,
            ];
        }

        if ($factura->canceladaAnteSat()) {
            return [
                'label' => 'Cancelada ante el SAT',
                'badge' => 'success',
                'title' => 'El CFDI ya está cancelado ante el SAT.',
            ];
        }

        if ($factura->cancelacionFiscalVencidaSinResolver()) {
            return [
                'label' => 'Plazo SAT vencido',
                'badge' => 'warning',
                'title' => 'El plazo de respuesta venció y el SAT aún reporta el CFDI vigente.',
            ];
        }

        if ($factura->solicitudFiscalPendiente()) {
            return [
                'label' => 'Pendiente SAT',
                'badge' => 'warning',
                'title' => 'Solicitud enviada al SAT; el CFDI puede seguir vigente hasta la respuesta.',
            ];
        }

        if ($factura->cancelacionFiscalRechazada()) {
            return [
                'label' => 'Rechazada',
                'badge' => 'danger',
                'title' => 'El receptor rechazó la cancelación; el CFDI sigue vigente ante el SAT.',
            ];
        }

        return [
            'label' => 'Solo ERP',
            'badge' => 'info',
            'title' => 'Cancelación administrativa en el ERP; el CFDI aún no está cancelado ante el SAT.',
        ];
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
