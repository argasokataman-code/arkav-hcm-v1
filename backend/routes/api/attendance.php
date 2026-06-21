<?php

use App\Http\Controllers\Api\Attendance\AttendanceAdminController;
use App\Http\Controllers\Api\Attendance\AttendanceCorrectionController;
use App\Http\Controllers\Api\Attendance\AttendanceEmployeeController;
use App\Http\Controllers\Api\Attendance\AttendanceScheduleController;
use App\Http\Controllers\Api\Attendance\AttendanceSelfieController;
use App\Http\Controllers\Api\Attendance\AttendanceTimesheetController;
use App\Http\Controllers\Api\Attendance\HcmAttendanceSettingsController;
use App\Http\Controllers\Api\Attendance\HcmShiftController;
use App\Http\Controllers\Api\Attendance\HcmSmartAttendanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context', 'hcm.api.feature:attendance'])->group(function () {
    // Attendance Admin
    Route::get('/attendance/admin', [AttendanceAdminController::class, 'adminIndex']);
    Route::get('/attendance/admin/export', [AttendanceAdminController::class, 'adminExport']);
    Route::put('/attendance/admin/record', [AttendanceAdminController::class, 'adminUpsertRecord']);

    // Corrections (admin: review, approve, dismiss)
    Route::get('/attendance/admin/corrections', [AttendanceCorrectionController::class, 'pendingCorrections']);
    Route::post('/attendance/admin/correction-approve', [AttendanceCorrectionController::class, 'approveCorrection']);
    Route::post('/attendance/admin/correction-dismiss', [AttendanceCorrectionController::class, 'dismissCorrection']);

    // Attendance Settings
    Route::get('/attendance/settings', [HcmAttendanceSettingsController::class, 'show']);
    Route::put('/attendance/settings', [HcmAttendanceSettingsController::class, 'update']);

    // Timesheets
    Route::get('/timesheets', [AttendanceTimesheetController::class, 'timesheetsIndex']);

    // Employee Attendance Self-Service
    Route::get('/attendance/me/today', [AttendanceEmployeeController::class, 'meToday']);
    Route::get('/attendance/me/history', [AttendanceEmployeeController::class, 'meHistory']);
    Route::get('/attendance/me/stats', [AttendanceEmployeeController::class, 'meStats']);
    Route::post('/attendance/me/punch', [AttendanceEmployeeController::class, 'punch']);
    Route::post('/attendance/me/break', [AttendanceEmployeeController::class, 'toggleBreak']);
    Route::post('/attendance/me/selfie', [AttendanceSelfieController::class, 'meSelfie'])->middleware('biometric.consent');
    Route::get('/attendance/me/selfie/status', [AttendanceSelfieController::class, 'meSelfieStatus']);
    Route::get('/attendance/admin/records/{id}/selfie/download', [AttendanceSelfieController::class, 'adminSelfieDownload']);
});

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context', 'hcm.api.feature:attendance_correction'])->group(function () {
    // Correction request by employee
    Route::post('/attendance/me/correction-request', [AttendanceCorrectionController::class, 'requestCorrection']);
});

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context', 'hcm.api.feature:attendance_shift_scheduling'])->group(function () {
    // Schedule Timing
    Route::get('/schedule-timing', [AttendanceScheduleController::class, 'scheduleTimingIndex']);
    Route::get('/schedule-timing/export', [AttendanceScheduleController::class, 'scheduleTimingExport']);
    Route::put('/schedule-timing/{userId}', [AttendanceScheduleController::class, 'scheduleTimingUpsert'])->whereNumber('userId');
    Route::delete('/schedule-timing/{userId}', [AttendanceScheduleController::class, 'scheduleTimingDestroy'])->whereNumber('userId');

    // Smart Attendance Shifting
    Route::post('/smart-attendance-shifting/generate', [HcmSmartAttendanceController::class, 'generate']);
    Route::get('/smart-attendance-shifting/settings', [HcmSmartAttendanceController::class, 'settings']);
    Route::put('/smart-attendance-shifting/settings', [HcmSmartAttendanceController::class, 'updateSettings']);
    Route::post('/smart-attendance-shifting/publish-roster', [HcmSmartAttendanceController::class, 'publishRoster']);
    Route::post('/smart-attendance-shifting/simulate-swap', [HcmSmartAttendanceController::class, 'simulateSwap']);
    Route::post('/smart-attendance-shifting/find-replacement', [HcmSmartAttendanceController::class, 'findReplacement']);
    Route::get('/schedule-rosters', [HcmSmartAttendanceController::class, 'rosterIndex']);

    // Shifts
    Route::get('/shifts', [HcmShiftController::class, 'index']);
    Route::post('/shifts', [HcmShiftController::class, 'store']);
    Route::put('/shifts/{id}', [HcmShiftController::class, 'update'])->whereNumber('id');
    Route::delete('/shifts/{id}', [HcmShiftController::class, 'destroy'])->whereNumber('id');
});
