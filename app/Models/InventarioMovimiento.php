<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioMovimiento extends Model
{
    protected $table = 'inventario_movimientos';

    protected $fillable = [
        'producto_id',
        'tipo',
        'cantidad',
        'stock_anterior',
        'stock_resultante',
        'factura_id',
        'remision_id',
        'orden_compra_id',
        'usuario_id',
        'observaciones',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'stock_anterior' => 'decimal:2',
        'stock_resultante' => 'decimal:2',
    ];

    const TIPO_ENTRADA_COMPRA = 'entrada_compra';
    const TIPO_SALIDA_FACTURA = 'salida_factura';
    const TIPO_DEVOLUCION_FACTURA = 'devolucion_factura';
    const TIPO_SALIDA_REMISION = 'salida_remision';
    const TIPO_ENTRADA_MANUAL = 'entrada_manual';
    const TIPO_SALIDA_MANUAL = 'salida_manual';

    public static function tiposEntrada(): array
    {
        return [self::TIPO_ENTRADA_COMPRA, self::TIPO_ENTRADA_MANUAL, self::TIPO_DEVOLUCION_FACTURA];
    }

    public static function esEntrada(string $tipo): bool
    {
        return in_array($tipo, self::tiposEntrada(), true);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function remision(): BelongsTo
    {
        return $this->belongsTo(Remision::class);
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getEtiquetaTipoAttribute(): string
    {
        return match ($this->tipo) {
            self::TIPO_ENTRADA_COMPRA => 'Entrada (compra)',
            self::TIPO_SALIDA_FACTURA => 'Salida (factura)',
            self::TIPO_DEVOLUCION_FACTURA => 'Devolución (factura cancelada)',
            self::TIPO_SALIDA_REMISION => 'Salida (remisión)',
            self::TIPO_ENTRADA_MANUAL => 'Entrada manual',
            self::TIPO_SALIDA_MANUAL => 'Salida manual',
            default => $this->tipo,
        };
    }

    /**
     * Registrar movimiento y actualizar stock del producto.
     */
    public static function registrar(
        Producto $producto,
        string $tipo,
        float $cantidad,
        ?int $usuarioId = null,
        ?int $facturaId = null,
        ?int $remisionId = null,
        ?int $ordenCompraId = null,
        ?string $observaciones = null
    ): self {
        if (!$producto->controla_inventario) {
            throw new \InvalidArgumentException('El producto no controla inventario');
        }
        $stockAnterior = (float) $producto->stock;
        $esEntrada = self::esEntrada($tipo);
        $stockResultante = $esEntrada
            ? $stockAnterior + $cantidad
            : $stockAnterior - $cantidad;
        if ($stockResultante < 0) {
            throw new \InvalidArgumentException("Stock insuficiente para el producto {$producto->nombre}. Disponible: {$stockAnterior}");
        }
        $producto->update(['stock' => $stockResultante]);
        $mov = self::create([
            'producto_id' => $producto->id,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'stock_anterior' => $stockAnterior,
            'stock_resultante' => $stockResultante,
            'factura_id' => $facturaId,
            'remision_id' => $remisionId,
            'orden_compra_id' => $ordenCompraId,
            'usuario_id' => $usuarioId ?? auth()->id(),
            'observaciones' => $observaciones,
        ]);
        return $mov;
    }
}
