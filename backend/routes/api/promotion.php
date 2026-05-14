<?php

use App\Http\Controllers\Api\Promotion\HcmPromotionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/promotions')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::get('/', [HcmPromotionController::class, 'index']);
    Route::get('/users/{userId}/promotions', [HcmPromotionController::class, 'promotionsForUser'])->whereNumber('userId');
    Route::post('/', [HcmPromotionController::class, 'store']);
    Route::get('/{id}', [HcmPromotionController::class, 'show'])->whereNumber('id');
    Route::put('/{id}', [HcmPromotionController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [HcmPromotionController::class, 'destroy'])->whereNumber('id');
});
