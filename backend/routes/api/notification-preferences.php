<?php

use App\Http\Controllers\Api\HcmNotificationPreferenceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::get('/notification-preferences', [HcmNotificationPreferenceController::class, 'index']);
    Route::put('/notification-preferences', [HcmNotificationPreferenceController::class, 'update']);
});
