<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteMetaComercial extends Model
{
    use HasFactory;

    public const PERIODO_ANUAL = 'anual';
    public const PERIODO_MENSUAL = 'mensual';

    protected $table = 'cliente_metas_comerciales';

    protected $fillable = [
        'cliente_id',
        'anio',
        'periodo',
        'monto_meta',
        'notas',
    ];

    protected $casts = [
        'anio' => 'integer',
        'monto_meta' => 'decimal:2',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function esAnual(): bool
    {
        return $this->periodo === self::PERIODO_ANUAL;
    }

    public function esMensual(): bool
    {
        return $this->periodo === self::PERIODO_MENSUAL;
    }

    public function getPeriodoEtiquetaAttribute(): string
    {
        return $this->esMensual()
            ? 'Mensual ' . $this->anio
            : 'Anual ' . $this->anio;
    }

    /**
     * Meta anual equivalente: si es mensual, monto × 12; si es anual, el monto.
     */
    public function getMontoAnualEquivalenteAttribute(): float
    {
        $monto = (float) $this->monto_meta;

        return $this->esMensual() ? $monto * 12 : $monto;
    }
}
