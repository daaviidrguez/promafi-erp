<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tabla ISR RESICO (Régimen Simplificado de Confianza - clave 626)
    |--------------------------------------------------------------------------
    | Tasas aproximadas sobre ingreso mensual (persona física).
    | Rango en MXN => tasa en decimal (1% = 0.01)
    */
    'regimen_clave' => '626',

    /*
    |--------------------------------------------------------------------------
    | Retención ISR cuando Persona Moral paga a Persona Física RESICO
    |--------------------------------------------------------------------------
    | Según LISR Art. 152 y 113-J, la persona moral debe retener 1.25% sobre
    | el subtotal (base gravable sin IVA). SAT 2026.
    */
    'tasa_retencion_pm_a_resico' => 0.0125,

    'tasas' => [
        ['desde' => 0, 'hasta' => 25000, 'tasa' => 0.01],      // 1%
        ['desde' => 25000, 'hasta' => 50000, 'tasa' => 0.011],   // 1.1%
        ['desde' => 50000, 'hasta' => 83333, 'tasa' => 0.015],   // 1.5%
        ['desde' => 83333, 'hasta' => 208333, 'tasa' => 0.02],   // 2%
        ['desde' => 208333, 'hasta' => 3500000, 'tasa' => 0.025], // 2.5%
    ],
];
