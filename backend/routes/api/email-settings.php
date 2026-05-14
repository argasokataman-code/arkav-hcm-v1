<?php

use App\Http\Controllers\Api\Settings\HcmEmailSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/email-settings')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::get('/', [HcmEmailSettingsController::class, 'show']);
    Route::put('/', [HcmEmailSettingsController::class, 'update']);
    Route::get('/mailtrap-status', [HcmEmailSettingsController::class, 'mailtrapStatus']);
    Route::post('/test-connection', [HcmEmailSettingsController::class, 'testConnection']);
    Route::post('/compose', [HcmEmailSettingsController::class, 'sendCompose']);
});
