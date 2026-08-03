<?php

namespace App\Models;

// UBICACIÓN: app/Models/Cotizacion.php

use App\Helpers\IsrResicoHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'isr_retenido',
        'total',
        
        // Condiciones de pago
        'tipo_venta',
        'dias_credito_aplicados',
        'forma_pago',
        'condiciones_pago',
        'observaciones',
        'referencia_comercial',
        'referencia_url',
        'referencia_url_2',
        'referencia_url_3',
        
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
        'isr_retenido' => 'decimal:2',
        'total' => 'decimal:2',
        'tipo_cambio' => 'decimal:6',
        'dias_credito_aplicados' => 'integer',
    ];

    /**
     * ISR retenido aplicable (PF RESICO → persona moral), coherente con facturación.
     */
    public function calcularIsrRetenido(?Empresa $empresa = null, ?Cliente $cliente = null): float
    {
        $empresa = $empresa ?? $this->empresa ?? Empresa::principal();
        $cliente = $cliente ?? $this->cliente;

        if (! $empresa || ! $cliente) {
            return 0.0;
        }

        if (! IsrResicoHelper::aplicaRetencionIsrPm($empresa, $cliente)) {
            return 0.0;
        }

        return IsrResicoHelper::calcularRetencionIsrPm(
            (float) $this->subtotal,
            (float) ($this->descuento ?? 0)
        );
    }

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
     * Documentos de respaldo internos (cotizaciones de proveedor, etc.).
     * No se envían al cliente ni se incluyen en el PDF de la cotización.
     */
    public function adjuntos(): HasMany
    {
        return $this->hasMany(CotizacionAdjunto::class)->orderByDesc('created_at');
    }

    /**
     * Factura generada al convertir esta cotización (si aplica).
     */
    public function factura(): HasOne
    {
        return $this->hasOne(Factura::class);
    }

    /**
     * Siguiente folio de cotización disponible (sin reservar).
     * Considera el contador de empresa y los folios ya usados (incl. soft-deleted).
     */
    public static function siguienteFolioDisponible(?Empresa $empresa = null): string
    {
        $empresa ??= Empresa::principal();
        $serie = $empresa?->serie_cotizacion ?: 'COT';
        $contador = (int) ($empresa?->folio_cotizacion ?? 1);
        $numero = max($contador, self::maxNumeroFolioParaSerie($serie, false) + 1);

        return $serie.'-'.str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generar folio desde configuración de empresa (serie + folio).
     * Reserva el folio incrementando el contador de la empresa (con lock).
     * Si el contador quedó desfasado respecto a cotizaciones existentes, se re-sincroniza.
     */
    public static function generarFolio(): string
    {
        return \Illuminate\Support\Facades\DB::transaction(function () {
            $empresa = Empresa::principal();
            if ($empresa) {
                $e = Empresa::query()->whereKey($empresa->id)->lockForUpdate()->first();
                if ($e) {
                    $serie = $e->serie_cotizacion ?: 'COT';
                    $numero = max((int) $e->folio_cotizacion, self::maxNumeroFolioParaSerie($serie, true) + 1);
                    $folio = $serie.'-'.str_pad((string) $numero, 4, '0', STR_PAD_LEFT);

                    $e->folio_cotizacion = $numero + 1;
                    $e->save();

                    return $folio;
                }
            }
            // Fallback si no hay empresa: secuencia por último registro
            $serie = 'COT';
            $numero = self::maxNumeroFolioParaSerie($serie, true) + 1;

            return $serie.'-'.str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Máximo número de folio ya usado para una serie (incluye soft-deleted por unique).
     */
    private static function maxNumeroFolioParaSerie(string $serie, bool $lock = false): int
    {
        $max = 0;
        $pattern = '/^'.preg_quote($serie, '/').'-(\d+)$/';

        $query = self::withTrashed()->where('folio', 'like', $serie.'-%');
        if ($lock) {
            $query->lockForUpdate();
        }

        foreach ($query->pluck('folio') as $folio) {
            if (preg_match($pattern, (string) $folio, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return $max;
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
        return in_array($this->estado, ['borrador', 'vencida'], true);
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
     * Si tiene al menos una partida manual (histórico; se mantiene para UI/indicadores).
     */
    public function tienePartidasManuales(): bool
    {
        return $this->detalles()->where('es_producto_manual', true)->exists();
    }

    /**
     * Partidas sin producto asignado (requiere asignación con lupita para facturar).
     */
    public function tienePartidasSinProductoAsignado(): bool
    {
        return $this->detalles()->whereNull('producto_id')->exists();
    }

    /**
     * Puede convertir a factura: estado correcto y producto asignado en cada partida.
     * Stock y datos fiscales del PAC se validan al timbrar la factura.
     */
    public function puedeConvertirAFactura(): bool
    {
        return $this->motivoNoConvertirAFactura() === null;
    }

    /**
     * Mensaje por el cual no se puede convertir a factura (producto pendiente u otro).
     */
    public function motivoNoConvertirAFactura(): ?string
    {
        if (\App\Models\Factura::withTrashed()->where('cotizacion_id', $this->id)->exists()) {
            return 'Esta cotización ya tiene una factura asociada.';
        }
        if (!$this->puedeFacturarse()) {
            return 'La cotización debe estar aceptada o enviada.';
        }
        if ($this->tienePartidasSinProductoAsignado()) {
            return 'Primero debe asignar un producto en cada partida usando la lupita (📦 Asignar producto(s)).';
        }
        foreach ($this->detalles as $d) {
            if (!$d->producto_id || !$d->producto) {
                return 'Primero debe asignar un producto en cada partida usando la lupita (📦 Asignar producto(s)).';
            }
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
     * Scope: vendedor solo ve las suyas, admin/otros ven todas.
     */
    public function scopeParaUsuarioActual($query)
    {
        $user = auth()->user();
        if ($user && $user->isVendedor()) {
            $query->where('usuario_id', $user->id);
        }
        return $query;
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