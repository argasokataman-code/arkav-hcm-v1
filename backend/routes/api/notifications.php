<?php

use App\Http\Controllers\Api\Notifications\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/notifications')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::post('/send-email', [NotificationController::class, 'sendComposeEmail']);
});
