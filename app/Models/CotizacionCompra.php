<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CotizacionCompra extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cotizaciones_compra';

    protected $fillable = [
        'folio',
        'estado',
        'proveedor_id',
        'empresa_id',
        'proveedor_nombre',
        'proveedor_rfc',
        'proveedor_email',
        'proveedor_telefono',
        'fecha',
        'fecha_vencimiento',
        'moneda',
        'tipo_cambio',
        'subtotal',
        'descuento',
        'iva',
        'total',
        'observaciones',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_vencimiento' => 'date',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
        'tipo_cambio' => 'decimal:6',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CotizacionCompraDetalle::class)->orderBy('orden');
    }

    public function ordenCompra(): HasMany
    {
        return $this->hasMany(OrdenCompra::class, 'cotizacion_compra_id');
    }

    public static function generarFolio(): string
    {
        $max = 0;
        foreach (self::where('folio', 'like', 'CC-%')->pluck('folio') as $folio) {
            $n = self::extraerSecuenciaFolioCc($folio);
            if ($n > $max) {
                $max = $n;
            }
        }

        return 'CC-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * CC-0001 (actual) o CC-2026-0001 (histórico).
     */
    private static function extraerSecuenciaFolioCc(string $folio): int
    {
        if (preg_match('/^CC-(\d{4})$/', $folio, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^CC-\d{4}-(\d{4})$/', $folio, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    public function puedeEditarse(): bool
    {
        return $this->estado === 'borrador';
    }

    public function puedeAprobarse(): bool
    {
        return $this->estado === 'borrador';
    }

    public function puedeGenerarOrden(): bool
    {
        return $this->estado === 'aprobada';
    }
}
