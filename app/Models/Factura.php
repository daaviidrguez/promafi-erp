<?php

namespace App\Models;

// UBICACIÓN: app/Models/Factura.php

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Factura extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'serie',
        'folio',
        'tipo_comprobante',
        'estado',
        'cliente_id',
        'empresa_id',
        'rfc_emisor',
        'nombre_emisor',
        'regimen_fiscal_emisor',
        'rfc_receptor',
        'nombre_receptor',
        'uso_cfdi',
        'regimen_fiscal_receptor',
        'domicilio_fiscal_receptor',
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
        'pac_cfdi_id',
        'fecha_timbrado',
        'no_certificado_sat',
        'sello_cfdi',
        'sello_sat',
        'cadena_original',
        'xml_content',
        'xml_path',
        'pdf_path',
        'motivo_cancelacion',
        'fecha_cancelacion',
        'acuse_cancelacion',
        'codigo_estatus_cancelacion',
        'cotizacion_id',
        'observaciones',
        'uuid_referencia',
        'tipo_relacion',
        'usuario_id',
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

    /**
     * Saldo acreditable máximo para una nota de crédito directa (sin devolución).
     * PPD: saldo_pendiente_real de la cuenta por cobrar.
     * PUE: total factura menos total de NCs timbradas.
     */
    public function getSaldoAcreditableAttribute(): float
    {
        if ($this->cuentaPorCobrar) {
            return max(0, (float) $this->cuentaPorCobrar->saldo_pendiente_real);
        }
        $ncTotal = (float) NotaCredito::where('factura_id', $this->id)->where('estado', 'timbrada')->sum('total');
        return max(0, (float) $this->total - $ncTotal);
    }

    /**
     * Relación con Cliente
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Relación con Empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Relación con Usuario
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Relación con Cotización
     */
    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    /**
     * Relación con Detalle (productos)
     */
    public function detalles()
    {
        return $this->hasMany(FacturaDetalle::class);
    }

    /**
     * Relación con Cuenta por Cobrar
     */
    public function cuentaPorCobrar()
    {
        return $this->hasOne(CuentaPorCobrar::class);
    }

    /**
     * Documentos relacionados en complementos de pago (pagos aplicados a esta factura)
     */
    public function documentosRelacionadosPago()
    {
        return $this->hasMany(DocumentoRelacionadoPago::class);
    }

    /**
     * Devoluciones asociadas a esta factura
     */
    public function devoluciones()
    {
        return $this->hasMany(Devolucion::class);
    }

    /**
     * Notas de crédito que referencian esta factura
     */
    public function notasCredito()
    {
        return $this->hasMany(NotaCredito::class);
    }

    /**
     * Verificar si está timbrada
     */
    public function estaTimbrada(): bool
    {
        return $this->estado === 'timbrada' && !empty($this->uuid);
    }

    /**
     * Verificar si está cancelada
     */
    public function estaCancelada(): bool
    {
        return $this->estado === 'cancelada';
    }

    /**
     * Verificar si es borrador
     */
    public function esBorrador(): bool
    {
        return $this->estado === 'borrador';
    }

    /**
     * Verificar si puede ser timbrada
     */
    public function puedeTimbrar(): bool
    {
        return $this->estado === 'borrador' && $this->total > 0;
    }

    /**
     * Verificar si puede ser cancelada (sin documentos relacionados).
     * Se volverá a activar en flujo castada.
     */
    public function puedeCancelar(): bool
    {
        return $this->estado === 'timbrada' && !$this->tieneDocumentosRelacionados();
    }

    /**
     * Verificar si la factura tiene documentos relacionados que impiden cancelarla.
     * Incluye: complementos de pago, notas de crédito y devoluciones.
     */
    public function tieneDocumentosRelacionados(): bool
    {
        return $this->documentosRelacionadosPago()->exists()
            || $this->notasCredito()->exists()
            || $this->devoluciones()->exists();
    }

    /**
     * Obtener detalle de documentos relacionados para mensajes informativos.
     */
    public function getDocumentosRelacionadosDetalle(): array
    {
        $detalle = [];
        if ($this->documentosRelacionadosPago()->exists()) {
            $count = $this->documentosRelacionadosPago()->count();
            $detalle[] = $count === 1
                ? '1 complemento de pago aplicado'
                : "{$count} complementos de pago aplicados";
        }
        if ($this->notasCredito()->exists()) {
            $count = $this->notasCredito()->count();
            $detalle[] = $count === 1
                ? '1 nota de crédito emitida'
                : "{$count} notas de crédito emitidas";
        }
        if ($this->devoluciones()->exists()) {
            $count = $this->devoluciones()->count();
            $detalle[] = $count === 1
                ? '1 devolución registrada'
                : "{$count} devoluciones registradas";
        }
        return $detalle;
    }

    /**
     * Verificar si es a crédito (PPD)
     */
    public function esCredito(): bool
    {
        return $this->metodo_pago === 'PPD';
    }

    /**
     * Obtener folio completo
     */
    public function getFolioCompletoAttribute(): string
    {
        return $this->serie . '-' . str_pad($this->folio, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calcular IVA (desde impuestos traslado por línea, o estimado si no hay)
     */
    public function calcularIVA(): float
    {
        $iva = $this->detalles->sum(function ($d) {
            return $d->impuestos->sum(fn ($i) => $i->importe ?? 0);
        });
        if ($iva > 0 || $this->detalles->count() === 0) {
            return (float) $iva;
        }
        $baseIva = $this->subtotal - $this->descuento;
        return round($baseIva * 0.16, 2);
    }

    /**
     * Scope para facturas timbradas
     */
    public function scopeTimbradas($query)
    {
        return $query->where('estado', 'timbrada');
    }

    /**
     * Scope para facturas de un periodo
     */
    public function scopeDelMes($query, $mes = null, $anio = null)
    {
        $mes = $mes ?? now()->month;
        $anio = $anio ?? now()->year;
        
        return $query->whereMonth('fecha_emision', $mes)
                    ->whereYear('fecha_emision', $anio);
    }

    /**
     * Etiqueta del estado para mostrar en listados (incluye código SAT de cancelación).
     * Códigos SAT: https://apisandbox.facturama.mx/docs
     */
    public function getEstadoEtiquetaAttribute(): string
    {
        if ($this->estado === 'borrador') {
            return 'Borrador';
        }
        if ($this->estado === 'timbrada') {
            $cod = $this->codigo_estatus_cancelacion;
            if ($cod && (str_starts_with($cod, 'R') || str_starts_with($cod, 'Rechazada'))) {
                return 'Timbrada (' . self::descripcionCodigoCancelacion($cod) . ')';
            }
            return 'Timbrada';
        }
        if ($this->estado === 'cancelada') {
            $cod = $this->codigo_estatus_cancelacion;
            if ($cod) {
                return 'Cancelada (' . $cod . ')';
            }
            return 'Cancelada';
        }
        return $this->estado ?? '—';
    }

    /**
     * Descripción del código de estatus de cancelación SAT.
     * Documentación: https://apisandbox.facturama.mx/docs
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

    /**
     * Scope para búsqueda
     */
    public function scopeBuscar($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('folio', 'like', "%{$search}%")
              ->orWhere('serie', 'like', "%{$search}%")
              ->orWhere('uuid', 'like', "%{$search}%")
              ->orWhere('nombre_receptor', 'like', "%{$search}%")
              ->orWhere('rfc_receptor', 'like', "%{$search}%");
        });
    }
}