<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrdenCompra extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ordenes_compra';

    protected $fillable = [
        'folio',
        'estado',
        'cotizacion_compra_id',
        'proveedor_id',
        'empresa_id',
        'proveedor_nombre',
        'proveedor_rfc',
        'proveedor_regimen_fiscal',
        'proveedor_uso_cfdi',
        'fecha',
        'fecha_entrega_estimada',
        'fecha_recepcion',
        'moneda',
        'tipo_cambio',
        'subtotal',
        'descuento',
        'iva',
        'total',
        'dias_credito',
        'observaciones',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_entrega_estimada' => 'date',
        'fecha_recepcion' => 'date',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
        'tipo_cambio' => 'decimal:6',
        'dias_credito' => 'integer',
    ];

    public function cotizacionCompra(): BelongsTo
    {
        return $this->belongsTo(CotizacionCompra::class);
    }

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
        return $this->hasMany(OrdenCompraDetalle::class)->orderBy('orden');
    }

    public function cuentaPorPagar(): HasOne
    {
        return $this->hasOne(CuentaPorPagar::class);
    }

    public function facturaCompra(): HasOne
    {
        return $this->hasOne(FacturaCompra::class);
    }

    public static function generarFolio(): string
    {
        $max = 0;
        foreach (self::where('folio', 'like', 'OC-%')->pluck('folio') as $folio) {
            $n = self::extraerSecuenciaFolioOc($folio);
            if ($n > $max) {
                $max = $n;
            }
        }

        return 'OC-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * OC-0001 (actual) o OC-2026-0001 (histórico).
     */
    private static function extraerSecuenciaFolioOc(string $folio): int
    {
        if (preg_match('/^OC-(\d{4})$/', $folio, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^OC-\d{4}-(\d{4})$/', $folio, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    public function puedeEditarse(): bool
    {
        return $this->estado === 'borrador';
    }

    public function puedeAceptarse(): bool
    {
        return $this->estado === 'borrador';
    }

    public function puedeConvertirseACompra(): bool
    {
        return $this->estado === 'aceptada' && ! $this->facturaCompra()->exists();
    }

    /**
     * Se puede cancelar en aceptada si aún no se generó la compra.
     * Si hay cuenta por pagar asociada a la orden, también se cancela.
     */
    public function puedeCancelarse(): bool
    {
        return $this->estado === 'aceptada' && ! $this->facturaCompra()->exists();
    }
}
