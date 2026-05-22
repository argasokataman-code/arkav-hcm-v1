<?php

use App\Http\Controllers\Api\EmailDeliveryStatusWebhookController;
use App\Http\Controllers\Api\EmailInboundWebhookController;
use App\Http\Controllers\Api\Payment\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

// Webhooks (outside auth middleware, signature-validated instead)
Route::post('/webhooks/stripe', [PaymentWebhookController::class, 'handleStripe']);
Route::post('/webhooks/midtrans', [PaymentWebhookController::class, 'handleMidtrans']);
Route::post('/webhooks/email-inbound', [EmailInboundWebhookController::class, 'handle']);
Route::post('/webhooks/email-delivery-status', [EmailDeliveryStatusWebhookController::class, 'handle']);
