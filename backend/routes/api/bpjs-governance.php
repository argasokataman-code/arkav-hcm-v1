<?php

use App\Http\Controllers\Api\HcmBpjsGovernanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/bpjs-governance')->middleware(['api.token', 'tenant.context'])->group(function (): void {
    Route::get('/reference', [HcmBpjsGovernanceController::class, 'reference']);

    Route::get('/policies', [HcmBpjsGovernanceController::class, 'indexPolicies']);
    Route::get('/policies/history', [HcmBpjsGovernanceController::class, 'policyHistory']);
    Route::post('/policies', [HcmBpjsGovernanceController::class, 'storePolicy']);
    Route::put('/policies/{policyRef}', [HcmBpjsGovernanceController::class, 'updatePolicy']);
    Route::delete('/policies/{policyRef}', [HcmBpjsGovernanceController::class, 'destroyPolicy']);

    Route::get('/employee-membership', [HcmBpjsGovernanceController::class, 'employeeMembership']);
    Route::put('/employee-membership/{userId}', [HcmBpjsGovernanceController::class, 'updateEmployeeMembership'])->whereNumber('userId');

    Route::get('/reports', [HcmBpjsGovernanceController::class, 'reports']);
    Route::get('/reports/export', [HcmBpjsGovernanceController::class, 'exportReports']);

    Route::get('/rate-baselines', [HcmBpjsGovernanceController::class, 'rateBaselines']);
    Route::put('/rate-baselines/{programCode}/{contributionParty}', [HcmBpjsGovernanceController::class, 'updateRateBaseline']);
});
