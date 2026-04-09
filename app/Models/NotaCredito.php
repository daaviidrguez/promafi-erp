<?php

namespace App\Models;

use App\Models\Concerns\HasDesgloseTotalesCfdi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaCredito extends Model
{
    use HasDesgloseTotalesCfdi;
    use SoftDeletes;

    protected $table = 'notas_credito';

    protected $fillable = [
        'serie', 'folio', 'tipo_comprobante', 'estado',
        'factura_id', 'cliente_id', 'empresa_id', 'devolucion_id',
        'rfc_emisor', 'nombre_emisor', 'regimen_fiscal_emisor',
        'rfc_receptor', 'nombre_receptor', 'uso_cfdi', 'regimen_fiscal_receptor', 'domicilio_fiscal_receptor',
        'lugar_expedicion', 'fecha_emision', 'forma_pago', 'metodo_pago', 'moneda', 'tipo_cambio',
        'subtotal', 'descuento', 'total', 'motivo_cfdi',
        'uuid', 'pac_cfdi_id', 'uuid_referencia', 'tipo_relacion', 'fecha_timbrado', 'no_certificado_sat', 'sello_cfdi', 'sello_sat', 'cadena_original',
        'xml_content', 'xml_path', 'pdf_path',
        'motivo_cancelacion', 'fecha_cancelacion', 'acuse_cancelacion',
        'observaciones', 'usuario_id',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'fecha_timbrado' => 'datetime',
        'fecha_cancelacion' => 'datetime',
        'tipo_cambio' => 'decimal:6',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function devolucion(): BelongsTo
    {
        return $this->belongsTo(Devolucion::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(NotaCreditoDetalle::class);
    }

    public function getFolioCompletoAttribute(): string
    {
        return $this->serie . '-' . str_pad((string) $this->folio, 4, '0', STR_PAD_LEFT);
    }

    public function estaTimbrada(): bool
    {
        return $this->estado === 'timbrada' && !empty($this->uuid);
    }

    public function puedeTimbrar(): bool
    {
        return $this->estado === 'borrador' && $this->total > 0;
    }

    public function puedeCancelar(): bool
    {
        return $this->estado === 'timbrada';
    }

    public function calcularIVA(): float
    {
        $iva = $this->detalles->sum(function ($d) {
            return $d->impuestos->sum(fn ($i) => ($i->tipo ?? 'traslado') === 'retencion' ? 0 : ((float) ($i->importe ?? 0)));
        });
        if ($iva > 0 || $this->detalles->count() === 0) {
            return (float) $iva;
        }
        return round(((float) $this->subtotal - (float) $this->descuento) * 0.16, 2);
    }
}
