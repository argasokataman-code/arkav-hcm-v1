<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\HcmSmartAttendanceController;
use App\Http\Controllers\Api\HcmShiftController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context', 'hcm.api.feature:attendance'])->group(function () {
    // Attendance Admin
    Route::get('/attendance/admin', [AttendanceController::class, 'adminIndex']);
    Route::put('/attendance/admin/record', [AttendanceController::class, 'adminUpsertRecord']);

    // Timesheets
    Route::get('/timesheets', [AttendanceController::class, 'timesheetsIndex']);

    // Schedule Timing
    Route::get('/schedule-timing', [AttendanceController::class, 'scheduleTimingIndex']);
    Route::put('/schedule-timing/{userId}', [AttendanceController::class, 'scheduleTimingUpsert'])->whereNumber('userId');
    Route::delete('/schedule-timing/{userId}', [AttendanceController::class, 'scheduleTimingDestroy'])->whereNumber('userId');

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

    // Employee Attendance Self-Service
    Route::get('/attendance/me/today', [AttendanceController::class, 'meToday']);
    Route::get('/attendance/me/history', [AttendanceController::class, 'meHistory']);
    Route::get('/attendance/me/stats', [AttendanceController::class, 'meStats']);
    Route::post('/attendance/me/punch', [AttendanceController::class, 'punch']);
    Route::post('/attendance/me/break', [AttendanceController::class, 'toggleBreak']);
    Route::post('/attendance/me/correction-request', [AttendanceController::class, 'requestCorrection']);
    Route::post('/attendance/me/selfie', [AttendanceController::class, 'meSelfie']);
    Route::get('/attendance/me/selfie/status', [AttendanceController::class, 'meSelfieStatus']);
    Route::get('/attendance/admin/records/{id}/selfie/download', [AttendanceController::class, 'adminSelfieDownload']);
});
