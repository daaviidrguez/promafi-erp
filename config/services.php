<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Facturama / cURL SSL
    |--------------------------------------------------------------------------
    | Error 60 "unable to get local issuer certificate" ocurre cuando PHP no
    | encuentra el bundle de CA para verificar certificados SSL.
    |
    | Opciones para CURL_CA_BUNDLE:
    | - No definido: prueba automáticamente rutas del sistema (RHEL, Debian, etc.)
    | - /etc/pki/tls/certs/ca-bundle.crt: RHEL/CentOS/Fedora (recomendado)
    | - /etc/ssl/certs/ca-certificates.crt: Debian/Ubuntu
    | - /ruta/cacert.pem: archivo descargado de curl.se/ca/cacert.pem
    | - false: desactiva verificación SSL (solo si no hay otra opción)
    */
    'facturama' => [
        'verify' => env('CURL_CA_BUNDLE'),
    ],

];
