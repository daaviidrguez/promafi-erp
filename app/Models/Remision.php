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
