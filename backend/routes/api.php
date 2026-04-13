<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\HcmDashboardController;
use App\Http\Controllers\Api\HcmEmployeeController;
use App\Http\Controllers\Api\HcmHolidayController;
use App\Http\Controllers\Api\HcmLeaveRequestController;
use App\Http\Controllers\Api\HcmLeaveSettingController;
use App\Http\Controllers\Api\HcmOvertimeRequestController;
use App\Http\Controllers\Api\HcmOvertimeTypeController;
use App\Http\Controllers\Api\HcmPayrollItemController;
use App\Http\Controllers\Api\HcmPayrollPeriodController;
use App\Http\Controllers\Api\HcmPayrollRunController;
use App\Http\Controllers\Api\HcmPayrollPkwtCompensationController;
use App\Http\Controllers\Api\HcmPayrollThrController;
use App\Http\Controllers\Api\HcmPayrollThrBatchController;
use App\Http\Controllers\Api\HcmPayrollThrSettingsController;
use App\Http\Controllers\Api\HcmPerformanceController;
use App\Http\Controllers\Api\HcmPromotionController;
use App\Http\Controllers\Api\HcmResignationController;
use App\Http\Controllers\Api\HcmTerminationController;
use App\Http\Controllers\Api\HcmSalaryComponentController;
use App\Http\Controllers\Api\HcmShiftController;
use App\Http\Controllers\Api\HcmTicketController;
use App\Http\Controllers\Api\HcmTrainingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/identity')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('api.token')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });

    Route::middleware(['api.token', 'tenant.context'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
    });
});

