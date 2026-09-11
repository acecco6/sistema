<?php

use App\Http\Controllers\Payments\MercadoPagoAccounts\MercadoPagoOAuthCallbackController;
use App\Http\Controllers\Payments\MercadoPagoWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/integrations/mercado-pago/callback', MercadoPagoOAuthCallbackController::class)
    ->name('mercado_pago.oauth.callback');
Route::post('/webhooks/mercadopago', MercadoPagoWebhookController::class)
    ->name('webhook.mercadopago');
