<?php

use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\EmailInboundWebhookController;
use App\Http\Controllers\Api\EmailDeliveryStatusWebhookController;
use Illuminate\Support\Facades\Route;

// Webhooks (outside auth middleware, signature-validated instead)
Route::post('/webhooks/stripe', [PaymentWebhookController::class, 'handleStripe']);
Route::post('/webhooks/xendit', [PaymentWebhookController::class, 'handleXendit']);
Route::post('/webhooks/email-inbound', [EmailInboundWebhookController::class, 'handle'])->middleware('throttle:60,1');
Route::post('/webhooks/email-delivery-status', [EmailDeliveryStatusWebhookController::class, 'handle'])->middleware('throttle:60,1');
