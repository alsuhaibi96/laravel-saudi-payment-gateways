<?php

use Alsuhaibi\SaudiPayments\Http\Controllers\PaymentMethodsController;
use Alsuhaibi\SaudiPayments\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('payments/methods', PaymentMethodsController::class)
    ->name('saudi-payments.methods');

Route::post('webhooks/payments/{gateway}', WebhookController::class)
    ->where('gateway', '[a-z0-9_-]+')
    ->name('saudi-payments.webhook');
