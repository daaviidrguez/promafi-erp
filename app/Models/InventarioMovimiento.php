<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventarioMovimiento extends Model
{
    protected $table = 'inventario_movimientos';

    protected $fillable = [
        'producto_id',
        'tipo',
        'cantidad',
        'stock_anterior',
        'stock_resultante',
        'costo_unitario',
        'costo_promedio_anterior',
        'costo_promedio_resultante',
        'folio',
        'factura_id',
        'remision_id',
        'orden_compra_id',
        'factura_compra_id',
        'entrada_anticipada_id',
        'reverso_de_id',
        'usuario_id',
        'observaciones',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'stock_anterior' => 'decimal:2',
        'stock_resultante' => 'decimal:2',
        'costo_unitario' => 'decimal:4',
        'costo_promedio_anterior' => 'decimal:4',
        'costo_promedio_resultante' => 'decimal:4',
    ];

    const TIPO_ENTRADA_COMPRA = 'entrada_compra';
    const TIPO_SALIDA_COMPRA = 'salida_compra';
    const TIPO_SALIDA_FACTURA = 'salida_factura';
    const TIPO_DEVOLUCION_FACTURA = 'devolucion_factura';
    const TIPO_SALIDA_REMISION = 'salida_remision';
    const TIPO_ENTRADA_REMISION = 'entrada_remision';
    const TIPO_ENTRADA_MANUAL = 'entrada_manual';
    const TIPO_SALIDA_MANUAL = 'salida_manual';
    const TIPO_ENTRADA_ANTICIPADA = 'entrada_anticipada';
    const TIPO_SALIDA_ANTICIPADA = 'salida_anticipada';
    const TIPO_AJUSTE_COSTO_FACTURA_COMPRA = 'ajuste_costo_factura_compra';

    public static function tiposEntrada(): array
    {
        return [
            self::TIPO_ENTRADA_COMPRA,
            self::TIPO_ENTRADA_REMISION,
            self::TIPO_ENTRADA_MANUAL,
            self::TIPO_ENTRADA_ANTICIPADA,
            self::TIPO_DEVOLUCION_FACTURA,
        ];
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

    public function facturaCompra(): BelongsTo
    {
        return $this->belongsTo(FacturaCompra::class);
    }

    public function entradaAnticipada(): BelongsTo
    {
        return $this->belongsTo(EntradaAnticipada::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reversoDe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverso_de_id');
    }

    public function reverso(): HasOne
    {
        return $this->hasOne(self::class, 'reverso_de_id');
    }

    public function esManual(): bool
    {
        return in_array($this->tipo, [self::TIPO_ENTRADA_MANUAL, self::TIPO_SALIDA_MANUAL], true);
    }

    public function esReverso(): bool
    {
        return $this->reverso_de_id !== null;
    }

    public function estaRevertido(): bool
    {
        if ($this->relationLoaded('reverso')) {
            return $this->reverso !== null;
        }

        return self::where('reverso_de_id', $this->id)->exists();
    }

    /**
     * Solo movimientos manuales originales (no ya revertidos ni ellos mismos un reverso).
     */
    public function esReversible(): bool
    {
        return $this->esManual() && ! $this->esReverso() && ! $this->estaRevertido();
    }

    public function motivoNoReversible(): ?string
    {
        if (! $this->esManual()) {
            return 'Solo se pueden reversar entradas o salidas manuales.';
        }
        if ($this->esReverso()) {
            return 'Este movimiento ya es una reversa; no se puede reversar de nuevo.';
        }
        if ($this->estaRevertido()) {
            return 'Este movimiento ya fue revertido.';
        }

        return null;
    }

    public function getEtiquetaTipoAttribute(): string
    {
        if ($this->esReverso()) {
            return match ($this->tipo) {
                self::TIPO_ENTRADA_MANUAL => 'Entrada (reversa manual)',
                self::TIPO_SALIDA_MANUAL => 'Salida (reversa manual)',
                default => 'Reversa manual',
            };
        }

        return match ($this->tipo) {
            self::TIPO_ENTRADA_COMPRA => 'Entrada (compra)',
            self::TIPO_SALIDA_COMPRA => 'Salida (reversa compra)',
            self::TIPO_SALIDA_FACTURA => 'Salida (factura)',
            self::TIPO_DEVOLUCION_FACTURA => 'Devolución (factura cancelada)',
            self::TIPO_SALIDA_REMISION => 'Salida (remisión)',
            self::TIPO_ENTRADA_REMISION => 'Entrada (reversa remisión)',
            self::TIPO_ENTRADA_MANUAL => 'Entrada manual',
            self::TIPO_SALIDA_MANUAL => 'Salida manual',
            self::TIPO_ENTRADA_ANTICIPADA => 'Entrada (anticipada)',
            self::TIPO_SALIDA_ANTICIPADA => 'Salida (reversa EA)',
            self::TIPO_AJUSTE_COSTO_FACTURA_COMPRA => 'Ajuste costo (factura)',
            default => $this->tipo,
        };
    }

    /**
     * Genera el siguiente folio para movimientos manuales: IM-YYYY-0001, IM-YYYY-0002, etc.
     */
    public static function generarFolioManual(): string
    {
        $year = date('Y');
        $ultimo = self::whereIn('tipo', [self::TIPO_ENTRADA_MANUAL, self::TIPO_SALIDA_MANUAL])
            ->whereNotNull('folio')
            ->where('folio', 'like', "IM-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();
        $numero = $ultimo && preg_match('/IM-' . $year . '-(\d{4})/', $ultimo->folio, $m) ? (int) $m[1] + 1 : 1;
        return 'IM-' . $year . '-' . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Referencia para mostrar en columnas (folio o etiqueta tipo).
     */
    public function getFolioReferenciaAttribute(): string
    {
        return $this->folio ?? $this->etiqueta_tipo;
    }

    /**
     * Costo de valuación vigente del producto (promedio almacenado o costo de catálogo).
     */
    public static function costoValuacionProducto(Producto $producto): float
    {
        if ($producto->costo_promedio !== null && (float) $producto->costo_promedio > 0) {
            return (float) $producto->costo_promedio;
        }

        return (float) ($producto->costo ?? 0);
    }

    /**
     * Promedio ponderado al ingresar mercancía con costo unitario.
     * Misma fórmula que recepción de compra / entrada anticipada.
     * No modifica el costo de catálogo del producto.
     */
    public static function calcularCostoPromedioEntrada(
        float $stockAnterior,
        float $costoPromedioAnterior,
        float $cantidad,
        float $costoUnitario
    ): float {
        $denominador = $stockAnterior + $cantidad;
        if ($denominador <= 0) {
            return max(0, round($costoUnitario, 2));
        }

        return round(
            ($stockAnterior * $costoPromedioAnterior + $cantidad * $costoUnitario) / $denominador,
            2
        );
    }

    /**
     * Entradas que, con costo unitario, actualizan costo_promedio (no el costo de catálogo).
     */
    public static function tiposEntradaQueActualizanCostoPromedio(): array
    {
        return [
            self::TIPO_ENTRADA_MANUAL,
            self::TIPO_ENTRADA_COMPRA,
            self::TIPO_ENTRADA_ANTICIPADA,
        ];
    }

    /**
     * Registrar movimiento y actualizar stock del producto.
     * Entradas valuadas (manual, compra, anticipada) con costo_unitario: actualizan costo_promedio.
     * Salidas: pueden auditar costo_unitario sin cambiar el promedio aquí (la reversa de compra/EA la hace el caller).
     */
    public static function registrar(
        Producto $producto,
        string $tipo,
        float $cantidad,
        ?int $usuarioId = null,
        ?int $facturaId = null,
        ?int $remisionId = null,
        ?int $ordenCompraId = null,
        ?int $facturaCompraId = null,
        ?string $observaciones = null,
        ?int $entradaAnticipadaId = null,
        ?float $costoUnitario = null
    ): self {
        if (!$producto->controla_inventario) {
            throw new \InvalidArgumentException('El producto no controla inventario');
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use (
            $producto,
            $tipo,
            $cantidad,
            $usuarioId,
            $facturaId,
            $remisionId,
            $ordenCompraId,
            $facturaCompraId,
            $observaciones,
            $entradaAnticipadaId,
            $costoUnitario
        ) {
            $producto = Producto::query()->whereKey($producto->id)->lockForUpdate()->firstOrFail();
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

            $esManual = in_array($tipo, [self::TIPO_ENTRADA_MANUAL, self::TIPO_SALIDA_MANUAL], true);
            $actualizaPromedio = in_array($tipo, self::tiposEntradaQueActualizanCostoPromedio(), true);
            $costoPromedioAnterior = self::costoValuacionProducto($producto);
            $costoUnitarioMovimiento = null;
            $costoPromedioResultante = null;
            $registroPromedio = false;
            $productoUpdate = ['stock' => $stockResultante];

            if ($tipo === self::TIPO_ENTRADA_MANUAL && $costoUnitario === null) {
                throw new \InvalidArgumentException('El costo unitario es obligatorio en entradas manuales');
            }

            if ($actualizaPromedio && $costoUnitario !== null) {
                if ($costoUnitario < 0) {
                    throw new \InvalidArgumentException('El costo unitario no puede ser negativo');
                }
                $costoUnitarioMovimiento = round((float) $costoUnitario, 4);
                $costoPromedioResultante = self::calcularCostoPromedioEntrada(
                    $stockAnterior,
                    $costoPromedioAnterior,
                    $cantidad,
                    $costoUnitarioMovimiento
                );
                // Solo costo_promedio: el costo de catálogo no se modifica.
                $productoUpdate['costo_promedio'] = $costoPromedioResultante;
                $registroPromedio = true;
            } elseif ($tipo === self::TIPO_SALIDA_MANUAL) {
                $costoUnitarioMovimiento = $costoUnitario !== null
                    ? round((float) $costoUnitario, 4)
                    : round($costoPromedioAnterior, 4);
                $costoPromedioResultante = $costoPromedioAnterior;
                $registroPromedio = true;
            } elseif ($costoUnitario !== null) {
                // Auditoría de costo en salidas/otros (p. ej. reversa compra/EA); no recalcula aquí.
                if ($costoUnitario < 0) {
                    throw new \InvalidArgumentException('El costo unitario no puede ser negativo');
                }
                $costoUnitarioMovimiento = round((float) $costoUnitario, 4);
            }

            $producto->update($productoUpdate);

            $data = [
                'producto_id' => $producto->id,
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'stock_anterior' => $stockAnterior,
                'stock_resultante' => $stockResultante,
                'factura_id' => $facturaId,
                'remision_id' => $remisionId,
                'orden_compra_id' => $ordenCompraId,
                'factura_compra_id' => $facturaCompraId,
                'entrada_anticipada_id' => $entradaAnticipadaId,
                'usuario_id' => $usuarioId ?? auth()->id(),
                'observaciones' => $observaciones,
            ];

            if ($esManual) {
                $data['folio'] = self::generarFolioManual();
            }

            if ($costoUnitarioMovimiento !== null) {
                $data['costo_unitario'] = $costoUnitarioMovimiento;
            }

            if ($registroPromedio) {
                $data['costo_promedio_anterior'] = round($costoPromedioAnterior, 4);
                $data['costo_promedio_resultante'] = $costoPromedioResultante !== null
                    ? round($costoPromedioResultante, 4)
                    : null;
            }

            return self::create($data);
        });
    }

    /**
     * Reversa un movimiento manual creando el movimiento opuesto (append-only).
     * - Entrada → salida (exige stock disponible; restaura costo_promedio).
     * - Salida → entrada (reincorpora stock con el costo auditado en el original).
     */
    public function reversarManual(?int $usuarioId = null, ?string $motivo = null): self
    {
        $motivoBloqueo = $this->motivoNoReversible();
        if ($motivoBloqueo !== null) {
            throw new \InvalidArgumentException($motivoBloqueo);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($usuarioId, $motivo) {
            $original = self::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            $motivoBloqueo = $original->motivoNoReversible();
            if ($motivoBloqueo !== null) {
                throw new \InvalidArgumentException($motivoBloqueo);
            }

            $producto = Producto::query()->whereKey($original->producto_id)->lockForUpdate()->firstOrFail();
            if (! $producto->controla_inventario) {
                throw new \InvalidArgumentException('El producto no controla inventario');
            }

            $cantidad = (float) $original->cantidad;
            $folioOrigen = $original->folio ?? ('#' . $original->id);
            $obs = 'Reversa de ' . $folioOrigen;
            if ($motivo !== null && trim($motivo) !== '') {
                $obs .= '. Motivo: ' . trim($motivo);
            }

            $esEntradaOriginal = self::esEntrada($original->tipo);
            $tipoReverso = $esEntradaOriginal
                ? self::TIPO_SALIDA_MANUAL
                : self::TIPO_ENTRADA_MANUAL;

            $costoUnitarioOriginal = $original->costo_unitario !== null
                ? (float) $original->costo_unitario
                : null;

            if ($esEntradaOriginal) {
                // Quitar del promedio la contribución de la entrada (mismo criterio que cancelar compra).
                $stockActual = (float) $producto->stock;
                $stockDespues = $stockActual - $cantidad;
                if ($stockDespues < 0) {
                    throw new \InvalidArgumentException(
                        "Stock insuficiente para reversar. Disponible: {$stockActual}, se requieren {$cantidad}."
                    );
                }

                $costoActual = self::costoValuacionProducto($producto);
                $costoEntrada = $costoUnitarioOriginal ?? $costoActual;

                if ($stockDespues > 0) {
                    $nuevoPromedio = round(
                        ($stockActual * $costoActual - $cantidad * $costoEntrada) / $stockDespues,
                        2
                    );
                    $producto->update(['costo_promedio' => max(0, $nuevoPromedio)]);
                } else {
                    $restaurado = $original->costo_promedio_anterior !== null
                        ? (float) $original->costo_promedio_anterior
                        : (float) ($producto->costo ?? 0);
                    $producto->update(['costo_promedio' => max(0, $restaurado)]);
                }

                $reverso = self::registrar(
                    $producto->fresh(),
                    $tipoReverso,
                    $cantidad,
                    $usuarioId,
                    null,
                    null,
                    null,
                    null,
                    $obs,
                    null,
                    $costoEntrada
                );
            } else {
                $costoEntrada = $costoUnitarioOriginal ?? self::costoValuacionProducto($producto);
                $reverso = self::registrar(
                    $producto,
                    $tipoReverso,
                    $cantidad,
                    $usuarioId,
                    null,
                    null,
                    null,
                    null,
                    $obs,
                    null,
                    $costoEntrada
                );
            }

            $reverso->update(['reverso_de_id' => $original->id]);

            return $reverso->fresh(['reversoDe', 'producto', 'usuario']);
        });
    }
}
