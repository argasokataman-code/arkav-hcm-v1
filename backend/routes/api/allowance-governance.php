<?php

use App\Http\Controllers\Api\HcmEmployeeAllowanceGovernanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/allowance-governance')->middleware(['api.token', 'tenant.context'])->group(function (): void {
    Route::get('/reference', [HcmEmployeeAllowanceGovernanceController::class, 'reference']);

    Route::get('/policies', [HcmEmployeeAllowanceGovernanceController::class, 'policies']);
    Route::get('/policies/history', [HcmEmployeeAllowanceGovernanceController::class, 'policyHistory']);
    Route::post('/policies', [HcmEmployeeAllowanceGovernanceController::class, 'storePolicy']);
    Route::patch('/policies/{policyRef}', [HcmEmployeeAllowanceGovernanceController::class, 'updatePolicy']);
    Route::post('/policies/{policyRef}/activate', [HcmEmployeeAllowanceGovernanceController::class, 'activatePolicy']);

    Route::get('/assignments', [HcmEmployeeAllowanceGovernanceController::class, 'assignments']);
    Route::post('/assignments', [HcmEmployeeAllowanceGovernanceController::class, 'storeAssignment']);
    Route::patch('/assignments/{assignmentRef}', [HcmEmployeeAllowanceGovernanceController::class, 'updateAssignment']);

    Route::get('/reports/compliance', [HcmEmployeeAllowanceGovernanceController::class, 'reports']);
    Route::get('/reports/compliance/export', [HcmEmployeeAllowanceGovernanceController::class, 'exportReports']);
});
