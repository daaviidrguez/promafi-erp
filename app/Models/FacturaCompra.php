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
        'folio_interno',
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
        $interno = $this->resolverFolioInterno();
        if (! empty($this->uuid)) {
            $fiscal = $this->etiquetaFolioFiscalProveedor();
            if ($fiscal !== '') {
                return $interno . ' - ' . $fiscal;
            }
        }

        return $interno;
    }

    /**
     * Texto de folios para listados (índice de compras, reportes): EM-0001,
     * EM-0001 · Serie/Folio CFDI, EM-0001 · OC-0001, o las tres partes si aplica.
     */
    public function folioListadoReferencias(): string
    {
        $em = $this->resolverFolioInterno();
        $partes = [$em];
        if (! empty($this->uuid)) {
            $fiscal = $this->etiquetaFolioFiscalProveedor();
            if ($fiscal !== '') {
                $partes[] = $fiscal;
            }
        }
        $folioOc = $this->ordenCompra?->folio;
        if ($folioOc) {
            $partes[] = $folioOc;
        }

        return implode(' · ', $partes);
    }

    /**
     * Serie/folio del CFDI (solo timbrado / con UUID), formato Serie/Folio.
     */
    public function etiquetaFolioFiscalProveedor(): string
    {
        if (empty($this->uuid)) {
            return '';
        }
        $s = trim((string) ($this->serie ?? ''));
        $f = trim((string) ($this->folio ?? ''));

        if ($s !== '' && $f !== '') {
            return $s . '/' . $f;
        }

        return $s !== '' ? $s : $f;
    }

    private function resolverFolioInterno(): string
    {
        if (! empty($this->folio_interno)) {
            return $this->folio_interno;
        }
        if (! empty($this->folio) && preg_match('/^EM-/', (string) $this->folio)) {
            return (string) $this->folio;
        }
        $fallback = $this->etiquetaFolioFiscalProveedor();

        return $fallback !== '' ? $fallback : '—';
    }

    /**
     * Folio consecutivo interno único (manual, Leer CFDI, registrar cuenta, importador).
     */
    public static function generarFolioInterno(): string
    {
        $max = 0;
        foreach (self::query()->whereNotNull('folio_interno')->where('folio_interno', 'like', 'EM-%')->pluck('folio_interno') as $f) {
            $max = max($max, self::extraerSecuenciaFolioEm($f));
        }
        foreach (self::query()->whereNull('folio_interno')->where('folio', 'like', 'EM-%')->pluck('folio') as $f) {
            $max = max($max, self::extraerSecuenciaFolioEm($f));
        }

        return 'EM-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @deprecated Usar generarFolioInterno(); se mantiene por compatibilidad con llamadas existentes.
     */
    public static function generarFolioManual(): string
    {
        return self::generarFolioInterno();
    }

    /**
     * EM-0001 (actual) o EM-2026-0001 (histórico).
     */
    private static function extraerSecuenciaFolioEm(string $folio): int
    {
        if (preg_match('/^EM-(\d{4})$/', $folio, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^EM-\d{4}-(\d{4})$/', $folio, $m)) {
            return (int) $m[1];
        }

        return 0;
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
                ->orWhere('folio_interno', 'like', "%{$search}%")
                ->orWhere('serie', 'like', "%{$search}%")
                ->orWhere('uuid', 'like', "%{$search}%")
                ->orWhere('nombre_emisor', 'like', "%{$search}%");
        });
    }
}
