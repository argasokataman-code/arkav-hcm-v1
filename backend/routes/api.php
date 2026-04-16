<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\HcmActivityController;
use App\Http\Controllers\Api\HcmDashboardController;
use App\Http\Controllers\Api\HcmEmailSettingsController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\DomainController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\MockPaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReportSnapshotController;
use App\Http\Controllers\Api\ReconciliationExportController;
use App\Http\Controllers\Api\BulkPaymentImportController;
use App\Http\Controllers\Api\SuperAdminDashboardController;
use App\Http\Controllers\Api\HcmEmployeeController;
use App\Http\Controllers\Api\HcmHolidayController;
use App\Http\Controllers\Api\HcmLeaveRequestController;
use App\Http\Controllers\Api\HcmLeaveTypeController;
use App\Http\Controllers\Api\HcmLeaveSettingController;
use App\Http\Controllers\Api\HcmOvertimeRequestController;
use App\Http\Controllers\Api\HcmOvertimeTypeController;
use App\Http\Controllers\Api\HcmPayrollItemController;
use App\Http\Controllers\Api\HcmPayrollItemAssignmentController;
use App\Http\Controllers\Api\HcmPayrollPeriodController;
use App\Http\Controllers\Api\HcmPayrollRunController;
use App\Http\Controllers\Api\HcmPayrollPkwtCompensationController;
use App\Http\Controllers\Api\HcmPayrollThrController;
use App\Http\Controllers\Api\HcmPayrollThrBatchController;
use App\Http\Controllers\Api\HcmPayrollThrSettingsController;
use App\Http\Controllers\Api\HcmPerformanceController;
use App\Http\Controllers\Api\HcmAssetController;
use App\Http\Controllers\Api\HcmAssetCategoryController;
use App\Http\Controllers\Api\HcmPromotionController;
use App\Http\Controllers\Api\HcmResignationController;
use App\Http\Controllers\Api\HcmTerminationController;
use App\Http\Controllers\Api\HcmSalaryComponentController;
use App\Http\Controllers\Api\HcmShiftController;
use App\Http\Controllers\Api\HcmTicketController;
use App\Http\Controllers\Api\HcmTrainingController;
use App\Http\Controllers\Api\HcmUserManagementController;
use App\Http\Controllers\Api\WilayahLookupController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SaasCompanyBillingOverviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/identity')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('api.token')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });

    Route::middleware(['api.token', 'tenant.context'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    });
});

Route::prefix('v1/public')->group(function () {
    // Onboarding: create company + owner + subscription/invoice (public, guest)
    Route::post('/onboarding', [\App\Http\Controllers\Api\PublicOnboardingController::class, 'store'])
        ->middleware(['throttle:10,1']);
});

Route::prefix('v1/company')->middleware(['api.token'])->group(function () {
    // CRUD operations (no tenant context, as they're cross-tenant for admins)
    Route::get('/', [CompanyController::class, 'index']);
    Route::post('/', [CompanyController::class, 'store']);
    Route::put('/{id}', [CompanyController::class, 'update']);
    Route::delete('/{id}', [CompanyController::class, 'destroy']);
});

