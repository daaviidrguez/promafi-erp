<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaSoporte extends Model
{
    protected $table = 'factura_soportes';

    protected $fillable = [
        'factura_id',
        'nombre_original',
        'path',
        'mime_type',
        'size',
        'usuario_id',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
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

    public function contentType(): string
    {
        $mime = strtolower((string) $this->mime_type);
        $permitidos = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        if (in_array($mime, $permitidos, true)) {
            return $mime;
        }

        $ext = strtolower(pathinfo((string) $this->nombre_original, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}
