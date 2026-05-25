<?php

use App\Http\Controllers\Api\Calendar\HcmCalendarEventController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context', 'hcm.api.feature:holiday_calendar'])->group(function () {
    Route::get('/calendar/events', [HcmCalendarEventController::class, 'index']);
    Route::post('/calendar/events', [HcmCalendarEventController::class, 'store']);
    Route::put('/calendar/events/{id}', [HcmCalendarEventController::class, 'update'])->whereNumber('id');
    Route::delete('/calendar/events/{id}', [HcmCalendarEventController::class, 'destroy'])->whereNumber('id');
});