Route::prefix('v1/company')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::get('/active', [CompanyController::class, 'active']);
});

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::get('/dashboard-summary', [HcmDashboardController::class, 'summary']);
    Route::get('/employee-dashboard-summary', [HcmDashboardController::class, 'employeeSummary']);

    Route::get('/employees', [HcmEmployeeController::class, 'index']);
    Route::get('/employees/export', [HcmEmployeeController::class, 'exportEmployees']);
    Route::post('/employees', [HcmEmployeeController::class, 'store']);
    Route::get('/employees/bulk-template', [HcmEmployeeController::class, 'bulkTemplate']);
    Route::post('/employees/bulk-upload', [HcmEmployeeController::class, 'bulkUpload']);
    Route::get('/employees/salary-template', [HcmEmployeeController::class, 'bulkTemplate']);
    Route::post('/employees/salary-bulk-upload', [HcmEmployeeController::class, 'bulkUpload']);
    Route::get('/employees/{id}', [HcmEmployeeController::class, 'show'])->whereNumber('id');
    Route::put('/employees/{id}', [HcmEmployeeController::class, 'update'])->whereNumber('id');
    Route::post('/employees/{id}/profile-photo', [HcmEmployeeController::class, 'uploadProfilePhoto'])->whereNumber('id');
    Route::get('/departments', [HcmEmployeeController::class, 'departments']);
    Route::get('/departments/export', [HcmEmployeeController::class, 'exportDepartments']);
    Route::post('/departments', [HcmEmployeeController::class, 'storeDepartment']);
    Route::put('/departments/{id}', [HcmEmployeeController::class, 'updateDepartment'])->whereNumber('id');
    Route::delete('/departments/{id}', [HcmEmployeeController::class, 'destroyDepartment'])->whereNumber('id');
    Route::get('/designations', [HcmEmployeeController::class, 'designations']);
    Route::get('/designations/export', [HcmEmployeeController::class, 'exportDesignations']);
    Route::post('/designations', [HcmEmployeeController::class, 'storeDesignation']);
    Route::put('/designations/{id}', [HcmEmployeeController::class, 'updateDesignation'])->whereNumber('id');
    Route::delete('/designations/{id}', [HcmEmployeeController::class, 'destroyDesignation'])->whereNumber('id');
    Route::get('/policies', [HcmEmployeeController::class, 'policies']);
    Route::get('/policies/export', [HcmEmployeeController::class, 'exportPolicies']);
    Route::post('/policies', [HcmEmployeeController::class, 'storePolicy']);
    Route::put('/policies/{id}', [HcmEmployeeController::class, 'updatePolicy'])->whereNumber('id');
    Route::delete('/policies/{id}', [HcmEmployeeController::class, 'destroyPolicy'])->whereNumber('id');

    Route::get('/attendance/admin', [AttendanceController::class, 'adminIndex']);
    Route::put('/attendance/admin/record', [AttendanceController::class, 'adminUpsertRecord']);
    Route::get('/timesheets', [AttendanceController::class, 'timesheetsIndex']);
    Route::get('/schedule-timing', [AttendanceController::class, 'scheduleTimingIndex']);
    Route::put('/schedule-timing/{userId}', [AttendanceController::class, 'scheduleTimingUpsert'])->whereNumber('userId');
    Route::delete('/schedule-timing/{userId}', [AttendanceController::class, 'scheduleTimingDestroy'])->whereNumber('userId');
    Route::get('/shifts', [HcmShiftController::class, 'index']);
    Route::post('/shifts', [HcmShiftController::class, 'store']);
    Route::put('/shifts/{id}', [HcmShiftController::class, 'update'])->whereNumber('id');
    Route::delete('/shifts/{id}', [HcmShiftController::class, 'destroy'])->whereNumber('id');
    Route::get('/salary-components', [HcmSalaryComponentController::class, 'index']);
    Route::post('/salary-components', [HcmSalaryComponentController::class, 'store']);
    Route::get('/salary-components/{id}', [HcmSalaryComponentController::class, 'show'])->whereNumber('id');
    Route::put('/salary-components/{id}', [HcmSalaryComponentController::class, 'update'])->whereNumber('id');
    Route::delete('/salary-components/{id}', [HcmSalaryComponentController::class, 'destroy'])->whereNumber('id');

    Route::get('/payroll-periods', [HcmPayrollPeriodController::class, 'index']);
    Route::get('/payroll-periods/active', [HcmPayrollPeriodController::class, 'active']);
    Route::post('/payroll-periods', [HcmPayrollPeriodController::class, 'store']);
    Route::get('/payroll-periods/{id}', [HcmPayrollPeriodController::class, 'show'])->whereNumber('id');
    Route::post('/payroll-periods/{id}/calculate-draft', [HcmPayrollPeriodController::class, 'calculateDraft'])->whereNumber('id');
    Route::get('/payroll-runs/history', [HcmPayrollRunController::class, 'history']);
    Route::get('/payroll-runs/{id}', [HcmPayrollRunController::class, 'show'])->whereNumber('id');
    Route::post('/payroll-runs/{id}/finalize', [HcmPayrollRunController::class, 'finalize'])->whereNumber('id');
    Route::post('/payroll-runs/{id}/disburse', [HcmPayrollRunController::class, 'disburse'])->whereNumber('id');
    Route::post('/payroll-runs/{id}/reset-payments', [HcmPayrollRunController::class, 'resetPayments'])->whereNumber('id');
    Route::get('/payroll/my-slip', [HcmPayrollRunController::class, 'mySlip']);
    Route::get('/payroll/my-slip-lines', [HcmPayrollRunController::class, 'mySlipLines']);
    Route::get('/payroll/my-slip-pdf', [HcmPayrollRunController::class, 'mySlipPdf']);
    Route::get('/payroll/admin-run-slips', [HcmPayrollRunController::class, 'adminRunSlips']);
    Route::get('/payroll/admin-slips', [HcmPayrollRunController::class, 'adminSlips']);
    Route::post('/payroll/send-slips', [HcmPayrollRunController::class, 'sendMonthlySlips']);
    Route::get('/payroll/my-thr-slip', [HcmPayrollThrBatchController::class, 'myThrSlip']);
    Route::get('/payroll/pkwt-compensations', [HcmPayrollPkwtCompensationController::class, 'index']);
    Route::post('/payroll/pkwt-calculate', [HcmPayrollPkwtCompensationController::class, 'calculate']);
    Route::post('/payroll/pkwt-compensations/post-payroll', [HcmPayrollPkwtCompensationController::class, 'postPayroll']);
    Route::post('/payroll/thr-calculate', [HcmPayrollThrController::class, 'calculate']);
    Route::get('/payroll/thr-settings', [HcmPayrollThrSettingsController::class, 'index']);
    Route::put('/payroll/thr-settings/{calendarYear}', [HcmPayrollThrSettingsController::class, 'upsert'])->whereNumber('calendarYear');
    Route::get('/payroll/thr-batch', [HcmPayrollThrBatchController::class, 'show']);
    Route::post('/payroll/thr-batch/generate', [HcmPayrollThrBatchController::class, 'generate']);
    Route::post('/payroll/thr-batch/disburse', [HcmPayrollThrBatchController::class, 'disburse']);
    Route::post('/payroll/thr-batch/post-payroll', [HcmPayrollThrBatchController::class, 'postPayroll']);
    Route::post('/payroll/thr-batch/send-slip', [HcmPayrollThrBatchController::class, 'sendSlip']);
    Route::get('/payroll/thr-batch/lines/{line}/slip', [HcmPayrollThrBatchController::class, 'slip'])->whereNumber('line');
    Route::get('/payroll-items', [HcmPayrollItemController::class, 'index']);
    Route::get('/payroll-items/export', [HcmPayrollItemController::class, 'export']);
    Route::post('/payroll-items', [HcmPayrollItemController::class, 'store']);
    Route::put('/payroll-items/{id}', [HcmPayrollItemController::class, 'update'])->whereNumber('id');
    Route::delete('/payroll-items/{id}', [HcmPayrollItemController::class, 'destroy'])->whereNumber('id');

    Route::get('/overtime-types', [HcmOvertimeTypeController::class, 'index']);
    Route::post('/overtime-types', [HcmOvertimeTypeController::class, 'store']);
    Route::put('/overtime-types/{id}', [HcmOvertimeTypeController::class, 'update'])->whereNumber('id');
    Route::delete('/overtime-types/{id}', [HcmOvertimeTypeController::class, 'destroy'])->whereNumber('id');
    Route::get('/attendance/me/today', [AttendanceController::class, 'meToday']);
    Route::get('/attendance/me/history', [AttendanceController::class, 'meHistory']);
    Route::get('/attendance/me/stats', [AttendanceController::class, 'meStats']);
    Route::post('/attendance/me/punch', [AttendanceController::class, 'punch']);
    Route::post('/attendance/me/break', [AttendanceController::class, 'toggleBreak']);
    Route::post('/attendance/me/correction-request', [AttendanceController::class, 'requestCorrection']);

    Route::get('/holidays', [HcmHolidayController::class, 'index']);
    Route::post('/holidays', [HcmHolidayController::class, 'store']);
    Route::post('/holidays/sync-indonesia', [HcmHolidayController::class, 'syncIndonesia']);
    Route::put('/holidays/{id}', [HcmHolidayController::class, 'update'])->whereNumber('id');
    Route::delete('/holidays/{id}', [HcmHolidayController::class, 'destroy'])->whereNumber('id');

    Route::get('/leave-type-options', [HcmLeaveRequestController::class, 'enabledLeaveTypes']);
    Route::get('/leave-requests/export', [HcmLeaveRequestController::class, 'export']);
    Route::get('/leave-requests', [HcmLeaveRequestController::class, 'index']);
    Route::post('/leave-requests', [HcmLeaveRequestController::class, 'store']);
    Route::put('/leave-requests/{id}', [HcmLeaveRequestController::class, 'update'])->whereNumber('id');
    Route::delete('/leave-requests/{id}', [HcmLeaveRequestController::class, 'destroy'])->whereNumber('id');

    Route::get('/leave-settings', [HcmLeaveSettingController::class, 'index']);
    Route::put('/leave-settings/types/{code}', [HcmLeaveSettingController::class, 'updateType'])->where('code', '[a-z_]+');
    Route::post('/leave-settings/custom-policies', [HcmLeaveSettingController::class, 'storeCustomPolicy']);
    Route::put('/leave-settings/custom-policies/{id}', [HcmLeaveSettingController::class, 'updateCustomPolicy'])->whereNumber('id');
    Route::delete('/leave-settings/custom-policies/{id}', [HcmLeaveSettingController::class, 'destroyCustomPolicy'])->whereNumber('id');

    Route::get('/overtime-requests', [HcmOvertimeRequestController::class, 'index']);
    Route::post('/overtime-requests', [HcmOvertimeRequestController::class, 'store']);
    Route::post('/overtime-requests/calculate', [HcmOvertimeRequestController::class, 'calculate']);
    Route::put('/overtime-requests/{id}', [HcmOvertimeRequestController::class, 'update'])->whereNumber('id');
    Route::delete('/overtime-requests/{id}', [HcmOvertimeRequestController::class, 'destroy'])->whereNumber('id');

    Route::get('/tickets/assignable-users', [HcmTicketController::class, 'assignableUsers']);
    Route::get('/tickets/category-options', [HcmTicketController::class, 'categoryOptions']);
    Route::get('/tickets/categories', [HcmTicketController::class, 'categories']);
    Route::post('/tickets/categories', [HcmTicketController::class, 'storeCategory']);
    Route::put('/tickets/categories/{id}', [HcmTicketController::class, 'updateCategory'])->whereNumber('id');
    Route::delete('/tickets/categories/{id}', [HcmTicketController::class, 'destroyCategory'])->whereNumber('id');
    Route::get('/tickets', [HcmTicketController::class, 'index']);
    Route::post('/tickets', [HcmTicketController::class, 'store']);
    Route::get('/tickets/{id}', [HcmTicketController::class, 'show'])->whereNumber('id');
    Route::put('/tickets/{id}', [HcmTicketController::class, 'update'])->whereNumber('id');
    Route::delete('/tickets/{id}', [HcmTicketController::class, 'destroy'])->whereNumber('id');
    Route::post('/tickets/{id}/comments', [HcmTicketController::class, 'addComment'])->whereNumber('id');
    Route::post('/tickets/{id}/attachments', [HcmTicketController::class, 'addAttachment'])->whereNumber('id');
    Route::get('/tickets/{id}/attachments/{attachmentId}/preview', [HcmTicketController::class, 'previewAttachment'])
        ->whereNumber('id')
        ->whereNumber('attachmentId');
    Route::get('/tickets/{id}/attachments/{attachmentId}/download', [HcmTicketController::class, 'downloadAttachment'])
        ->whereNumber('id')
        ->whereNumber('attachmentId');

    // Performance (Phase 1)
    Route::prefix('performance')->group(function () {
        // Goal types (list for all authenticated, mutating admin-only)
        Route::get('/goal-types', [HcmPerformanceController::class, 'goalTypes']);
        Route::post('/goal-types', [HcmPerformanceController::class, 'storeGoalType']);
        Route::put('/goal-types/{id}', [HcmPerformanceController::class, 'updateGoalType'])->whereNumber('id');
        Route::delete('/goal-types/{id}', [HcmPerformanceController::class, 'destroyGoalType'])->whereNumber('id');

        // Goals
        Route::get('/goals', [HcmPerformanceController::class, 'goals']);
        Route::post('/goals', [HcmPerformanceController::class, 'storeGoal']);
        Route::put('/goals/{id}', [HcmPerformanceController::class, 'updateGoal'])->whereNumber('id');
        Route::delete('/goals/{id}', [HcmPerformanceController::class, 'destroyGoal'])->whereNumber('id');

        // Indicator templates (admin)
        Route::get('/indicator-templates', [HcmPerformanceController::class, 'indicatorTemplates']);
        Route::post('/indicator-templates', [HcmPerformanceController::class, 'storeIndicatorTemplate']);
        Route::put('/indicator-templates/{id}', [HcmPerformanceController::class, 'updateIndicatorTemplate'])->whereNumber('id');
        Route::delete('/indicator-templates/{id}', [HcmPerformanceController::class, 'destroyIndicatorTemplate'])->whereNumber('id');
        Route::get('/indicator-templates/{id}/items', [HcmPerformanceController::class, 'indicatorItems'])->whereNumber('id');
        Route::post('/indicator-templates/{id}/items', [HcmPerformanceController::class, 'storeIndicatorItem'])->whereNumber('id');
        Route::put('/indicator-items/{itemId}', [HcmPerformanceController::class, 'updateIndicatorItem'])->whereNumber('itemId');
        Route::delete('/indicator-items/{itemId}', [HcmPerformanceController::class, 'destroyIndicatorItem'])->whereNumber('itemId');

        // Cycles (admin)
        Route::get('/cycles', [HcmPerformanceController::class, 'cycles']);
        Route::post('/cycles', [HcmPerformanceController::class, 'storeCycle']);
        Route::put('/cycles/{id}', [HcmPerformanceController::class, 'updateCycle'])->whereNumber('id');
        Route::post('/cycles/{id}/activate', [HcmPerformanceController::class, 'activateCycle'])->whereNumber('id');
        Route::post('/cycles/{id}/close', [HcmPerformanceController::class, 'closeCycle'])->whereNumber('id');

        // Reviews
        Route::get('/reviews', [HcmPerformanceController::class, 'reviews']);
        Route::post('/reviews', [HcmPerformanceController::class, 'createReview']);
        Route::get('/reviews/{id}', [HcmPerformanceController::class, 'showReview'])->whereNumber('id');
        Route::put('/reviews/{id}', [HcmPerformanceController::class, 'updateReviewSelf'])->whereNumber('id');
        Route::post('/reviews/{id}/submit', [HcmPerformanceController::class, 'submitReview'])->whereNumber('id');
        Route::put('/reviews/{id}/manager', [HcmPerformanceController::class, 'managerUpdate'])->whereNumber('id');
        Route::post('/reviews/{id}/manager-complete', [HcmPerformanceController::class, 'managerComplete'])->whereNumber('id');
        Route::put('/reviews/{id}/final', [HcmPerformanceController::class, 'adminFinalUpdate'])->whereNumber('id');
        Route::post('/reviews/{id}/finalize', [HcmPerformanceController::class, 'finalize'])->whereNumber('id');
    });

    // Training (Phase 1)
    Route::prefix('training')->group(function () {
        // Training types (list for all authenticated, mutating admin-only)
        Route::get('/types', [HcmTrainingController::class, 'types']);
        Route::post('/types', [HcmTrainingController::class, 'storeType']);
        Route::put('/types/{id}', [HcmTrainingController::class, 'updateType'])->whereNumber('id');
        Route::delete('/types/{id}', [HcmTrainingController::class, 'destroyType'])->whereNumber('id');

        // Trainings (admin-only phase 1)
        Route::get('/trainings', [HcmTrainingController::class, 'trainings']);
        Route::post('/trainings', [HcmTrainingController::class, 'storeTraining']);
        Route::put('/trainings/{id}', [HcmTrainingController::class, 'updateTraining'])->whereNumber('id');
        Route::delete('/trainings/{id}', [HcmTrainingController::class, 'destroyTraining'])->whereNumber('id');
        Route::get('/users/{userId}/trainings', [HcmTrainingController::class, 'trainingsForUser'])->whereNumber('userId');

        // Trainers (admin-only phase 1)
        Route::get('/trainers', [HcmTrainingController::class, 'trainers']);
        Route::post('/trainers', [HcmTrainingController::class, 'storeTrainer']);
        Route::put('/trainers/{id}', [HcmTrainingController::class, 'updateTrainer'])->whereNumber('id');
        Route::delete('/trainers/{id}', [HcmTrainingController::class, 'destroyTrainer'])->whereNumber('id');
    });

    // Promotion (Phase 1)
    Route::prefix('promotions')->group(function () {
        Route::get('/', [HcmPromotionController::class, 'index']);
        Route::get('/users/{userId}/promotions', [HcmPromotionController::class, 'promotionsForUser'])->whereNumber('userId');
        Route::post('/', [HcmPromotionController::class, 'store']);
        Route::get('/{id}', [HcmPromotionController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [HcmPromotionController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [HcmPromotionController::class, 'destroy'])->whereNumber('id');
    });

    Route::prefix('resignations')->group(function () {
        Route::get('/', [HcmResignationController::class, 'index']);
        Route::get('/users/{userId}/resignations', [HcmResignationController::class, 'resignationsForUser'])->whereNumber('userId');
        Route::post('/', [HcmResignationController::class, 'store']);
        Route::get('/{id}', [HcmResignationController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [HcmResignationController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [HcmResignationController::class, 'destroy'])->whereNumber('id');
    });

    Route::prefix('terminations')->group(function () {
        Route::get('/', [HcmTerminationController::class, 'index']);
        Route::get('/users/{userId}/terminations', [HcmTerminationController::class, 'terminationsForUser'])->whereNumber('userId');
        Route::post('/', [HcmTerminationController::class, 'store']);
        Route::get('/{id}', [HcmTerminationController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [HcmTerminationController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [HcmTerminationController::class, 'destroy'])->whereNumber('id');
    });
});

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'data' => [
            'status' => 'ok',
            'service' => config('app.name'),
        ],
    ]);
});
