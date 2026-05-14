<?php

use App\Http\Controllers\Api\Saas\PackageController;
use App\Http\Controllers\Api\Saas\SubscriptionController;
use App\Http\Controllers\Api\Billing\HcmSubscriptionChangeController;
use App\Http\Controllers\Api\Saas\TransactionController;
use App\Http\Controllers\Api\Billing\InvoiceController;
use App\Http\Controllers\Api\Payment\PaymentController;
use App\Http\Controllers\Api\Saas\SuperAdminDashboardController;
use App\Http\Controllers\Api\Reports\ReportController;
use App\Http\Controllers\Api\Saas\SaasCompanyBillingOverviewController;
use App\Http\Controllers\Api\TaxGovernance\PlatformTaxSummaryController;
use App\Http\Controllers\Api\Domain\DomainController; // Added this line for completeness
use App\Http\Controllers\Api\Saas\BulkPaymentImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/saas')->middleware(['api.token'])->group(function () {
    // Packages (public listing, admin CRUD)
    Route::get('/packages', [PackageController::class, 'index']);
    Route::get('/packages/feature-catalog', [PackageController::class, 'featureCatalog']);
    Route::get('/packages/feature-catalog/healthcheck', [PackageController::class, 'featureCatalogHealthcheck']);
    Route::get('/packages/check-compliance', [PackageController::class, 'checkCompliance']);
    Route::get('/packages/{package}', [PackageController::class, 'show']);
    Route::post('/packages', [PackageController::class, 'store']);
    Route::put('/packages/{package}', [PackageController::class, 'update']);
    Route::delete('/packages/{package}', [PackageController::class, 'destroy']);

    // Package Add-ons
    Route::get('/package-addons', [PackageController::class, 'addons']);
    Route::get('/package-addons/{addon}', [PackageController::class, 'showAddon'])->whereNumber('addon');
    Route::post('/package-addons', [PackageController::class, 'storeAddon']);
    Route::put('/package-addons/{addon}', [PackageController::class, 'updateAddon'])->whereNumber('addon');
    Route::delete('/package-addons/{addon}', [PackageController::class, 'destroyAddon'])->whereNumber('addon');

    // Package Features
    Route::get('/packages/{package}/features', [PackageController::class, 'getFeatures']);
    Route::post('/packages/{package}/features', [PackageController::class, 'addFeature']);
    Route::put('/packages/features/{feature}', [PackageController::class, 'updateFeature']);
    Route::delete('/packages/features/{feature}', [PackageController::class, 'deleteFeature']);

    // Subscriptions
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::post('/subscriptions', [SubscriptionController::class, 'store']);
    Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show']);
    Route::put('/subscriptions/{subscription}', [SubscriptionController::class, 'update']);
    Route::delete('/subscriptions/{subscription}', [SubscriptionController::class, 'destroy']);
    Route::post('/subscriptions/{subscription}/renew', [SubscriptionController::class, 'renew']);

    // Reports (keep controller-level RBAC response contract: AUTH_FORBIDDEN)
    Route::get('/reports/revenue', [ReportController::class, 'revenue']);
    Route::get('/reports/aging', [ReportController::class, 'aging']);
    Route::get('/reports/churn', [ReportController::class, 'churn']);

    // Global Admin Only Routes
    Route::middleware('hcm.api.global-admin')->group(function () {
        // Tenant subscription change requests — super-admin approval
        Route::get('/subscription-change-requests', [HcmSubscriptionChangeController::class, 'listAllForAdmin']);
        Route::post('/subscription-change-requests/{id}/approve', [HcmSubscriptionChangeController::class, 'approve']);
        Route::post('/subscription-change-requests/{id}/reject', [HcmSubscriptionChangeController::class, 'reject']);

        // Purchase Transactions
        Route::get('/transactions/export', [TransactionController::class, 'export']);
        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::post('/transactions', [TransactionController::class, 'store']);
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->whereNumber('transaction');
        Route::put('/transactions/{transaction}', [TransactionController::class, 'update'])->whereNumber('transaction');

        // Custom Domains
        Route::get('/domains', [DomainController::class, 'index']);
        Route::post('/domains', [DomainController::class, 'store']);
        Route::get('/domains/{domain}', [DomainController::class, 'show']);
        Route::put('/domains/{domain}', [DomainController::class, 'update']);
        Route::delete('/domains/{domain}', [DomainController::class, 'destroy']);
        Route::post('/domains/{domain}/verify', [DomainController::class, 'verify']);
        Route::get('/domains/{domain}/verification-details', [DomainController::class, 'verificationDetails']);

        // Invoices
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::put('/invoices/{invoice}', [InvoiceController::class, 'update']);
        Route::put('/invoices/{invoice}/send', [InvoiceController::class, 'markAsSent']);
        Route::put('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markAsPaid']);
        Route::get('/invoices/{invoice}/pdf/preview', [InvoiceController::class, 'previewPdf']);
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);
        Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy']);
        Route::post('/invoices/{invoice}/send-email', [InvoiceController::class, 'sendEmail']);

        // Payments
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::post('/payments', [PaymentController::class, 'store']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);
        Route::put('/payments/{payment}/verify', [PaymentController::class, 'verify']);
        Route::delete('/payments/{payment}', [PaymentController::class, 'destroy']);
        Route::post('/payments/bulk-upload', [BulkPaymentImportController::class, 'upload']);

        // Billing overview (companies)
        Route::get('/companies/billing-overview', [SaasCompanyBillingOverviewController::class, 'index']);

        // Platform Tax Reporting — SPT PPN / PPh 23 (Global Super Admin only)
        Route::prefix('/tax')->group(function () {
            Route::get('/active-ppn-rate', [PlatformTaxSummaryController::class, 'activePpnRate']);
            Route::get('/dashboard', [PlatformTaxSummaryController::class, 'dashboard']);
            Route::get('/dashboard/export', [PlatformTaxSummaryController::class, 'exportDashboard']);
            Route::get('/spt-ppn', [PlatformTaxSummaryController::class, 'sptPpn']);
            Route::get('/spt-ppn/export', [PlatformTaxSummaryController::class, 'exportSptPpn']);
            Route::get('/spt-pph23', [PlatformTaxSummaryController::class, 'sptPph23']);
            Route::get('/spt-pph23/export', [PlatformTaxSummaryController::class, 'exportSptPph23']);
            Route::get('/spt-pph-badan', [PlatformTaxSummaryController::class, 'sptPphBadan']);
            Route::get('/spt-pph-badan/export', [PlatformTaxSummaryController::class, 'exportSptPphBadan']);
        });

        // Super Admin Dashboard
        Route::prefix('/dashboard')->group(function () {
            Route::get('/kpi', [SuperAdminDashboardController::class, 'getKpi']);
            Route::get('/kpi/{metricKey}', [SuperAdminDashboardController::class, 'getMetricTrend']);
            Route::get('/companies', [SuperAdminDashboardController::class, 'getCompanies']);
            Route::get('/companies/top-performers', [SuperAdminDashboardController::class, 'getTopCompanies']);
            Route::get('/companies/{company}/details', [SuperAdminDashboardController::class, 'getCompanyDetails']);
            Route::get('/users', [SuperAdminDashboardController::class, 'getUserStats']);
            Route::get('/users/retention', [SuperAdminDashboardController::class, 'getUserRetention']);
            Route::get('/revenue/monthly', [SuperAdminDashboardController::class, 'getMonthlytRevenue']);
            Route::get('/revenue/forecast', [SuperAdminDashboardController::class, 'getRevenueForecast']);
            Route::get('/revenue/by-plan', [SuperAdminDashboardController::class, 'getRevenueByPlan']);
            Route::get('/subscriptions/status', [SuperAdminDashboardController::class, 'getSubscriptionStatus']);
            Route::get('/subscriptions/health', [SuperAdminDashboardController::class, 'getSubscriptionHealth']);
            Route::get('/reports/custom', [SuperAdminDashboardController::class, 'getCustomReport']);
            Route::get('/audit-logs', [SuperAdminDashboardController::class, 'getAuditLogs']);
            Route::get('/audit-logs/{auditLog}', [SuperAdminDashboardController::class, 'getAuditLogDetail']);
        });
    });
});
