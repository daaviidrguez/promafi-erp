<?php

namespace App\Models;

// UBICACIÓN: app/Models/ComplementoPago.php
// REEMPLAZA el contenido actual con este

use App\Services\EstatusCancelacionCfdi;
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
        'estatus_cancelacion_pac',
        'mensaje_cancelacion_pac',
        'is_cancelable',
        'fecha_solicitud_cancelacion',
        'fecha_vencimiento_aceptacion',
        'estatus_sat',
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
        'fecha_solicitud_cancelacion' => 'datetime',
        'fecha_vencimiento_aceptacion' => 'datetime',
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

    public function cancelacionEventos()
    {
        return $this->morphMany(CfdiCancelacionEvento::class, 'cancelable');
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

    public function canceladaAnteSat(): bool
    {
        return EstatusCancelacionCfdi::esCanceladaSat(
            $this->estatus_cancelacion_pac,
            $this->estatus_sat,
            $this->codigo_estatus_cancelacion
        );
    }

    public function solicitudFiscalPendiente(): bool
    {
        return EstatusCancelacionCfdi::esPendientePac($this->estatus_cancelacion_pac);
    }

    public function puedeConsultarEstatusCancelacion(): bool
    {
        return $this->estado === 'cancelado' && ! empty($this->uuid);
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
            return EstatusCancelacionCfdi::etiquetaListado(
                'cancelado',
                false,
                $this->estatus_cancelacion_pac,
                $this->estatus_sat,
                $this->codigo_estatus_cancelacion,
                false
            );
        }

        return $this->estado ?? '—';
    }

    public function getEstatusSolicitudLabelAttribute(): ?string
    {
        if ($this->estado !== 'cancelado') {
            return null;
        }
        if ($this->solicitudFiscalPendiente()) {
            $hasta = $this->fecha_vencimiento_aceptacion
                ? $this->fecha_vencimiento_aceptacion->format('d/m/Y H:i')
                : null;

            return $hasta
                ? 'Pendiente de aceptación del receptor (hasta '.$hasta.')'
                : 'Pendiente de aceptación del receptor (hasta 72 h)';
        }
        if ($this->canceladaAnteSat()) {
            return 'Cancelado ante el SAT';
        }
        $cod = $this->codigo_estatus_cancelacion;
        if ($cod === null || $cod === '') {
            return $this->estatus_sat ? 'SAT: '.$this->estatus_sat : 'Sin código SAT todavía';
        }

        return self::descripcionCodigoCancelacion($cod);
    }

    public static function descripcionCodigoCancelacion(?string $codigo): string
    {
        return EstatusCancelacionCfdi::descripcionCodigo($codigo);
    }
}