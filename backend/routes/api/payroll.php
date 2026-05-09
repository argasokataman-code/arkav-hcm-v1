<?php

use App\Http\Controllers\Api\HcmPayrollPeriodController;
use App\Http\Controllers\Api\HcmPayrollRunController;
use App\Http\Controllers\Api\HcmPayrollWorkArrangementController;
use App\Http\Controllers\Api\HcmPayrollItemController;
use App\Http\Controllers\Api\HcmPayrollItemAssignmentController;
use App\Http\Controllers\Api\HcmPayrollPkwtCompensationController;
use App\Http\Controllers\Api\HcmPayrollThrController;
use App\Http\Controllers\Api\HcmPayrollThrBatchController;
use App\Http\Controllers\Api\HcmPayrollThrSettingsController;
use App\Http\Controllers\Api\HcmPayrollSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context', 'hcm.api.feature:payroll'])->group(function (): void {
    // Payroll Periods
    Route::get('/payroll-periods', [HcmPayrollPeriodController::class, 'index']);
    Route::get('/payroll-periods/active', [HcmPayrollPeriodController::class, 'active']);
    Route::post('/payroll-periods', [HcmPayrollPeriodController::class, 'store']);
    Route::get('/payroll-periods/{id}', [HcmPayrollPeriodController::class, 'show'])->whereNumber('id');
    Route::post('/payroll-periods/{id}/calculate-draft', [HcmPayrollPeriodController::class, 'calculateDraft'])->whereNumber('id');

    // Payroll Runs
    Route::get('/payroll-runs/history', [HcmPayrollRunController::class, 'history']);
    Route::get('/payroll-runs/{id}', [HcmPayrollRunController::class, 'show'])->whereNumber('id');
    Route::post('/payroll-runs/{id}/finalize', [HcmPayrollRunController::class, 'finalize'])->whereNumber('id');
    Route::post('/payroll-runs/{id}/void', [HcmPayrollRunController::class, 'void'])->whereNumber('id');
    Route::post('/payroll-runs/{id}/mock-hosted-checkout', [HcmPayrollRunController::class, 'startMockHostedCheckout'])->whereNumber('id');
    Route::post('/payroll-runs/{id}/mock-hosted-checkout/confirm', [HcmPayrollRunController::class, 'confirmMockHostedCheckout'])->whereNumber('id');
    Route::post('/payroll-runs/{id}/disburse', [HcmPayrollRunController::class, 'disburse'])->whereNumber('id');
    Route::post('/payroll-runs/{id}/reset-payments', [HcmPayrollRunController::class, 'resetPayments'])->whereNumber('id');

    // Work Profiles & Arrangements
    Route::get('/payroll/work-profiles', [HcmPayrollWorkArrangementController::class, 'profiles']);
    Route::post('/payroll/work-profiles', [HcmPayrollWorkArrangementController::class, 'storeProfile']);
    Route::put('/payroll/work-profiles/{id}', [HcmPayrollWorkArrangementController::class, 'updateProfile'])->whereNumber('id');
    Route::get('/payroll/work-arrangements', [HcmPayrollWorkArrangementController::class, 'arrangements']);
    Route::post('/payroll/work-arrangements', [HcmPayrollWorkArrangementController::class, 'storeArrangement']);
    Route::put('/payroll/work-arrangements/{id}', [HcmPayrollWorkArrangementController::class, 'updateArrangement'])->whereNumber('id');

    // Payslips
    Route::get('/payroll/my-slip-latest-period', [HcmPayrollRunController::class, 'mySlipLatestPeriod']);
    Route::get('/payroll/my-slip', [HcmPayrollRunController::class, 'mySlip']);
    Route::get('/payroll/my-slip-lines', [HcmPayrollRunController::class, 'mySlipLines']);
    Route::get('/payroll/my-slip-pdf', [HcmPayrollRunController::class, 'mySlipPdf']);
    Route::get('/payroll/admin-run-slips', [HcmPayrollRunController::class, 'adminRunSlips']);
    Route::get('/payroll/admin-slips', [HcmPayrollRunController::class, 'adminSlips']);
    Route::post('/payroll/send-slips', [HcmPayrollRunController::class, 'sendMonthlySlips']);

    // THR (Bonus)
    Route::get('/payroll/my-thr-slip', [HcmPayrollThrBatchController::class, 'myThrSlip']);
    Route::post('/payroll/thr-calculate', [HcmPayrollThrController::class, 'calculate']);
    Route::get('/payroll/thr-settings', [HcmPayrollThrSettingsController::class, 'index']);
    Route::put('/payroll/thr-settings/{calendarYear}', [HcmPayrollThrSettingsController::class, 'upsert'])->whereNumber('calendarYear');
    Route::get('/payroll/thr-batch', [HcmPayrollThrBatchController::class, 'show']);
    Route::post('/payroll/thr-batch/generate', [HcmPayrollThrBatchController::class, 'generate']);
    Route::post('/payroll/thr-batch/disburse', [HcmPayrollThrBatchController::class, 'disburse']);
    Route::post('/payroll/thr-batch/post-payroll', [HcmPayrollThrBatchController::class, 'postPayroll']);
    Route::post('/payroll/thr-batch/send-slip', [HcmPayrollThrBatchController::class, 'sendSlip']);
    Route::get('/payroll/thr-batch/lines/{line}/slip', [HcmPayrollThrBatchController::class, 'slip'])->whereNumber('line');

    // PKWT Compensation
    Route::get('/payroll/pkwt-compensations', [HcmPayrollPkwtCompensationController::class, 'index']);
    Route::post('/payroll/pkwt-calculate', [HcmPayrollPkwtCompensationController::class, 'calculate']);
    Route::post('/payroll/pkwt-compensations/post-payroll', [HcmPayrollPkwtCompensationController::class, 'postPayroll']);

    // Payroll Settings
    Route::get('/payroll/settings', [HcmPayrollSettingsController::class, 'show']);
    Route::put('/payroll/settings', [HcmPayrollSettingsController::class, 'update']);
    Route::get('/payroll/settings/history', [HcmPayrollSettingsController::class, 'history']);

    // Payroll Items
    Route::get('/payroll-items', [HcmPayrollItemController::class, 'index']);
    Route::get('/payroll-items/export', [HcmPayrollItemController::class, 'export']);
    Route::post('/payroll-items', [HcmPayrollItemController::class, 'store']);
    Route::put('/payroll-items/{id}', [HcmPayrollItemController::class, 'update'])->whereNumber('id');
    Route::delete('/payroll-items/{id}', [HcmPayrollItemController::class, 'destroy'])->whereNumber('id');

    // Payroll Item Assignments
    Route::get('/payroll-item-assignments', [HcmPayrollItemAssignmentController::class, 'index']);
    Route::post('/payroll-item-assignments', [HcmPayrollItemAssignmentController::class, 'store']);
    Route::put('/payroll-item-assignments/{id}', [HcmPayrollItemAssignmentController::class, 'update'])->whereNumber('id');
    Route::delete('/payroll-item-assignments/{id}', [HcmPayrollItemAssignmentController::class, 'destroy'])->whereNumber('id');
});