Route::prefix('v1/hcm/company')->middleware(['api.token', 'tenant.context'])->group(function () {
    // Tenant-specific company operations
    Route::get('/active', [CompanyController::class, 'active']);
});

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::get('/dashboard-summary', [HcmDashboardController::class, 'summary']);
    Route::get('/employee-dashboard-summary', [HcmDashboardController::class, 'employeeSummary']);
    Route::get('/activity-feed', [HcmActivityController::class, 'index']);
    Route::post('/activity-manual', [HcmActivityController::class, 'storeManual']);
    Route::put('/activity-manual/{id}', [HcmActivityController::class, 'updateManual'])->whereNumber('id');
    Route::delete('/activity-manual/{id}', [HcmActivityController::class, 'destroyManual'])->whereNumber('id');

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
    Route::get('/wilayah/provinces', [WilayahLookupController::class, 'provinces']);
    Route::get('/wilayah/regencies', [WilayahLookupController::class, 'regencies']);
    Route::get('/wilayah/districts', [WilayahLookupController::class, 'districts']);
    Route::get('/wilayah/villages', [WilayahLookupController::class, 'villages']);
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

    Route::get('/asset-categories', [HcmAssetCategoryController::class, 'index']);
    Route::post('/asset-categories', [HcmAssetCategoryController::class, 'store']);
    Route::put('/asset-categories/{category}', [HcmAssetCategoryController::class, 'update'])->whereNumber('category');
    Route::delete('/asset-categories/{category}', [HcmAssetCategoryController::class, 'destroy'])->whereNumber('category');

    Route::get('/assets', [HcmAssetController::class, 'index']);
    Route::post('/assets', [HcmAssetController::class, 'store']);
    Route::get('/assets/{asset}', [HcmAssetController::class, 'show'])->whereNumber('asset');
    Route::put('/assets/{asset}', [HcmAssetController::class, 'update'])->whereNumber('asset');
    Route::delete('/assets/{asset}', [HcmAssetController::class, 'destroy'])->whereNumber('asset');
    Route::post('/assets/{asset}/assign', [HcmAssetController::class, 'assign'])->whereNumber('asset');
    Route::post('/assets/{asset}/return', [HcmAssetController::class, 'returnAsset'])->whereNumber('asset');
    Route::post('/assets/{asset}/issue-report', [HcmAssetController::class, 'reportIssue'])->whereNumber('asset');
    Route::post('/assets/{asset}/attachments', [HcmAssetController::class, 'attach'])->whereNumber('asset');

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
    Route::get('/payroll/my-slip-latest-period', [HcmPayrollRunController::class, 'mySlipLatestPeriod']);
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
    Route::get('/payroll-item-assignments', [HcmPayrollItemAssignmentController::class, 'index']);
    Route::post('/payroll-item-assignments', [HcmPayrollItemAssignmentController::class, 'store']);
    Route::put('/payroll-item-assignments/{id}', [HcmPayrollItemAssignmentController::class, 'update'])->whereNumber('id');
    Route::delete('/payroll-item-assignments/{id}', [HcmPayrollItemAssignmentController::class, 'destroy'])->whereNumber('id');

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
    Route::post('/attendance/me/selfie', [AttendanceController::class, 'meSelfie']);
    Route::get('/attendance/me/selfie/status', [AttendanceController::class, 'meSelfieStatus']);
    Route::get('/attendance/admin/records/{id}/selfie/download', [AttendanceController::class, 'adminSelfieDownload'])
        ->whereNumber('id');

    Route::get('/holidays', [HcmHolidayController::class, 'index']);
    Route::post('/holidays', [HcmHolidayController::class, 'store']);
    Route::post('/holidays/sync-indonesia', [HcmHolidayController::class, 'syncIndonesia']);
    Route::put('/holidays/{id}', [HcmHolidayController::class, 'update'])->whereNumber('id');
    Route::delete('/holidays/{id}', [HcmHolidayController::class, 'destroy'])->whereNumber('id');

    Route::get('/leave-type-options', [HcmLeaveRequestController::class, 'enabledLeaveTypes']);
    Route::get('/leave-types', [HcmLeaveTypeController::class, 'index']);
    Route::post('/leave-types', [HcmLeaveTypeController::class, 'store']);
    Route::put('/leave-types/{id}', [HcmLeaveTypeController::class, 'update'])->whereNumber('id');
    Route::delete('/leave-types/{id}', [HcmLeaveTypeController::class, 'destroy'])->whereNumber('id');
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
    Route::get('/email-settings/mailtrap-status', [HcmEmailSettingsController::class, 'mailtrapStatus']);

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

    // Tenant billing checkout (owner / tenant admin)
    Route::post('/billing/checkout', [\App\Http\Controllers\Api\HcmSubscriptionCheckoutController::class, 'checkout']);

    // Tenant invoices (my billing)
    Route::get('/billing/invoices', [\App\Http\Controllers\Api\HcmCompanyInvoiceController::class, 'index']);
    Route::get('/billing/invoices/{id}', [\App\Http\Controllers\Api\HcmCompanyInvoiceController::class, 'show'])->whereNumber('id');
    Route::get('/billing/invoices/{id}/download', [\App\Http\Controllers\Api\HcmCompanyInvoiceController::class, 'download'])->whereNumber('id');
    // Dev-only mock payment flow (keeps UX testable before gateway is integrated)
    Route::post('/billing/invoices/{id}/mock-pay', [\App\Http\Controllers\Api\HcmCompanyInvoiceController::class, 'mockPay'])->whereNumber('id');

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

    // User Management
    Route::prefix('user-management')->group(function () {
        Route::get('/users', [HcmUserManagementController::class, 'users']);
        Route::get('/users/export', [HcmUserManagementController::class, 'usersExport']);
        Route::get('/users/{id}', [HcmUserManagementController::class, 'userDetail'])->whereNumber('id');
        Route::post('/users', [HcmUserManagementController::class, 'createUser']);
        Route::put('/users/{id}', [HcmUserManagementController::class, 'updateUser'])->whereNumber('id');
        Route::delete('/users/{id}', [HcmUserManagementController::class, 'deleteUser'])->whereNumber('id');

        Route::get('/roles', [HcmUserManagementController::class, 'roles']);
        Route::post('/roles', [HcmUserManagementController::class, 'createRole']);
        Route::put('/roles/{id}', [HcmUserManagementController::class, 'updateRole'])->whereNumber('id');
        Route::delete('/roles/{id}', [HcmUserManagementController::class, 'deleteRole'])->whereNumber('id');

        Route::get('/permissions', [HcmUserManagementController::class, 'permissions']);
        Route::post('/roles/{id}/permissions:sync', [HcmUserManagementController::class, 'syncRolePermissions'])->whereNumber('id');

        Route::get('/users/{id}/roles', [HcmUserManagementController::class, 'userRoles'])->whereNumber('id');
        Route::post('/users/{id}/roles', [HcmUserManagementController::class, 'assignUserRole'])->whereNumber('id');
        Route::delete('/users/{id}/roles/{assignmentId}', [HcmUserManagementController::class, 'revokeUserRole'])
            ->whereNumber('id')
            ->whereNumber('assignmentId');
    });

    // Settings Management
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index']); // Get all settings by group query param
        Route::post('/', [SettingsController::class, 'store']); // Save settings for a group
        Route::post('/upload', [SettingsController::class, 'upload']); // Upload branding file and persist setting
        Route::get('/{key}', [SettingsController::class, 'show']); // Get single setting by key
        Route::put('/{key}', [SettingsController::class, 'update']); // Update single setting
        Route::delete('/{key}', [SettingsController::class, 'destroy']); // Delete single setting
    });

    // Reporting - Snapshots
    Route::prefix('reports')->group(function () {
        Route::prefix('snapshots')->group(function () {
            Route::post('/', [ReportSnapshotController::class, 'generate']);
            Route::get('/', [ReportSnapshotController::class, 'list']);
            Route::get('/{id}', [ReportSnapshotController::class, 'show'])->whereNumber('id');
            Route::post('/{id}/export', [ReportSnapshotController::class, 'export'])->whereNumber('id');
        });
    });
});

