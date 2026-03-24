<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Producto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'codigo',
        'codigo_barras',
        'nombre',
        'marca',
        'descripcion',
        'clave_sat',
        'clave_unidad_sat',
        'unidad',
        'objeto_impuesto',
        'tipo_impuesto',
        'tipo_factor',
        'costo',
        'costo_promedio',
        'precio_venta',
        'precio_mayoreo',
        'precio_minimo',
        'tasa_iva',
        'aplica_iva',
        'tasa_ieps',
        'stock',
        'stock_minimo',
        'stock_maximo',
        'controla_inventario',
        'categoria_id',
        'imagen_principal',
        'imagenes',
        'catalogo_online_visible',
        'catalogo_online_mostrar_precio',
        'activo',
        'notas',
    ];

    protected $casts = [
        'costo' => 'decimal:2',
        'costo_promedio' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'precio_mayoreo' => 'decimal:2',
        'precio_minimo' => 'decimal:2',
        'tasa_iva' => 'decimal:4',
        'aplica_iva' => 'boolean',
        'tasa_ieps' => 'decimal:4',
        'stock' => 'decimal:2',
        'stock_minimo' => 'decimal:2',
        'stock_maximo' => 'decimal:2',
        'controla_inventario' => 'boolean',
        'activo' => 'boolean',
        'imagenes' => 'array',
        'catalogo_online_visible' => 'boolean',
        'catalogo_online_mostrar_precio' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::forceDeleting(function (Producto $producto) {
            $producto->eliminarArchivosImagenesDelDisco();
        });
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
            if (! empty($this->imagen_principal)) {
                return [trim((string) $this->imagen_principal)];
            }

            return [];
        }

        return array_values(array_filter(array_map(static fn ($p) => is_string($p) ? trim($p) : '', $paths)));
    }

    /**
     * URLs públicas para mostrar en vistas (storage link).
     *
     * @return array<int, string>
     */
    public function getImagenesUrlsAttribute(): array
    {
        return array_values(array_filter(array_map(
            fn (string $p) => $p !== '' ? asset('storage/'.ltrim($p, '/')) : null,
            $this->rutasImagenes()
        )));
    }

    /**
     * URL del endpoint JSON de este producto para el catálogo online (API pública con token).
     */
    public function urlApiCatalogoOnline(): string
    {
        $base = rtrim((string) config('catalogo.public_base_url', config('app.url')), '/');

        return $base.'/api/v1/catalogo/productos/'.$this->id;
    }

    public function eliminarArchivosImagenesDelDisco(): void
    {
        $paths = array_unique(array_filter(array_merge(
            $this->rutasImagenes(),
            $this->imagen_principal ? [trim((string) $this->imagen_principal)] : []
        )));

        foreach ($paths as $path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Relación con Categoría
     */
    public function categoria()
    {
        return $this->belongsTo(CategoriaProducto::class, 'categoria_id');
    }

    /**
     * Calcular precio con IVA (según tipo_factor y tasa_iva)
     */
    public function getPrecioConIvaAttribute(): float
    {
        if ($this->tipo_factor === 'Exento' || !$this->aplica_iva) {
            return (float) $this->precio_venta;
        }
        $tasa = (float) ($this->tasa_iva ?? 0);
        return (float) ($this->precio_venta * (1 + $tasa));
    }

    /**
     * Si el concepto es objeto de impuesto y tiene tasa (no exento)
     */
    public function aplicaImpuestoTraslado(): bool
    {
        if (!in_array($this->objeto_impuesto ?? '02', ['02', '03'], true)) {
            return false;
        }
        return ($this->tipo_factor ?? 'Tasa') === 'Tasa' && (float)($this->tasa_iva ?? 0) > 0;
    }

    /**
     * Calcular margen de ganancia
     */
    public function getMargenAttribute(): float
    {
        if ($this->costo == 0) {
            return 0;
        }

        return (($this->precio_venta - $this->costo) / $this->costo) * 100;
    }

    /**
     * Verificar si hay stock disponible
     */
    public function tieneStock(float $cantidad = 1): bool
    {
        if (!$this->controla_inventario) {
            return true;
        }

        return $this->stock >= $cantidad;
    }

    /**
     * Verificar si está bajo en stock
     */
    public function bajoEnStock(): bool
    {
        return $this->controla_inventario && $this->stock <= $this->stock_minimo;
    }

    /**
     * Descontar stock (usar InventarioMovimiento::registrar para trazabilidad)
     */
    public function descontarStock(float $cantidad): void
    {
        if ($this->controla_inventario) {
            $this->decrement('stock', $cantidad);
        }
    }

    /**
     * Aumentar stock (usar InventarioMovimiento::registrar para trazabilidad)
     */
    public function aumentarStock(float $cantidad): void
    {
        if ($this->controla_inventario) {
            $this->increment('stock', $cantidad);
        }
    }

    public function movimientos()
    {
        return $this->hasMany(InventarioMovimiento::class)->orderByDesc('created_at');
    }

    /**
     * Detalles de órdenes de compra (para calcular costo promedio)
     */
    public function ordenesCompraDetalle()
    {
        return $this->hasMany(OrdenCompraDetalle::class)->whereHas('ordenCompra', function ($q) {
            $q->where('estado', 'recibida');
        });
    }

    /**
     * Costo promedio calculado desde compras recibidas (órdenes y facturas de compra).
     * Promedio ponderado: suma(total) / suma(cantidad). Solo se usa como fallback si costo_promedio no está almacenado.
     */
    public function getCostoPromedioCalculadoAttribute(): ?float
    {
        $sumTotal = 0.0;
        $sumCantidad = 0.0;

        $ordenes = \Illuminate\Support\Facades\DB::table('ordenes_compra_detalle as d')
            ->join('ordenes_compra as o', 'o.id', '=', 'd.orden_compra_id')
            ->where('d.producto_id', $this->id)
            ->where('o.estado', 'recibida')
            ->whereNull('o.deleted_at')
            ->selectRaw('COALESCE(SUM(d.total), 0) as sum_total, COALESCE(SUM(d.cantidad), 0) as sum_cantidad')
            ->first();
        if ($ordenes && (float) $ordenes->sum_cantidad > 0) {
            $sumTotal += (float) $ordenes->sum_total;
            $sumCantidad += (float) $ordenes->sum_cantidad;
        }

        $facturas = \Illuminate\Support\Facades\DB::table('facturas_compra_detalle as d')
            ->join('facturas_compra as f', 'f.id', '=', 'd.factura_compra_id')
            ->where('d.producto_id', $this->id)
            ->where('f.estado', 'recibida')
            ->whereNull('f.deleted_at')
            ->selectRaw('COALESCE(SUM(d.importe), 0) as sum_total, COALESCE(SUM(d.cantidad), 0) as sum_cantidad')
            ->first();
        if ($facturas && (float) $facturas->sum_cantidad > 0) {
            $sumTotal += (float) $facturas->sum_total;
            $sumCantidad += (float) $facturas->sum_cantidad;
        }

        if ($sumCantidad <= 0) {
            return null;
        }
        return round($sumTotal / $sumCantidad, 2);
    }

    /**
     * Costo promedio a mostrar: almacenado o calculado desde compras
     */
    public function getCostoPromedioMostrarAttribute(): ?float
    {
        if ($this->costo_promedio !== null && (float) $this->costo_promedio > 0) {
            return (float) $this->costo_promedio;
        }
        return $this->costo_promedio_calculado;
    }

    /**
     * Scope para productos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para productos con stock bajo
     */
    public function scopeBajoStock($query)
    {
        return $query->where('controla_inventario', true)
                    ->whereColumn('stock', '<=', 'stock_minimo');
    }

    /**
     * Scope para búsqueda
     */
    public function scopeBuscar($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('nombre', 'like', "%{$search}%")
              ->orWhere('codigo', 'like', "%{$search}%")
              ->orWhere('codigo_barras', 'like', "%{$search}%")
              ->orWhere('clave_sat', 'like', "%{$search}%");
        });
    }

    /**
     * Códigos del producto para cada proveedor (catálogo del proveedor).
     */
    public function codigosProveedores()
    {
        return $this->hasMany(ProductoProveedor::class, 'producto_id');
    }
}