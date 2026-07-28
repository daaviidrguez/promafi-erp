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
        'origen',
        'proveedor_id',
        'empresa_id',
        'orden_compra_id',
        'entrada_anticipada_id',
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

    public function entradaAnticipada(): BelongsTo
    {
        return $this->belongsTo(EntradaAnticipada::class);
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
     * Debe ser seguro ante concurrencia (lock de filas + unique en BD).
     */
    public static function generarFolioInterno(): string
    {
        return \Illuminate\Support\Facades\DB::transaction(function () {
            $candidatos = self::withTrashed()
                ->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNotNull('folio_interno')->where('folio_interno', 'like', 'EM-%');
                    })->orWhere(function ($q2) {
                        $q2->whereNull('folio_interno')->where('folio', 'like', 'EM-%');
                    });
                })
                ->lockForUpdate()
                ->get(['folio_interno', 'folio']);

            $max = 0;
            foreach ($candidatos as $row) {
                if (! empty($row->folio_interno) && ! str_starts_with((string) $row->folio_interno, 'EM-TMP-')) {
                    $max = max($max, self::extraerSecuenciaFolioEm((string) $row->folio_interno));
                } elseif (! empty($row->folio)) {
                    $max = max($max, self::extraerSecuenciaFolioEm((string) $row->folio));
                }
            }

            return 'EM-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
        });
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

    public function tienePdfSubido(): bool
    {
        return $this->resolverRutaArchivoLocal($this->pdf_path) !== null;
    }

    public function tieneXmlCfdi(): bool
    {
        if (! empty(trim((string) $this->xml_content))) {
            return true;
        }

        return $this->resolverRutaArchivoLocal($this->xml_path) !== null;
    }

    public function resolverRutaArchivoLocal(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        $private = storage_path('app/private/'.$path);
        if (file_exists($private)) {
            return $private;
        }

        $legacy = storage_path('app/'.$path);
        if (file_exists($legacy)) {
            return $legacy;
        }

        return null;
    }

    public function puedeRecibirse(): bool
    {
        return $this->estado === 'registrada' && ! $this->entrada_anticipada_id;
    }

    public function inventarioDesdeEntradaAnticipada(): bool
    {
        return $this->entrada_anticipada_id !== null;
    }

    public function estaRecibida(): bool
    {
        return $this->estado === 'recibida';
    }

    public function estaCancelada(): bool
    {
        return $this->estado === 'cancelada';
    }

    /**
     * Indica si ya hay una compra no cancelada con el mismo UUID de CFDI.
     */
    public static function existeCompraActivaConUuid(?string $uuid): bool
    {
        if (empty(trim((string) $uuid))) {
            return false;
        }

        return static::where('uuid', $uuid)
            ->where('estado', '!=', 'cancelada')
            ->exists();
    }

    /**
     * Compras registradas o recibidas sin pagos en CxP pueden cancelarse.
     */
    public function puedeCancelarse(): bool
    {
        return $this->motivoNoCancelable() === null;
    }

    public function motivoNoCancelable(): ?string
    {
        if ($this->estaCancelada()) {
            return 'Esta compra ya está cancelada.';
        }
        if (! in_array($this->estado, ['registrada', 'recibida'], true)) {
            return 'Solo se pueden cancelar compras registradas o recibidas.';
        }
        $cxp = $this->relationLoaded('cuentaPorPagar') ? $this->cuentaPorPagar : $this->cuentaPorPagar()->first();
        if ($cxp) {
            if ((float) $cxp->monto_pagado > 0) {
                return 'No se puede cancelar: la cuenta por pagar tiene pagos registrados. Revierta los pagos primero.';
            }
            if ($cxp->estado === 'cancelada') {
                return 'La cuenta por pagar vinculada ya está cancelada.';
            }
        }

        return null;
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
