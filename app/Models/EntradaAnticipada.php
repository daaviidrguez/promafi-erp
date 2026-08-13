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

    public function facturasCompra(): HasMany
    {
        return $this->hasMany(FacturaCompra::class, 'entrada_anticipada_id')
            ->where('estado', '!=', 'cancelada')
            ->orderByDesc('id');
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
        if (! in_array($this->estado, ['confirmada', 'parcialmente_facturada'], true)) {
            return false;
        }

        return $this->tieneSaldoPorFacturar();
    }

    public function puedeCancelarse(): bool
    {
        return in_array($this->estado, ['borrador', 'confirmada'], true)
            && ! $this->factura_compra_id
            && ! $this->facturasCompra()->exists();
    }

    public function estaFacturada(): bool
    {
        return $this->estado === 'facturada';
    }

    public function tieneSaldoPorFacturar(): bool
    {
        $this->loadMissing('detalles');

        return $this->detallesConSaldoFacturable()->isNotEmpty();
    }

    /**
     * @return \Illuminate\Support\Collection<int, EntradaAnticipadaDetalle>
     */
    public function detallesConSaldoFacturable()
    {
        $this->loadMissing('detalles');

        return $this->detalles->filter(
            fn ($d) => $d->producto_id && $d->tieneSaldoPorFacturar()
        );
    }

    /**
     * @return array{subtotal:float,iva:float,descuento:float,total:float}
     */
    public function importesSaldoPorFacturar(): array
    {
        return $this->importesSaldoPorDetalles($this->detallesConSaldoFacturable());
    }

    /**
     * @param  array<int, int>  $productoIds
     * @return array{subtotal:float,iva:float,descuento:float,total:float}
     */
    public function importesSaldoPorProductos(array $productoIds): array
    {
        $ids = array_map('intval', $productoIds);

        return $this->importesSaldoPorDetalles(
            $this->detallesConSaldoFacturable()->filter(
                fn ($d) => in_array((int) $d->producto_id, $ids, true)
            )
        );
    }

    /**
     * @param  iterable<EntradaAnticipadaDetalle>  $detalles
     * @return array{subtotal:float,iva:float,descuento:float,total:float}
     */
    public function importesSaldoPorDetalles(iterable $detalles): array
    {
        $subtotal = $iva = $descuento = $total = 0.0;
        foreach ($detalles as $d) {
            $factor = $d->factorSaldoPorFacturar();
            if ($factor <= 0) {
                continue;
            }
            $subtotal += (float) $d->subtotal * $factor;
            $iva += (float) $d->iva_monto * $factor;
            $descuento += (float) $d->descuento_monto * $factor;
            $total += (float) $d->total * $factor;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'iva' => round($iva, 2),
            'descuento' => round($descuento, 2),
            'total' => round($total, 2),
        ];
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
