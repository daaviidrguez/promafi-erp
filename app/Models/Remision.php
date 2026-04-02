<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Remision extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'remisiones';

    protected $fillable = [
        'folio',
        'estado',
        'cliente_id',
        'factura_id',
        'factura_id_cancelada',
        'orden_compra',
        'empresa_id',
        'cliente_nombre',
        'cliente_rfc',
        'fecha',
        'direccion_entrega',
        'fecha_entrega',
        'observaciones',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_entrega' => 'date',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    /**
     * Factura anterior (cancelada) usada para auditoría cuando la remisión
     * se vuelve a convertir y se conserva el primer CFDI cancelado.
     */
    public function facturaCancelada(): BelongsTo
    {
        return $this->belongsTo(Factura::class, 'factura_id_cancelada');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(RemisionDetalle::class)->orderBy('orden');
    }

    /**
     * Todos los envíos de logística vinculados (puede haber más de uno si quedan partidas por enviar).
     */
    public function logisticaEnvios(): HasMany
    {
        return $this->hasMany(LogisticaEnvio::class)->orderByDesc('id');
    }

    /**
     * Envío más reciente (estado en listados / compatibilidad).
     * Usa ofMany en lugar de latestOfMany para evitar SQL ambiguo en MySQL (1052) con columnas sin calificar.
     */
    public function logisticaEnvio(): HasOne
    {
        return $this->hasOne(LogisticaEnvio::class)->ofMany('id', 'max');
    }

    /**
     * Hay cantidad remisión no cubierta por envíos no cancelados (misma lógica que factura).
     */
    public function tienePartidasPendientesDeEnvioLogistica(): bool
    {
        $this->loadMissing('detalles');

        foreach ($this->detalles as $d) {
            $pend = LogisticaEnvio::cantidadPendienteEntregaRemisionDetalle((int) $d->id);
            if ($pend > 1e-6) {
                return true;
            }
        }

        return false;
    }

    /**
     * Regla del listado "Elegir origen": permite el primer envío si aún no hay envíos activos;
     * si ya hay envíos, solo permite otro cuando hay partidas pendientes en destino y además
     * hubo entrega parcial (estado) o marcas de entregado en destino — evita duplicar mientras
     * el único envío sigue en ruta sin checklist.
     */
    public function permiteNuevoEnvioDesdeElegirOrigen(): bool
    {
        if (! in_array($this->estado, ['enviada', 'entregada'], true)) {
            return false;
        }

        $this->loadMissing('logisticaEnvios');
        $enviosActivos = $this->logisticaEnvios->where('estado', '!=', 'cancelado');
        if ($enviosActivos->isEmpty()) {
            return true;
        }

        if (! $this->tienePartidasPendientesDeEnvioLogistica()) {
            return false;
        }

        if ($enviosActivos->contains('estado', 'entrega_parcial')) {
            return true;
        }

        foreach ($enviosActivos as $envio) {
            $items = $envio->relationLoaded('items')
                ? $envio->items
                : $envio->items()->get(['id', 'linea_entregada']);
            if ($items->contains('linea_entregada', true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generar folio desde configuración de empresa (serie + folio).
     * Reserva el folio incrementando el contador de la empresa.
     */
    public static function generarFolio(): string
    {
        $empresa = \App\Models\Empresa::principal();
        if ($empresa) {
            $folio = $empresa->obtenerSiguienteFolioRemision();
            $empresa->incrementarFolioRemision();

            return $folio;
        }
        // Fallback si no hay empresa: REM-AÑO-0001
        $year = date('Y');
        $ultimo = self::where('folio', 'like', "REM-{$year}-%")->orderBy('id', 'desc')->first();
        $numero = $ultimo && preg_match('/REM-'.$year.'-(\d{4})/', $ultimo->folio, $m) ? (int) $m[1] + 1 : 1;

        return 'REM-'.$year.'-'.str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }

    public function puedeEditarse(): bool
    {
        return $this->estado === 'borrador';
    }

    public function puedeEnviarse(): bool
    {
        return $this->estado === 'borrador';
    }

    public function puedeEntregarse(): bool
    {
        return $this->estado === 'enviada';
    }

    public function puedeCancelarse(): bool
    {
        return in_array($this->estado, ['borrador', 'enviada'], true);
    }

    /**
     * Etiqueta y clase de badge para listados: si la remisión está enviada, refleja el estado del envío de logística.
     *
     * @return array{label: string, badge: string}
     */
    public function estadoVisualListado(): array
    {
        return match ($this->estado) {
            'borrador' => ['label' => 'Borrador', 'badge' => 'badge-warning'],
            'cancelada' => ['label' => 'Cancelada', 'badge' => 'badge-danger'],
            'entregada' => ['label' => 'Entregada', 'badge' => 'badge-success'],
            'enviada' => $this->estadoVisualListadoDesdeEnvio(),
            default => ['label' => $this->estado ?? '—', 'badge' => 'badge-gray'],
        };
    }

    /**
     * @return array{label: string, badge: string}
     */
    private function estadoVisualListadoDesdeEnvio(): array
    {
        $envio = $this->logisticaEnvio;

        if (! $envio) {
            return ['label' => 'Enviada', 'badge' => 'badge-info'];
        }

        return match ($envio->estado) {
            'enviado' => ['label' => 'Enviada', 'badge' => 'badge-info'],
            'entregado' => ['label' => 'Entregada', 'badge' => 'badge-success'],
            'cancelado' => ['label' => 'Cancelada', 'badge' => 'badge-danger'],
            'pendiente' => ['label' => 'Pendiente', 'badge' => 'badge-warning'],
            'preparado' => ['label' => 'Preparado', 'badge' => 'badge-info'],
            'en_ruta' => ['label' => 'En ruta', 'badge' => 'badge-info'],
            'entrega_parcial' => ['label' => 'Entrega parcial', 'badge' => 'badge-warning'],
            default => ['label' => $envio->estado_etiqueta, 'badge' => 'badge-info'],
        };
    }

    /**
     * Texto de ayuda bajo el badge de estado en la vista show (alineado con logística cuando la remisión está enviada).
     */
    public function estadoAyudaParaShow(): string
    {
        return match ($this->estado) {
            'borrador' => 'Puedes editar o enviar la remisión.',
            'entregada' => 'Entrega registrada.',
            'cancelada' => '',
            'enviada' => $this->estadoAyudaParaShowDesdeEnvio(),
            default => '',
        };
    }

    private function estadoAyudaParaShowDesdeEnvio(): string
    {
        $envio = $this->logisticaEnvio;

        if (! $envio) {
            return 'Mercancía salida de almacén (inventario ya descontado). Marca como entregada cuando el cliente reciba.';
        }

        return match ($envio->estado) {
            'en_ruta' => 'El envío en Logística está en ruta. Puedes ver el detalle y el seguimiento en el módulo de Logística.',
            'entrega_parcial' => 'En Logística hay entrega parcial. Revisa las partidas allí antes de marcar la remisión como entregada si el envío aún no está completo.',
            'pendiente' => 'Envío registrado en Logística como pendiente. Mercancía salida de almacén (inventario descontado).',
            'preparado' => 'Envío en Logística preparado. Mercancía salida de almacén (inventario descontado).',
            'enviado' => 'Mercancía salida de almacén (inventario ya descontado). Marca como entregada cuando el cliente reciba.',
            'entregado' => 'Mercancía salida de almacén. El envío en Logística figura como entregado; puedes marcar la remisión como entregada si aún no lo has hecho.',
            'cancelado' => 'El envío vinculado en Logística está cancelado.',
            default => 'Mercancía salida de almacén (inventario ya descontado). Marca como entregada cuando el cliente reciba.',
        };
    }

    /**
     * Ya tiene factura (borrador o timbrada) vinculada.
     */
    public function estaFacturada(): bool
    {
        return $this->factura_id !== null || $this->factura_id_cancelada !== null;
    }

    /**
     * Puede generar factura desde la remisión (entregada y sin factura vinculada).
     */
    public function puedeConvertirseAFactura(): bool
    {
        if ($this->estado !== 'entregada') {
            return false;
        }

        // Si no hay factura activa, se puede convertir.
        if ($this->factura_id === null) {
            return true;
        }

        // Si la factura activa está cancelada, se puede convertir nuevamente.
        $factura = $this->relationLoaded('factura') ? $this->factura : Factura::find($this->factura_id);

        return $factura !== null && $factura->estado === 'cancelada';
    }

    public function scopeBuscar($query, ?string $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('folio', 'like', "%{$search}%")
                ->orWhere('cliente_nombre', 'like', "%{$search}%");
        });
    }
}
