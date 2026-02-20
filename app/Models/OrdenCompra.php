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

    public static function generarFolio(): string
    {
        $year = date('Y');
        $ultimo = self::where('folio', 'like', "OC-{$year}-%")->orderBy('id', 'desc')->first();
        $numero = $ultimo && preg_match('/OC-' . $year . '-(\d{4})/', $ultimo->folio, $m) ? intval($m[1]) + 1 : 1;
        return 'OC-' . $year . '-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }

    public function puedeEditarse(): bool
    {
        return $this->estado === 'borrador';
    }

    public function puedeAceptarse(): bool
    {
        return $this->estado === 'borrador';
    }

    public function puedeRecibirse(): bool
    {
        return $this->estado === 'aceptada';
    }

    public function estaRecibida(): bool
    {
        return $this->estado === 'recibida';
    }
}
