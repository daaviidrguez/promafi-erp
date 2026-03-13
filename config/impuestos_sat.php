<?php

/**
 * Catálogo de impuestos federales SAT / Facturama.
 * Los nombres deben coincidir exactamente con el catálogo de Facturama:
 * IVA | ISR | IEPS | IVA RET | IVA Exento
 *
 * Claves SAT: 001=ISR, 002=IVA, 003=IEPS
 *
 * @see https://apisandbox.facturama.mx/docs/ResourceModel?modelName=TaxBindingv4Model
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Nombres para Facturama API (Name del impuesto)
    |--------------------------------------------------------------------------
    | Facturama solo admite: IVA, ISR, IEPS, IVA RET, IVA Exento
    | - IVA RET: solo para retención de IVA (002 + retención)
    | - ISR/IEPS retención: usar "ISR" o "IEPS" con IsRetention=true (no "ISR RET")
    */
    'nombres_facturama' => [
        'traslado' => [
            '001' => 'ISR',
            '002' => 'IVA',
            '003' => 'IEPS',
        ],
        'retencion' => [
            '001' => 'ISR',      // IsRetention=true, no "ISR RET"
            '002' => 'IVA RET',  // Facturama exige "IVA RET" explícitamente
            '003' => 'IEPS',     // IsRetention=true, no "IEPS RET"
        ],
        'exento' => [
            '002' => 'IVA Exento',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nombres para mostrar en la app (coherente con Datos Fiscales del producto)
    |--------------------------------------------------------------------------
    */
    'nombres_display' => [
        '001' => 'ISR',
        '002' => 'IVA',
        '003' => 'IEPS',
    ],
];
