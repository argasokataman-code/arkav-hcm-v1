<?php

use App\Http\Controllers\Api\Performance\HcmPerformanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/performance')->middleware(['api.token', 'tenant.context', 'hcm.api.feature:performance'])->group(function () {
    // Goal types
    Route::get('/goal-types', [HcmPerformanceController::class, 'goalTypes']);
    Route::post('/goal-types', [HcmPerformanceController::class, 'storeGoalType']);
    Route::put('/goal-types/{id}', [HcmPerformanceController::class, 'updateGoalType'])->whereNumber('id');
    Route::delete('/goal-types/{id}', [HcmPerformanceController::class, 'destroyGoalType'])->whereNumber('id');

    // Goals
    Route::get('/goals', [HcmPerformanceController::class, 'goals']);
    Route::post('/goals', [HcmPerformanceController::class, 'storeGoal']);
    Route::put('/goals/{id}', [HcmPerformanceController::class, 'updateGoal'])->whereNumber('id');
    Route::delete('/goals/{id}', [HcmPerformanceController::class, 'destroyGoal'])->whereNumber('id');

    // Indicator templates
    Route::get('/indicator-templates', [HcmPerformanceController::class, 'indicatorTemplates']);
    Route::post('/indicator-templates', [HcmPerformanceController::class, 'storeIndicatorTemplate']);
    Route::put('/indicator-templates/{id}', [HcmPerformanceController::class, 'updateIndicatorTemplate'])->whereNumber('id');
    Route::delete('/indicator-templates/{id}', [HcmPerformanceController::class, 'destroyIndicatorTemplate'])->whereNumber('id');
    Route::get('/indicator-templates/{id}/items', [HcmPerformanceController::class, 'indicatorItems'])->whereNumber('id');
    Route::post('/indicator-templates/{id}/items', [HcmPerformanceController::class, 'storeIndicatorItem'])->whereNumber('id');
    Route::put('/indicator-items/{itemId}', [HcmPerformanceController::class, 'updateIndicatorItem'])->whereNumber('itemId');
    Route::delete('/indicator-items/{itemId}', [HcmPerformanceController::class, 'destroyIndicatorItem'])->whereNumber('itemId');

    // Cycles
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
