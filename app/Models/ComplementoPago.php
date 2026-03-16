<?php

namespace App\Models;

// UBICACIÓN: app/Models/ComplementoPago.php
// REEMPLAZA el contenido actual con este

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplementoPago extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'complementos_pago';

    protected $fillable = [
        // Identificación
        'serie',
        'folio',
        'estado',
        
        // Relaciones
        'cliente_id',
        'empresa_id',
        
        // Datos del emisor (CRÍTICOS - AGREGADOS)
        'rfc_emisor',
        'nombre_emisor',
        
        // Datos del receptor
        'rfc_receptor',
        'nombre_receptor',
        
        // Datos fiscales (CRÍTICOS - AGREGADOS)
        'fecha_emision',
        'lugar_expedicion',
        'monto_total',
        
        // Timbrado
        'uuid',
        'pac_cfdi_id',
        'fecha_timbrado',
        'no_certificado_sat',
        'sello_cfdi',
        'sello_sat',
        'cadena_original',
        'xml_content',
        'xml_path',
        'pdf_path',
        'fecha_cancelacion',
        'acuse_cancelacion',
        'codigo_estatus_cancelacion',
        'motivo_cancelacion',
        'uuid_referencia',
        'tipo_relacion',
        
        // Control
        'usuario_id',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'fecha_timbrado' => 'datetime',
        'fecha_cancelacion' => 'datetime',
        'monto_total' => 'decimal:2',
    ];

    /**
     * Relación con Cliente
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Relación con Empresa
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Relación con Usuario
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con Pagos Recibidos
     */
    public function pagosRecibidos(): HasMany
    {
        return $this->hasMany(PagoRecibido::class);
    }

    /**
     * Obtener folio completo (serie + folio)
     */
    public function getFolioCompletoAttribute(): string
    {
        return $this->serie . '-' . str_pad($this->folio, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verificar si está timbrado
     */
    public function estaTimbrado(): bool
    {
        return $this->estado === 'timbrado';
    }

    /**
     * Verificar si es borrador
     */
    public function esBorrador(): bool
    {
        return $this->estado === 'borrador';
    }

    /**
     * Verificar si puede timbrar
     */
    public function puedeTimbrar(): bool
    {
        return $this->estado === 'borrador' && !empty($this->uuid) === false;
    }

    /**
     * Verificar si está cancelado
     */
    public function estaCancelado(): bool
    {
        return $this->estado === 'cancelado';
    }

    /**
     * Verificar si puede cancelarse (timbrado y no cancelado)
     */
    public function puedeCancelar(): bool
    {
        return $this->estado === 'timbrado';
    }

    /**
     * Etiqueta del estado para listados (incluye código SAT de cancelación cuando aplica).
     */
    public function getEstadoEtiquetaAttribute(): string
    {
        if ($this->estado === 'borrador') {
            return 'Borrador';
        }
        if ($this->estado === 'timbrado') {
            return 'Timbrado';
        }
        if ($this->estado === 'cancelado') {
            $cod = $this->codigo_estatus_cancelacion;
            if ($cod) {
                return 'Cancelado (' . $cod . ')';
            }
            return 'Cancelado';
        }
        return $this->estado ?? '—';
    }

    /**
     * Estatus de la solicitud de cancelación (paso a paso) — descripción SAT del código.
     */
    public function getEstatusSolicitudLabelAttribute(): ?string
    {
        if ($this->estado !== 'cancelado') {
            return null;
        }
        $cod = $this->codigo_estatus_cancelacion;
        if ($cod === null || $cod === '') {
            return 'Sin respuesta SAT aún';
        }
        return self::descripcionCodigoCancelacion($cod);
    }

    /**
     * Descripción del código de estatus de cancelación SAT (catálogo c_EstatusCancelacion).
     */
    public static function descripcionCodigoCancelacion(?string $codigo): string
    {
        $map = [
            '201' => 'Solicitud procesada',
            '202' => 'UUID previamente enviado',
            '203' => 'UUID no corresponde al emisor',
            '204' => 'UUID no aplicable',
            '205' => 'UUID no existe',
            '206' => 'En proceso (pendiente aceptación)',
            '301' => 'Sello inválido',
            '302' => 'Certificado revocado/caduco',
            '401' => 'Fecha fuera de rango',
            '601' => 'No cancelable',
        ];
        $cod = (string) $codigo;
        if (str_starts_with($cod, 'R-')) {
            $num = substr($cod, 2);
            return ($map[$num] ?? $num) . ' (Rechazada)';
        }
        if (str_starts_with($cod, 'R') || str_starts_with($cod, 'Rechazada')) {
            return 'Rechazada';
        }
        return $map[$cod] ?? $codigo ?? '—';
    }
}