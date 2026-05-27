<?php

use App\Http\Controllers\Api\Billing\HcmSubscriptionCheckoutController;
use App\Http\Controllers\Api\Billing\HcmSubscriptionChangeController;
use App\Http\Controllers\Api\Billing\HcmCompanyInvoiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context'])->group(function () {
    // Tenant billing checkout
    Route::post('/billing/checkout', [HcmSubscriptionCheckoutController::class, 'checkout']);
    Route::post('/billing/addons/checkout', [HcmSubscriptionCheckoutController::class, 'checkoutAddon']);

    // Tenant-initiated subscription plan change
    Route::post('/subscriptions/preview-change', [HcmSubscriptionChangeController::class, 'preview']);
    Route::post('/subscriptions/change-plan', [HcmSubscriptionChangeController::class, 'changePlan']);
    Route::post('/subscriptions/cancel-change', [HcmSubscriptionChangeController::class, 'cancelChange']);
    Route::get('/subscriptions/change-requests', [HcmSubscriptionChangeController::class, 'index']);
    Route::post('/subscriptions/change-requests/{id}/activate-early', [HcmSubscriptionChangeController::class, 'activateEarly']);

    // Tenant invoices (my billing)
    Route::get('/billing/invoices', [HcmCompanyInvoiceController::class, 'index']);
    Route::get('/billing/invoices/{id}', [HcmCompanyInvoiceController::class, 'show']);
    Route::get('/billing/invoices/{id}/download', [HcmCompanyInvoiceController::class, 'download']);
    Route::post('/billing/invoices/{id}/mock-hosted-checkout', [HcmCompanyInvoiceController::class, 'mockHostedCheckout']);
    Route::post('/billing/invoices/{id}/mock-pay', [HcmCompanyInvoiceController::class, 'mockPay']);
    Route::post('/billing/invoices/{id}/sync-payment-status', [HcmCompanyInvoiceController::class, 'syncPaymentStatus']);
});
