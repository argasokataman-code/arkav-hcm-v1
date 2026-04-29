<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/identity')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('api.token')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });

    Route::middleware(['api.token', 'tenant.context'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    });
});
