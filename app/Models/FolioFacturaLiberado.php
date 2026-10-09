<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FolioFacturaLiberado extends Model
{
    protected $table = 'folios_factura_liberados';

    protected $fillable = [
        'empresa_id',
        'serie',
        'folio',
    ];

    protected $casts = [
        'folio' => 'integer',
    ];
}