Route::prefix('v1/reconciliation')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::post('/exports', [ReconciliationExportController::class, 'store']);
    Route::get('/exports', [ReconciliationExportController::class, 'index']);
    Route::get('/exports/{id}/download', [ReconciliationExportController::class, 'download'])->whereNumber('id');
});

// SaaS Packages Routes
Route::prefix('v1/saas')->middleware(['api.token'])->group(function () {
    // Packages (public listing, admin CRUD)
    Route::get('/packages', [PackageController::class, 'index']);
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
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy']);

    // Payments
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::put('/payments/{payment}/verify', [PaymentController::class, 'verify']);
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy']);
    Route::post('/payments/bulk-upload', [BulkPaymentImportController::class, 'upload']);

    // Reports
    Route::get('/reports/revenue', [ReportController::class, 'revenue']);
    Route::get('/reports/aging', [ReportController::class, 'aging']);
    Route::get('/reports/churn', [ReportController::class, 'churn']);

    // Invoice Actions
    Route::post('/invoices/{invoice}/send-email', [InvoiceController::class, 'sendEmail']);

    // Super Admin Dashboard
    Route::get('/dashboard/kpi', [SuperAdminDashboardController::class, 'getKpi']);
    Route::get('/dashboard/kpi/{metricKey}', [SuperAdminDashboardController::class, 'getMetricTrend']);
    Route::get('/dashboard/companies', [SuperAdminDashboardController::class, 'getCompanies']);
    Route::get('/dashboard/companies/top-performers', [SuperAdminDashboardController::class, 'getTopCompanies']);
    Route::get('/dashboard/companies/{company}/details', [SuperAdminDashboardController::class, 'getCompanyDetails']);
    Route::get('/dashboard/users', [SuperAdminDashboardController::class, 'getUserStats']);
    Route::get('/dashboard/revenue/monthly', [SuperAdminDashboardController::class, 'getMonthlytRevenue']);
    Route::get('/dashboard/revenue/by-plan', [SuperAdminDashboardController::class, 'getRevenueByPlan']);
    Route::get('/dashboard/subscriptions/status', [SuperAdminDashboardController::class, 'getSubscriptionStatus']);
    Route::get('/dashboard/audit-logs', [SuperAdminDashboardController::class, 'getAuditLogs']);

    // Billing overview (companies)
    Route::get('/companies/billing-overview', [SaasCompanyBillingOverviewController::class, 'index']);
});

// Mock Payments (Development only - for testing without Stripe/Xendit subscription)
if (app()->isLocal() || config('app.mock_payments_enabled')) {
    Route::prefix('v1/mock')->middleware(['api.token', 'tenant.context'])->group(function () {
        Route::post('/payments/create', [MockPaymentController::class, 'createPayment']);
        Route::post('/invoices/create-and-pay', [MockPaymentController::class, 'createInvoiceAndPay']);
        Route::get('/test-cards', [MockPaymentController::class, 'getTestCards']);
        Route::post('/webhook/charge-succeeded', [MockPaymentController::class, 'simulateChargeSucceeded']);
    });
}

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'data' => [
            'status' => 'ok',
            'service' => config('app.name'),
        ],
    ]);
});

// Webhooks (outside auth middleware, signature-validated instead)
Route::post('/webhooks/stripe', [\App\Http\Controllers\Api\PaymentWebhookController::class, 'handleStripe']);
Route::post('/webhooks/xendit', [\App\Http\Controllers\Api\PaymentWebhookController::class, 'handleXendit']);
