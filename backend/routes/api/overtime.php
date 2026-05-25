<?php

use App\Http\Controllers\Api\Overtime\HcmOvertimeTypeController;
use App\Http\Controllers\Api\Overtime\HcmOvertimeRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context', 'hcm.api.feature:overtime'])->group(function () {
    // Overtime Types
    Route::get('/overtime-types', [HcmOvertimeTypeController::class, 'index']);
    Route::post('/overtime-types', [HcmOvertimeTypeController::class, 'store']);
    Route::put('/overtime-types/{id}', [HcmOvertimeTypeController::class, 'update'])->whereNumber('id');
    Route::delete('/overtime-types/{id}', [HcmOvertimeTypeController::class, 'destroy'])->whereNumber('id');

    // Overtime Requests
    Route::get('/overtime-requests', [HcmOvertimeRequestController::class, 'index']);
    Route::post('/overtime-requests', [HcmOvertimeRequestController::class, 'store']);
    Route::post('/overtime-requests/calculate', [HcmOvertimeRequestController::class, 'calculate']);
    Route::put('/overtime-requests/{id}', [HcmOvertimeRequestController::class, 'update'])->whereNumber('id');
    Route::delete('/overtime-requests/{id}', [HcmOvertimeRequestController::class, 'destroy'])->whereNumber('id');
});
