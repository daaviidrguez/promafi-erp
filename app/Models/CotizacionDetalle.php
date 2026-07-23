<?php

namespace App\Models;

// UBICACIÓN: app/Models/CotizacionDetalle.php

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CotizacionDetalle extends Model
{
    use HasFactory;

    protected $table = 'cotizaciones_detalle';

    protected $fillable = [
        'cotizacion_id',
        'producto_id',
        'sugerencia_id',
        'codigo',
        'descripcion',
        'origen',
        'es_producto_manual',
        'cantidad',
        'unidad',
        'precio_unitario',
        'descuento_porcentaje',
        'tasa_iva',
        'subtotal',
        'descuento_monto',
        'base_imponible',
        'iva_monto',
        'total',
        'orden',
        'imagenes',
    ];

    protected $casts = [
        'imagenes' => 'array',
        'es_producto_manual' => 'boolean',
        'cantidad' => 'decimal:2',
        'precio_unitario' => 'decimal:2',
        'descuento_porcentaje' => 'decimal:2',
        'tasa_iva' => 'decimal:4',
        'subtotal' => 'decimal:2',
        'descuento_monto' => 'decimal:2',
        'base_imponible' => 'decimal:2',
        'iva_monto' => 'decimal:2',
        'total' => 'decimal:2',
        'orden' => 'integer',
    ];

    /**
     * Relación con Cotización
     */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    /**
     * Relación con Producto
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Relación con Sugerencia (partida manual sugerida)
     */
    public function sugerencia(): BelongsTo
    {
        return $this->belongsTo(Sugerencia::class);
    }

    /**
     * Rutas relativas guardadas (máx. 3) en disco public.
     *
     * @return array<int, string>
     */
    public function rutasImagenes(): array
    {
        $paths = $this->imagenes;
        if (! is_array($paths) || $paths === []) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($p) => is_string($p) ? trim($p) : '', $paths)));
    }

    /**
     * URLs para mostrar imágenes en vistas (ruta autenticada del ERP).
     *
     * @return array<int, string>
     */
    public function getImagenesUrlsAttribute(): array
    {
        if (! $this->id || ! $this->cotizacion_id) {
            return [];
        }

        $urls = [];
        foreach ($this->rutasImagenes() as $indice => $path) {
            if ($path === '') {
                continue;
            }
            $urls[] = route('cotizaciones.detalles.imagen', [
                'cotizacion' => $this->cotizacion_id,
                'detalle' => $this->id,
                'indice' => $indice,
            ]);
        }

        return $urls;
    }

    public function tieneImagenes(): bool
    {
        return $this->rutasImagenes() !== [];
    }

    public function eliminarImagenesDelDisco(): void
    {
        foreach ($this->rutasImagenes() as $path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Calcular importes automáticamente
     */
    public static function calcularImportes(array $datos): array
    {
        $cantidad = floatval($datos['cantidad']);
        $precioUnitario = floatval($datos['precio_unitario']);
        $descuentoPorcentaje = floatval($datos['descuento_porcentaje'] ?? 0);
        $tasaIva = isset($datos['tasa_iva']) ? floatval($datos['tasa_iva']) : null;

        // Subtotal
        $subtotal = $cantidad * $precioUnitario;

        // Descuento
        $descuentoMonto = $subtotal * ($descuentoPorcentaje / 100);

        // Base imponible
        $baseImponible = $subtotal - $descuentoMonto;

        // IVA
        $ivaMonto = 0;
        if ($tasaIva !== null) {
            $ivaMonto = $baseImponible * $tasaIva;
        }

        // Total
        $total = $baseImponible + $ivaMonto;

        return [
            'subtotal' => round($subtotal, 2),
            'descuento_monto' => round($descuentoMonto, 2),
            'base_imponible' => round($baseImponible, 2),
            'iva_monto' => round($ivaMonto, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Boot del modelo para calcular importes automáticamente
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($detalle) {
            if (!$detalle->subtotal) {
                $importes = self::calcularImportes($detalle->toArray());
                $detalle->fill($importes);
            }
        });

        static::updating(function ($detalle) {
            $importes = self::calcularImportes($detalle->toArray());
            $detalle->fill($importes);
        });
    }
}