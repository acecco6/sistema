<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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


    'mercadopago' => [
        'access_token' => env(
            'MERCADO_PAGO_ACCESS_TOKEN'
        ),

        'public_key' => env(
            'MERCADO_PAGO_PUBLIC_KEY'
        ),

        'webhook_secret' => env(
            'MERCADO_PAGO_WEBHOOK_SECRET'
        ),

        'webhook_url' => env(
            'MERCADO_PAGO_WEBHOOK_URL'
        ),

        'success_url' => env(
            'MERCADO_PAGO_SUCCESS_URL'
        ),

        'pending_url' => env(
            'MERCADO_PAGO_PENDING_URL'
        ),

        'failure_url' => env(
            'MERCADO_PAGO_FAILURE_URL'
        ),
    ],

];
