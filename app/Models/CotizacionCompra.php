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
        $year = date('Y');
        $ultimo = self::where('folio', 'like', "CC-{$year}-%")->orderBy('id', 'desc')->first();
        $numero = $ultimo && preg_match('/CC-' . $year . '-(\d{4})/', $ultimo->folio, $m) ? intval($m[1]) + 1 : 1;
        return 'CC-' . $year . '-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
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
