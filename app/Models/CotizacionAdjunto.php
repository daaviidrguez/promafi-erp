<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionAdjunto extends Model
{
    protected $table = 'cotizacion_adjuntos';

    protected $fillable = [
        'cotizacion_id',
        'nombre_original',
        'path',
        'mime_type',
        'size',
        'nota',
        'usuario_id',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ruta absoluta en disco (storage/app/...).
     */
    public function rutaAbsoluta(): string
    {
        return storage_path('app/' . $this->path);
    }

    public function existeEnDisco(): bool
    {
        return is_file($this->rutaAbsoluta());
    }

    public function eliminarDelDisco(): void
    {
        $full = $this->rutaAbsoluta();
        if (is_file($full)) {
            @unlink($full);
        }
    }

    public function tamanoLegible(): string
    {
        $bytes = (int) ($this->size ?? 0);
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return number_format($bytes / 1048576, 1) . ' MB';
    }
}
