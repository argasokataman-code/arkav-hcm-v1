<?php

use App\Http\Controllers\Api\CompanyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/company')->middleware(['api.token'])->group(function () {
    Route::get('/', [CompanyController::class, 'index']);
    Route::post('/', [CompanyController::class, 'store']);
    Route::put('/{id}', [CompanyController::class, 'update']);
    Route::delete('/{id}', [CompanyController::class, 'destroy']);
});

Route::prefix('v1/hcm/company')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::get('/active', [CompanyController::class, 'active']);
});
