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
        'cotizacion_id',
        'observaciones',
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
     * Verificar si puede ser cancelada
     */
    public function puedeCancelar(): bool
    {
        return $this->estado === 'timbrada';
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
     * Calcular IVA
     */
    public function calcularIVA(): float
    {
        $baseIva = $this->subtotal - $this->descuento;
        return $baseIva * 0.16;
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