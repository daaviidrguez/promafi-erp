<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        $numero = $ultimo && preg_match('/REM-' . $year . '-(\d{4})/', $ultimo->folio, $m) ? (int) $m[1] + 1 : 1;
        return 'REM-' . $year . '-' . str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
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
