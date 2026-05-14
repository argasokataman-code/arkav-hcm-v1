<?php

use App\Http\Controllers\Api\Resignation\HcmResignationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/resignations')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::get('/', [HcmResignationController::class, 'index']);
    Route::get('/users/{userId}/resignations', [HcmResignationController::class, 'resignationsForUser'])->where('userId', '[0-9a-fA-F\-]+');
    Route::post('/', [HcmResignationController::class, 'store']);
    Route::get('/{id}', [HcmResignationController::class, 'show'])->where('id', '[0-9a-fA-F\-]+');
    Route::put('/{id}', [HcmResignationController::class, 'update'])->where('id', '[0-9a-fA-F\-]+');
    Route::delete('/{id}', [HcmResignationController::class, 'destroy'])->where('id', '[0-9a-fA-F\-]+');
});
