<?php

use App\Http\Controllers\Api\HcmTaxGovernanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/tax-governance')->middleware(['api.token', 'tenant.context'])->group(function (): void {
    // Tenant PPh21 policies CRUD + lifecycle
    Route::get('/policies', [HcmTaxGovernanceController::class, 'index']);
    Route::post('/policies', [HcmTaxGovernanceController::class, 'store']);
    Route::get('/policies/{policyRef}', [HcmTaxGovernanceController::class, 'show']);
    Route::patch('/policies/{policyRef}', [HcmTaxGovernanceController::class, 'update']);
    Route::post('/policies/{policyRef}/submit', [HcmTaxGovernanceController::class, 'submit']);
    Route::post('/policies/{policyRef}/approve', [HcmTaxGovernanceController::class, 'approve']);
    Route::post('/policies/{policyRef}/publish', [HcmTaxGovernanceController::class, 'publish']);
    Route::get('/policies/{policyRef}/events', [HcmTaxGovernanceController::class, 'policyEventHistory']);

    Route::get('/governance/anomalies', [HcmTaxGovernanceController::class, 'anomalyRegistry']);
    Route::patch('/governance/anomalies/{anomalyId}/resolve', [HcmTaxGovernanceController::class, 'resolveAnomaly']);
    Route::patch('/governance/anomalies/{anomalyId}/acknowledge', [HcmTaxGovernanceController::class, 'acknowledgeAnomaly']);
    Route::get('/reports/tenant-self-audit', [HcmTaxGovernanceController::class, 'tenantSelfAuditReportEnhanced']);
    Route::get('/reports/tenant-self-audit-export', [HcmTaxGovernanceController::class, 'tenantSelfAuditReportExport']);
    Route::get('/reports/tenant-compliance-status', [HcmTaxGovernanceController::class, 'tenantComplianceStatus']);
    Route::get('/platform-billing/policies', [HcmTaxGovernanceController::class, 'platformBillingPolicies']);
    Route::post('/platform-billing/policies', [HcmTaxGovernanceController::class, 'storePlatformBillingPolicy']);
    Route::get('/platform-billing/reports', [HcmTaxGovernanceController::class, 'platformBillingReports']);
    Route::get('/platform-billing/invoices', [HcmTaxGovernanceController::class, 'platformBillingInvoices']);
    Route::get('/platform-tax-compliance/policies', [HcmTaxGovernanceController::class, 'platformTaxCompliancePolicies']);
    Route::post('/platform-tax-compliance/policies', [HcmTaxGovernanceController::class, 'storePlatformTaxCompliancePolicy']);
    Route::get('/platform-tax-compliance/reports', [HcmTaxGovernanceController::class, 'platformTaxComplianceReports']);
});
