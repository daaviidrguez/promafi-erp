<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class EntradaAnticipada extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'entradas_anticipadas';

    protected $fillable = [
        'folio',
        'estado',
        'orden_compra_id',
        'cotizacion_id',
        'proveedor_id',
        'empresa_id',
        'fecha_recepcion',
        'moneda',
        'tipo_cambio',
        'subtotal',
        'descuento',
        'iva',
        'total',
        'observaciones',
        'factura_compra_id',
        'fecha_facturacion',
        'usuario_id',
    ];

    protected $casts = [
        'fecha_recepcion' => 'date',
        'fecha_facturacion' => 'datetime',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
        'tipo_cambio' => 'decimal:6',
    ];

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class);
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
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
        return $this->hasMany(EntradaAnticipadaDetalle::class)->orderBy('orden');
    }

    public function facturaCompra(): BelongsTo
    {
        return $this->belongsTo(FacturaCompra::class);
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class);
    }

    public static function generarFolio(): string
    {
        $max = 0;
        foreach (self::where('folio', 'like', 'EA-%')->pluck('folio') as $folio) {
            if (preg_match('/^EA-(\d{4})$/', $folio, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return 'EA-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    public function puedeEditarse(): bool
    {
        return $this->estado === 'borrador';
    }

    public function puedeConfirmarse(): bool
    {
        return $this->estado === 'borrador';
    }

    public function puedeFacturarse(): bool
    {
        return in_array($this->estado, ['confirmada', 'parcialmente_facturada'], true)
            && ! $this->factura_compra_id;
    }

    public function puedeCancelarse(): bool
    {
        return in_array($this->estado, ['borrador', 'confirmada'], true)
            && ! $this->factura_compra_id;
    }

    public function estaFacturada(): bool
    {
        return $this->estado === 'facturada';
    }

    public function etiquetaEstado(): string
    {
        return match ($this->estado) {
            'borrador' => 'Borrador',
            'confirmada' => 'Confirmada',
            'parcialmente_facturada' => 'Parcialmente facturada',
            'facturada' => 'Facturada',
            'cancelada' => 'Cancelada',
            default => $this->estado,
        };
    }
}
