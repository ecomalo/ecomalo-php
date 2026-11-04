<?php

use EcomAlo\Laravel\Http\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post(config('ecomalo.webhook.path', 'ecomalo/webhook'), WebhookController::class)
    ->middleware((array) config('ecomalo.webhook.middleware', []))
    ->name('ecomalo.webhook');
