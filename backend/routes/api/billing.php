<?php

use App\Http\Controllers\Api\Billing\HcmCompanyInvoiceController;
use App\Http\Controllers\Api\Billing\HcmSubscriptionChangeController;
use App\Http\Controllers\Api\Billing\HcmSubscriptionCheckoutController;
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

    // Current subscription for checkout success state
    Route::get('/subscriptions/current', function (\Illuminate\Http\Request $request) {
        $companyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($companyId <= 0) {
            return response()->json(['success' => false, 'error' => ['code' => 'TENANT_CONTEXT_REQUIRED']], 422);
        }
        $subscription = \App\Models\Subscription::with('package')
            ->where('company_id', $companyId)
            ->latest('id')
            ->first();
        if (!$subscription) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND']], 404);
        }
        $pkg = $subscription->package;
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'plan_code' => $subscription->plan_code,
                'billing_cycle' => $subscription->billing_cycle,
                'amount' => $subscription->amount,
                'package' => $pkg ? [
                    'id' => $pkg->uuid,
                    'uuid' => $pkg->uuid,
                    'code' => $pkg->code,
                    'name' => $pkg->name,
                    'monthly_price' => $pkg->monthly_price,
                    'yearly_price' => $pkg->yearly_price,
                ] : null,
            ],
        ]);
    });

    // Tenant invoices (my billing)
    Route::get('/billing/invoices', [HcmCompanyInvoiceController::class, 'index']);
    Route::get('/billing/invoices/{id}', [HcmCompanyInvoiceController::class, 'show']);
    Route::get('/billing/invoices/{id}/download', [HcmCompanyInvoiceController::class, 'download']);
    Route::post('/billing/invoices/{id}/mock-hosted-checkout', [HcmCompanyInvoiceController::class, 'mockHostedCheckout']);
    Route::post('/billing/invoices/{id}/mock-pay', [HcmCompanyInvoiceController::class, 'mockPay']);
    Route::post('/billing/invoices/{id}/sync-payment-status', [HcmCompanyInvoiceController::class, 'syncPaymentStatus']);
});
