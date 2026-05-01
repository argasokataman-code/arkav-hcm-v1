<?php

use App\Http\Controllers\Api\HcmLeaveRequestController;
use App\Http\Controllers\Api\HcmLeaveTypeController;
use App\Http\Controllers\Api\HcmLeaveSettingController;
use App\Http\Controllers\Api\HcmHolidayController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context', 'hcm.api.feature:leave_management'])->group(function () {
    // Leave Type Options
    Route::get('/leave-type-options', [HcmLeaveRequestController::class, 'enabledLeaveTypes']);

    // Leave Types
    Route::get('/leave-types', [HcmLeaveTypeController::class, 'index']);
    Route::post('/leave-types', [HcmLeaveTypeController::class, 'store']);
    Route::put('/leave-types/{id}', [HcmLeaveTypeController::class, 'update'])->whereNumber('id');
    Route::delete('/leave-types/{id}', [HcmLeaveTypeController::class, 'destroy'])->whereNumber('id');

    // Leave Requests
    Route::get('/leave-requests/export', [HcmLeaveRequestController::class, 'export']);
    Route::get('/leave-requests', [HcmLeaveRequestController::class, 'index']);
    Route::get('/employee-leave-balance', [HcmLeaveRequestController::class, 'getEmployeeBalance']);
    Route::post('/leave-requests', [HcmLeaveRequestController::class, 'store']);
    Route::put('/leave-requests/{id}', [HcmLeaveRequestController::class, 'update'])->whereNumber('id');
    Route::delete('/leave-requests/{id}', [HcmLeaveRequestController::class, 'destroy'])->whereNumber('id');

    // Leave Settings
    Route::get('/leave-settings', [HcmLeaveSettingController::class, 'index']);
    Route::put('/leave-settings/types/{code}', [HcmLeaveSettingController::class, 'updateType'])->where('code', '[a-z_]+');
    Route::post('/leave-settings/custom-policies', [HcmLeaveSettingController::class, 'storeCustomPolicy']);
    Route::put('/leave-settings/custom-policies/{id}', [HcmLeaveSettingController::class, 'updateCustomPolicy'])->whereNumber('id');
    Route::delete('/leave-settings/custom-policies/{id}', [HcmLeaveSettingController::class, 'destroyCustomPolicy'])->whereNumber('id');
});

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context', 'hcm.api.feature:holiday_calendar'])->group(function () {
    // Holidays
    Route::get('/holidays', [HcmHolidayController::class, 'index']);
    Route::post('/holidays', [HcmHolidayController::class, 'store']);
    Route::post('/holidays/sync-indonesia', [HcmHolidayController::class, 'syncIndonesia']);
    Route::put('/holidays/{id}', [HcmHolidayController::class, 'update'])->whereNumber('id');
    Route::delete('/holidays/{id}', [HcmHolidayController::class, 'destroy'])->whereNumber('id');
});
