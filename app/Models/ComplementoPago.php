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
        'fecha_timbrado',
        'xml_content',
        'xml_path',
        
        // Control
        'usuario_id',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'fecha_timbrado' => 'datetime',
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
}