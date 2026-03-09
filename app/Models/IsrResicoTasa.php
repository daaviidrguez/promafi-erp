<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IsrResicoTasa extends Model
{
    protected $table = 'isr_resico_tasas';

    protected $fillable = ['desde', 'hasta', 'tasa', 'orden'];

    protected $casts = [
        'desde' => 'decimal:2',
        'hasta' => 'decimal:2',
        'tasa' => 'decimal:4',
    ];
}
