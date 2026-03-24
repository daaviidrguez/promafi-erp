<?php

return [

    /*
    |--------------------------------------------------------------------------
    | URL pública del ERP (para mostrar enlaces de API en pantallas internas)
    | Ej. producción: https://erp.promafi.mx
    |--------------------------------------------------------------------------
    */
    'public_base_url' => rtrim((string) env('CATALOGO_PUBLIC_BASE_URL', env('APP_URL', 'http://localhost')), '/'),

    /*
    |--------------------------------------------------------------------------
    | Token para consumir GET /api/v1/catalogo/* desde sitios externos
    | (ej. promafi.mx/catalogo). Enviar: Authorization: Bearer {token}
    | o cabecera X-Catalog-Token: {token}
    |--------------------------------------------------------------------------
    */
    'api_token' => env('CATALOGO_API_TOKEN'),

];
