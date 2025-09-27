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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /**
     * Datos relacionados a la cuenta de Bitrix consigudaos en .env
     */
    'bitrix' => [
        // URL pública (ngrok) donde Bitrix enviará los webhooks
        // esto para casos de Prueba, en producción debera ir la url y puerto de la app
        'webhook_url'   => env('WEBHOOK_URL'), // para construir callbacks
        'redirect_uri'  => env('BITRIX_REDIRECT_URI'), // para OAuth
    ],

    'anima' => [
        // URL base de la API de Ánima Bot (ej. http://localhost:8000)
        'url'   => env('ANIMA_API_URL'),
        // Token Bearer para autenticar llamadas a la API de Ánima
        'token' => env('AUTH_TOKEN'),
        'base_url' => env('ANIMALOGIC_API_URL', 'https://animalogic.anima.bot/api'),
    ],
];
