<?php

namespace App\Models;

// UBICACIÓN: app/Models/Cotizacion.php

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cotizacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cotizaciones';

    protected $fillable = [
        // Identificación
        'folio',
        'estado',
        
        // Relaciones
        'cliente_id',
        'empresa_id',
        
        // Datos del cliente (snapshot)
        'cliente_nombre',
        'cliente_rfc',
        'cliente_email',
        'cliente_telefono',
        'cliente_calle',
        'cliente_numero_exterior',
        'cliente_numero_interior',
        'cliente_colonia',
        'cliente_municipio',
        'cliente_estado',
        'cliente_codigo_postal',
        
        // Fechas
        'fecha',
        'fecha_vencimiento',
        
        // Moneda
        'moneda',
        'tipo_cambio',
        
        // Importes
        'subtotal',
        'descuento',
        'iva',
        'total',
        
        // Condiciones de pago
        'tipo_venta',
        'dias_credito_aplicados',
        'condiciones_pago',
        'observaciones',
        
        // Archivos
        'pdf_path',
        'fecha_envio',
        
        // Control
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_envio' => 'datetime',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
        'tipo_cambio' => 'decimal:6',
        'dias_credito_aplicados' => 'integer',
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
     * Relación con Detalle
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(CotizacionDetalle::class)->orderBy('orden');
    }

    /**
     * Generar folio desde configuración de empresa (serie + folio).
     * Reserva el folio incrementando el contador de la empresa.
     */
    public static function generarFolio(): string
    {
        $empresa = \App\Models\Empresa::principal();
        if ($empresa) {
            $folio = $empresa->obtenerSiguienteFolioCotizacion();
            $empresa->incrementarFolioCotizacion();
            return $folio;
        }
        // Fallback si no hay empresa: secuencia por último registro
        $ultimo = self::orderBy('id', 'desc')->first();
        if (!$ultimo) {
            return 'COT-0001';
        }
        $folio = $ultimo->folio;
        if (preg_match('/^.+-(\d{4})$/', $folio, $m)) {
            $numero = (int) $m[1] + 1;
        } else {
            $numero = 1;
        }
        return 'COT-' . str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Verificar si está vencida
     */
    public function estaVencida(): bool
    {
        if (!$this->fecha_vencimiento) {
            return false;
        }

        return $this->fecha_vencimiento < now()
            && !in_array($this->estado, ['facturada', 'rechazada']);
    }

    /**
     * Verificar si puede editarse
     */
    public function puedeEditarse(): bool
    {
        return in_array($this->estado, ['borrador', 'aceptada']);
    }

    /**
     * Verificar si puede aceptarse
     */
    public function puedeAceptarse(): bool
    {
        return $this->estado === 'borrador';
    }

    /**
     * Verificar si puede enviarse
     */
    public function puedeEnviarse(): bool
    {
        return $this->estado === 'aceptada';
    }

    /**
     * Verificar si puede facturarse (estado)
     */
    public function puedeFacturarse(): bool
    {
        return in_array($this->estado, ['aceptada', 'enviada']);
    }

    /**
     * Si tiene al menos una partida manual
     */
    public function tienePartidasManuales(): bool
    {
        return $this->detalles()->where('es_producto_manual', true)->exists();
    }

    /**
     * Puede convertir a factura: estado correcto, todos los datos necesarios y stock suficiente
     * en productos que controlan inventario.
     */
    public function puedeConvertirAFactura(): bool
    {
        if (!$this->puedeFacturarse()) {
            return false;
        }
        foreach ($this->detalles as $d) {
            if ($d->producto_id && $d->producto) {
                if ($d->producto->controla_inventario && !$d->producto->tieneStock((float) $d->cantidad)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Mensaje por el cual no se puede convertir a factura (stock u otro)
     */
    public function motivoNoConvertirAFactura(): ?string
    {
        if (!$this->puedeFacturarse()) {
            return 'La cotización debe estar aceptada o enviada.';
        }
        $sinStock = [];
        foreach ($this->detalles as $d) {
            if ($d->producto_id && $d->producto && $d->producto->controla_inventario) {
                if (!$d->producto->tieneStock((float) $d->cantidad)) {
                    $sinStock[] = $d->producto->nombre . ' (requiere ' . $d->cantidad . ', hay ' . $d->producto->stock . ')';
                }
            }
        }
        if (!empty($sinStock)) {
            return 'Falta stock: ' . implode('; ', $sinStock);
        }
        return null;
    }

    /**
     * Verificar si puede eliminarse
     */
    public function puedeEliminarse(): bool
    {
        return in_array($this->estado, ['borrador', 'rechazada', 'vencida']);
    }

    /**
     * Aceptar cotización
     */
    public function aceptar(): bool
    {
        if (!$this->puedeAceptarse()) {
            return false;
        }

        $this->estado = 'aceptada';
        return $this->save();
    }

    /**
     * Marcar como enviada
     */
    public function marcarComoEnviada(): bool
    {
        if (!$this->puedeEnviarse()) {
            return false;
        }

        $this->estado = 'enviada';
        $this->fecha_envio = now();
        return $this->save();
    }

    /**
     * Marcar como facturada
     */
    public function marcarComoFacturada(): bool
    {
        if (!$this->puedeFacturarse()) {
            return false;
        }

        $this->estado = 'facturada';
        return $this->save();
    }

    /**
     * Rechazar cotización
     */
    public function rechazar(): bool
    {
        $this->estado = 'rechazada';
        return $this->save();
    }

    /**
     * Calcular días hasta vencimiento
     */
    public function diasHastaVencimiento(): ?int
    {
        if (!$this->fecha_vencimiento) {
            return null;
        }

        return now()->diffInDays($this->fecha_vencimiento, false);
    }

    /**
     * Scope: Solo vigentes
     */
    public function scopeVigentes($query)
    {
        return $query->whereNotIn('estado', ['facturada', 'rechazada', 'vencida']);
    }

    /**
     * Scope: Por estado
     */
    public function scopeEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    /**
     * Scope: Por vencer (próximos 7 días)
     */
    public function scopePorVencer($query)
    {
        return $query->where('fecha_vencimiento', '>=', now())
            ->where('fecha_vencimiento', '<=', now()->addDays(7))
            ->whereNotIn('estado', ['facturada', 'rechazada', 'vencida']);
    }

    /**
     * Scope: Vencidas
     */
    public function scopeVencidas($query)
    {
        return $query->where('fecha_vencimiento', '<', now())
            ->whereNotIn('estado', ['facturada', 'rechazada', 'vencida']);
    }

    /**
     * Scope para búsqueda
     */

    public function scopeBuscar($query, $term)
    {
        return $query->where(function ($q) use ($term) {

            $q->where('folio', 'like', "%{$term}%")
            ->orWhere('cliente_nombre', 'like', "%{$term}%")
            ->orWhere('cliente_rfc', 'like', "%{$term}%")
            ->orWhereHas('detalles', function ($q2) use ($term) {
                $q2->where('descripcion', 'like', "%{$term}%")
                    ->orWhere('codigo', 'like', "%{$term}%");
            });
        });
    }

}