<?php

use App\Http\Controllers\Api\HcmInvoiceSettingsController;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/settings')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::get('/', [SettingsController::class, 'index']);
    Route::post('/', [SettingsController::class, 'store']);
    Route::post('/upload', [SettingsController::class, 'upload']);
    Route::get('/{key}', [SettingsController::class, 'show']);
    Route::put('/{key}', [SettingsController::class, 'update']);
    Route::delete('/{key}', [SettingsController::class, 'destroy']);
});

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::get('/invoice-settings', [HcmInvoiceSettingsController::class, 'show']);
    Route::put('/invoice-settings', [HcmInvoiceSettingsController::class, 'update']);
});
