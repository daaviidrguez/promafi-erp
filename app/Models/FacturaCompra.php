<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FacturaCompra extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'facturas_compra';

    protected $fillable = [
        'serie',
        'folio',
        'tipo_comprobante',
        'estado',
        'proveedor_id',
        'empresa_id',
        'orden_compra_id',
        'rfc_emisor',
        'nombre_emisor',
        'regimen_fiscal_emisor',
        'rfc_receptor',
        'nombre_receptor',
        'regimen_fiscal_receptor',
        'lugar_expedicion',
        'fecha_emision',
        'forma_pago',
        'metodo_pago',
        'moneda',
        'tipo_cambio',
        'subtotal',
        'descuento',
        'total',
        'uuid',
        'fecha_timbrado',
        'no_certificado_sat',
        'xml_content',
        'xml_path',
        'pdf_path',
        'observaciones',
        'usuario_id',
        'fecha_recepcion',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'fecha_timbrado' => 'datetime',
        'fecha_recepcion' => 'datetime',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
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

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(FacturaCompraDetalle::class)->orderBy('orden');
    }

    public function cuentaPorPagar(): HasOne
    {
        return $this->hasOne(CuentaPorPagar::class);
    }

    public function getFolioCompletoAttribute(): string
    {
        return trim(($this->serie ?? '') . ' ' . $this->folio);
    }

    /**
     * Genera el siguiente folio para compras manuales: EM-YYYY-0001, EM-YYYY-0002, etc.
     */
    public static function generarFolioManual(): string
    {
        $year = date('Y');
        $ultimo = self::whereNull('uuid')
            ->where('folio', 'like', "EM-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();
        $numero = $ultimo && preg_match('/EM-' . $year . '-(\d{4})/', $ultimo->folio, $m) ? (int) $m[1] + 1 : 1;
        return 'EM-' . $year . '-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }

    public function tieneCuentaPorPagar(): bool
    {
        return $this->cuentaPorPagar !== null;
    }

    public function puedeRecibirse(): bool
    {
        return $this->estado === 'registrada';
    }

    public function estaRecibida(): bool
    {
        return $this->estado === 'recibida';
    }

    /**
     * Scope para búsqueda global (folio, proveedor, UUID).
     */
    public function scopeBuscar($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('folio', 'like', "%{$search}%")
                ->orWhere('serie', 'like', "%{$search}%")
                ->orWhere('uuid', 'like', "%{$search}%")
                ->orWhere('nombre_emisor', 'like', "%{$search}%");
        });
    }
}
