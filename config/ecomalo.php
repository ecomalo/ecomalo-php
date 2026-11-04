<?php

return [

    // the site token and webhook secret from `php artisan ecomalo:connect <code>`
    'api_key' => env('ECOMALO_API_KEY'),

    'api_secret' => env('ECOMALO_API_SECRET'),

    // optional merchant API key (the API keys page of the EcomAlo panel) for order lookup, stock levels and changes, and tracking
    'merchant_key' => env('ECOMALO_MERCHANT_KEY'),

    'base_url' => env('ECOMALO_BASE_URL', 'https://api.ecomalo.com/v1'),

    // seconds; a slow EcomAlo must never hold a buyer at checkout, so send orders from a queued job
    'timeout' => (float) env('ECOMALO_TIMEOUT', 15),

    'connect_timeout' => (float) env('ECOMALO_CONNECT_TIMEOUT', 5),

    // extra tries on a lost connection, 429 or 5xx (0.5s, 1s, 2s...)
    'retries' => (int) env('ECOMALO_RETRIES', 2),

    'webhook' => [
        // the route EcomAlo posts order and parcel updates to; give its full URL to ecomalo:connect
        'enabled' => (bool) env('ECOMALO_WEBHOOK_ENABLED', true),

        'path' => env('ECOMALO_WEBHOOK_PATH', 'ecomalo/webhook'),

        // no "web" group: no session and no CSRF token on a server-to-server call
        'middleware' => [],

        'max_age' => 300,

        // a retried delivery keeps its id; within this many minutes it is answered without firing events again (0 turns it off)
        'dedupe_minutes' => (int) env('ECOMALO_WEBHOOK_DEDUPE_MINUTES', 1440),
    ],

];
