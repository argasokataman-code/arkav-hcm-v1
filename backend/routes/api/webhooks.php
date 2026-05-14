<?php

use App\Http\Controllers\Api\Payment\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

// Webhooks (outside auth middleware, signature-validated instead)
Route::post('/webhooks/stripe', [PaymentWebhookController::class, 'handleStripe']);
Route::post('/webhooks/xendit', [PaymentWebhookController::class, 'handleXendit']);
