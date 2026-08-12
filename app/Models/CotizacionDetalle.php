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
        'utilidad',
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
        'utilidad' => 'decimal:2',
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
     * Precio de venta a partir de costo (precio_unitario) y margen % (30 = 30 %, no 0.30).
     * precio_venta = costo / (1 - margen). Si utilidad vacía/≤0, el costo es el precio neto.
     */
    public static function precioUnitarioVenta(float $costo, float|string|null $utilidad): float
    {
        if ($utilidad === null || $utilidad === '') {
            return round($costo, 2);
        }

        $pct = (float) $utilidad;
        if ($pct <= 0) {
            return round($costo, 2);
        }

        $margen = $pct / 100;
        if ($margen >= 1) {
            return round($costo, 2);
        }

        return round($costo / (1 - $margen), 2);
    }

    /**
     * Precio unitario de venta de esta partida (para show/PDF/factura).
     */
    public function precioUnitarioVentaCalculado(): float
    {
        return self::precioUnitarioVenta((float) $this->precio_unitario, $this->utilidad);
    }

    /**
     * Utilidad en pesos de la línea (solo si hay % utilidad): neto venta − costo neto.
     * Ambos netos ya consideran descuento %. Uso interno; no va al PDF del cliente.
     */
    public function utilidadMonto(): float
    {
        return self::utilidadMontoLinea(
            (float) $this->cantidad,
            (float) $this->precio_unitario,
            $this->utilidad,
            (float) ($this->descuento_porcentaje ?? 0)
        );
    }

    /**
     * Utilidad en pesos a partir de costo y margen %.
     */
    public static function utilidadMontoLinea(
        float $cantidad,
        float $costo,
        float|string|null $utilidadPct,
        float $descuentoPct = 0.0
    ): float {
        if ($utilidadPct === null || $utilidadPct === '' || (float) $utilidadPct <= 0) {
            return 0.0;
        }

        $precioVenta = self::precioUnitarioVenta($costo, $utilidadPct);
        $factorDesc = 1 - (max(0.0, min(100.0, $descuentoPct)) / 100);
        $netoVenta = $cantidad * $precioVenta * $factorDesc;
        $costoNeto = $cantidad * $costo * $factorDesc;

        return round($netoVenta - $costoNeto, 2);
    }

    /**
     * Normaliza utilidad del request: vacío → null.
     */
    public static function normalizarUtilidad(mixed $utilidad): ?float
    {
        if ($utilidad === null || $utilidad === '') {
            return null;
        }

        return (float) $utilidad;
    }

    /**
     * Calcular importes automáticamente (sobre precio de venta, no sobre costo).
     */
    public static function calcularImportes(array $datos): array
    {
        $cantidad = floatval($datos['cantidad']);
        $costo = floatval($datos['precio_unitario']);
        $utilidad = array_key_exists('utilidad', $datos)
            ? self::normalizarUtilidad($datos['utilidad'])
            : null;
        $precioVenta = self::precioUnitarioVenta($costo, $utilidad);
        $descuentoPorcentaje = floatval($datos['descuento_porcentaje'] ?? 0);
        $tasaIva = array_key_exists('tasa_iva', $datos) && $datos['tasa_iva'] !== null && $datos['tasa_iva'] !== ''
            ? floatval($datos['tasa_iva'])
            : null;

        // Subtotal sobre precio de venta
        $subtotal = $cantidad * $precioVenta;

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