<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogisticaEnvio extends Model
{
    public const ESTADOS = ['pendiente', 'preparado', 'enviado', 'en_ruta', 'entrega_parcial', 'entregado', 'cancelado'];

    /** @var array<string, int> */
    private const PROGRESO = [
        'pendiente' => 0,
        'preparado' => 1,
        'enviado' => 2,
        'en_ruta' => 3,
        'entrega_parcial' => 4,
        'entregado' => 5,
    ];

    protected $table = 'logistica_envios';

    protected $fillable = [
        'folio',
        'estado',
        'cliente_id',
        'usuario_id',
        'factura_id',
        'remision_id',
        'cliente_direccion_entrega_id',
        'direccion_entrega',
        'chofer',
        'recibido_almacen',
        'lugar_entrega',
        'entrega_recibido_por',
        'notas',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function remision(): BelongsTo
    {
        return $this->belongsTo(Remision::class);
    }

    public function direccionEntregaRel(): BelongsTo
    {
        return $this->belongsTo(ClienteDireccionEntrega::class, 'cliente_direccion_entrega_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LogisticaEnvioItem::class, 'logistica_envio_id')->orderBy('id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(LogisticaEnvioHistorial::class, 'logistica_envio_id')->orderBy('id');
    }

    public static function cantidadEnviadaFacturaDetalle(int $facturaDetalleId): float
    {
        return (float) LogisticaEnvioItem::query()
            ->where('factura_detalle_id', $facturaDetalleId)
            ->whereHas('envio', fn ($q) => $q->where('estado', '!=', 'cancelado'))
            ->sum('cantidad');
    }

    public static function cantidadEntregadaEnDestinoFacturaDetalle(int $facturaDetalleId): float
    {
        return (float) LogisticaEnvioItem::query()
            ->where('factura_detalle_id', $facturaDetalleId)
            ->whereHas('envio', fn ($q) => $q->where('estado', '!=', 'cancelado'))
            ->where('linea_entregada', true)
            ->sum('cantidad');
    }

    public static function cantidadEnRutaSinEntregarFacturaDetalle(int $facturaDetalleId): float
    {
        return (float) LogisticaEnvioItem::query()
            ->where('factura_detalle_id', $facturaDetalleId)
            ->whereHas('envio', fn ($q) => $q->where('estado', '!=', 'cancelado'))
            ->where('linea_entregada', false)
            ->sum('cantidad');
    }

    public static function cantidadPendienteEntregaFacturaDetalle(int $facturaDetalleId): float
    {
        $det = FacturaDetalle::query()->find($facturaDetalleId);
        if (! $det) {
            return 0.0;
        }

        $entregado = self::cantidadEntregadaEnDestinoFacturaDetalle($facturaDetalleId);

        return max(0.0, (float) $det->cantidad - $entregado);
    }

    public static function cantidadMaximaNuevoEnvioFacturaDetalle(int $facturaDetalleId): float
    {
        $det = FacturaDetalle::query()->find($facturaDetalleId);
        if (! $det) {
            return 0.0;
        }
        $fd = (float) $det->cantidad;
        $asignado = self::cantidadEnviadaFacturaDetalle($facturaDetalleId);
        $enRutaSinEntregar = self::cantidadEnRutaSinEntregarFacturaDetalle($facturaDetalleId);

        return max(0.0, ($fd - $asignado) + $enRutaSinEntregar);
    }

    public static function cantidadEnviadaRemisionDetalle(int $remisionDetalleId): float
    {
        return (float) LogisticaEnvioItem::query()
            ->where('remision_detalle_id', $remisionDetalleId)
            ->whereHas('envio', fn ($q) => $q->where('estado', '!=', 'cancelado'))
            ->sum('cantidad');
    }

    /**
     * Cantidad marcada como entregada en destino (checks en entrega parcial / entregado).
     */
    public static function cantidadEntregadaEnDestinoRemisionDetalle(int $remisionDetalleId): float
    {
        return (float) LogisticaEnvioItem::query()
            ->where('remision_detalle_id', $remisionDetalleId)
            ->whereHas('envio', fn ($q) => $q->where('estado', '!=', 'cancelado'))
            ->where('linea_entregada', true)
            ->sum('cantidad');
    }

    /**
     * Cantidad aún en ruta / sin marcar entregada en destino (puede reasignarse a otro envío).
     */
    public static function cantidadEnRutaSinEntregarRemisionDetalle(int $remisionDetalleId): float
    {
        return (float) LogisticaEnvioItem::query()
            ->where('remision_detalle_id', $remisionDetalleId)
            ->whereHas('envio', fn ($q) => $q->where('estado', '!=', 'cancelado'))
            ->where('linea_entregada', false)
            ->sum('cantidad');
    }

    /**
     * Pendiente de entrega al cliente para una línea de remisión (total remisión − entregado en destino).
     */
    public static function cantidadPendienteEntregaRemisionDetalle(int $remisionDetalleId): float
    {
        $det = RemisionDetalle::query()->find($remisionDetalleId);
        if (! $det) {
            return 0.0;
        }

        $entregado = self::cantidadEntregadaEnDestinoRemisionDetalle($remisionDetalleId);

        return max(0.0, (float) $det->cantidad - $entregado);
    }

    /**
     * Máxima cantidad que puede incorporarse en un nuevo envío: hueco de inventario + cantidad en ruta no entregada (reasignable).
     */
    public static function cantidadMaximaNuevoEnvioRemisionDetalle(int $remisionDetalleId): float
    {
        $det = RemisionDetalle::query()->find($remisionDetalleId);
        if (! $det) {
            return 0.0;
        }
        $rd = (float) $det->cantidad;
        $asignado = self::cantidadEnviadaRemisionDetalle($remisionDetalleId);
        $enRutaSinEntregar = self::cantidadEnRutaSinEntregarRemisionDetalle($remisionDetalleId);

        return max(0.0, ($rd - $asignado) + $enRutaSinEntregar);
    }

    /**
     * Siguiente folio de logística. Debe llamarse dentro de DB::transaction (usa lockForUpdate en empresa).
     */
    public static function siguienteFolioEnTransaccion(): string
    {
        $empresa = Empresa::principal();
        if (! $empresa) {
            $n = (int) self::query()->max('id') + 1;

            return 'LOG-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
        }

        $e = Empresa::query()->whereKey($empresa->id)->lockForUpdate()->first();
        if (! $e) {
            return 'LOG-0001';
        }
        $folio = $e->obtenerSiguienteFolioLogistica();
        $e->incrementarFolioLogistica();

        return $folio;
    }

    public function puedeTransicionarA(string $nuevo): bool
    {
        if (! in_array($nuevo, self::ESTADOS, true)) {
            return false;
        }
        if ($nuevo === $this->estado) {
            return true;
        }
        if ($nuevo === 'cancelado') {
            return ! in_array($this->estado, ['cancelado', 'entregado'], true);
        }
        if (in_array($this->estado, ['cancelado', 'entregado'], true)) {
            return false;
        }
        $i = self::PROGRESO[$this->estado] ?? -1;
        $j = self::PROGRESO[$nuevo] ?? -1;

        return $j > $i;
    }

    public function registrarHistorial(?string $anterior, string $nuevo, ?int $userId, ?string $nota = null): void
    {
        $this->historial()->create([
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'user_id' => $userId,
            'nota' => $nota,
        ]);
    }

    public function aplicarEstado(string $nuevo, ?int $userId, ?string $nota = null): void
    {
        if ($this->estado === $nuevo) {
            return;
        }
        $ant = $this->estado;
        $this->estado = $nuevo;
        $this->save();
        $this->registrarHistorial($ant, $nuevo, $userId, $nota);
    }

    public function getEstadoEtiquetaAttribute(): string
    {
        return match ($this->estado) {
            'pendiente' => 'Pendiente',
            'preparado' => 'Preparado',
            'enviado' => 'Enviado',
            'en_ruta' => 'En ruta',
            'entrega_parcial' => 'Entrega parcial',
            'entregado' => 'Entregado',
            'cancelado' => 'Cancelado',
            default => $this->estado ?? '—',
        };
    }
}
