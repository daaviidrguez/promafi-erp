<?php

namespace App\Models;

// UBICACIÓN: app/Models/FacturaImpuesto.php

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacturaImpuesto extends Model
{
    use HasFactory;

    protected $table = 'facturas_impuestos';

    protected $fillable = [
        'factura_detalle_id',
        'tipo',
        'impuesto',
        'tipo_factor',
        'tasa_o_cuota',
        'base',
        'importe',
    ];

    protected $casts = [
        'tasa_o_cuota' => 'decimal:6',
        'base' => 'decimal:2',
        'importe' => 'decimal:2',
    ];

    /**
     * Relación con FacturaDetalle
     */
    public function facturaDetalle()
    {
        return $this->belongsTo(FacturaDetalle::class);
    }

    /**
     * Verificar si es traslado (IVA)
     */
    public function esTraslado(): bool
    {
        return $this->tipo === 'traslado';
    }

    /**
     * Verificar si es retención
     */
    public function esRetencion(): bool
    {
        return $this->tipo === 'retencion';
    }

    /**
     * Verificar si es IVA
     */
    public function esIVA(): bool
    {
        return $this->impuesto === '002';
    }

    /**
     * Obtener nombre del impuesto (para mostrar en la app)
     */
    public function getNombreImpuestoAttribute(): string
    {
        $nombres = config('impuestos_sat.nombres_display', [
            '001' => 'ISR',
            '002' => 'IVA',
            '003' => 'IEPS',
        ]);

        return $nombres[$this->impuesto] ?? $this->impuesto;
    }

    /**
     * Nombre del impuesto para Facturama API (solo admite: IVA, ISR, IEPS, IVA RET, IVA Exento).
     *
     * @param string $impuesto Clave SAT: 001, 002, 003
     * @param string $tipo traslado|retencion
     * @param string|null $tipoFactor Tasa|Cuota|Exento (para IVA Exento)
     */
    public static function nombreParaFacturama(string $impuesto, string $tipo, ?string $tipoFactor = null): string
    {
        $cfg = config('impuestos_sat.nombres_facturama', []);
        $tipo = $tipo === 'retencion' ? 'retencion' : 'traslado';

        if ($tipoFactor === 'Exento' && $impuesto === '002') {
            return $cfg['exento']['002'] ?? 'IVA Exento';
        }

        $map = $cfg[$tipo] ?? [];
        return $map[$impuesto] ?? ($cfg['traslado'][$impuesto] ?? 'IVA');
    }
}