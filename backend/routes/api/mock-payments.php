<?php

use App\Http\Controllers\Api\Payment\MockPaymentController;
use Illuminate\Support\Facades\Route;

// Mock Payments (Development only - for testing without real payment gateway subscription)
if (app()->environment(['local', 'testing']) || config('app.mock_payments_enabled')) {
    Route::prefix('v1/mock')->middleware(['api.token', 'tenant.context'])->group(function () {
        Route::post('/payments/create', [MockPaymentController::class, 'createPayment']);
        Route::post('/invoices/create-and-pay', [MockPaymentController::class, 'createInvoiceAndPay']);
        Route::get('/test-cards', [MockPaymentController::class, 'getTestCards']);
        Route::post('/webhook/charge-succeeded', [MockPaymentController::class, 'simulateChargeSucceeded']);
    });
}
