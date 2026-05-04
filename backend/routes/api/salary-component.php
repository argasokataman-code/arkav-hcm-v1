<?php

use App\Http\Controllers\Api\HcmSalaryComponentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::get('/salary-components', [HcmSalaryComponentController::class, 'index']);
    Route::get('/salary-components/employee-profiles', [HcmSalaryComponentController::class, 'employeeProfiles']);
    Route::get('/salary-component-categories', [HcmSalaryComponentController::class, 'categories']);
    Route::post('/salary-component-categories', [HcmSalaryComponentController::class, 'storeCategory']);
    Route::put('/salary-component-categories/{id}', [HcmSalaryComponentController::class, 'updateCategory'])->whereNumber('id');
    Route::delete('/salary-component-categories/{id}', [HcmSalaryComponentController::class, 'destroyCategory'])->whereNumber('id');
    Route::post('/salary-components', [HcmSalaryComponentController::class, 'store']);
    Route::get('/salary-components/{id}', [HcmSalaryComponentController::class, 'show'])->whereNumber('id');
    Route::put('/salary-components/{id}', [HcmSalaryComponentController::class, 'update'])->whereNumber('id');
    Route::patch('/salary-components/{id}/tax-flags', [HcmSalaryComponentController::class, 'patchTaxFlags'])->whereNumber('id');
    Route::delete('/salary-components/{id}', [HcmSalaryComponentController::class, 'destroy'])->whereNumber('id');
});
