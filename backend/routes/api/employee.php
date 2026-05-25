<?php

use App\Http\Controllers\Api\Employee\HcmEmployeeController;
use App\Http\Controllers\Api\Employee\HcmTeamController;
use App\Http\Controllers\Api\Location\WilayahLookupController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context', 'hcm.api.feature:employee_management'])->group(function () {
    // Employees
    Route::get('/employees', [HcmEmployeeController::class, 'index']);
    Route::get('/employees/export', [HcmEmployeeController::class, 'exportEmployees']);
    Route::post('/employees', [HcmEmployeeController::class, 'store']);
    Route::get('/employees/bulk-template', [HcmEmployeeController::class, 'bulkTemplate']);
    Route::post('/employees/bulk-upload', [HcmEmployeeController::class, 'bulkUpload']);
    Route::get('/employees/salary-template', [HcmEmployeeController::class, 'bulkTemplate']);
    Route::post('/employees/salary-bulk-upload', [HcmEmployeeController::class, 'bulkUpload']);
    Route::get('/employees/{id}', [HcmEmployeeController::class, 'show'])->whereNumber('id');
    Route::put('/employees/{id}', [HcmEmployeeController::class, 'update'])->whereNumber('id');
    Route::post('/employees/{id}/profile-photo', [HcmEmployeeController::class, 'uploadProfilePhoto'])->whereNumber('id');
    Route::delete('/employees/{id}/profile-photo', [HcmEmployeeController::class, 'deleteProfilePhoto'])->whereNumber('id');
});

// Wilayah (Location) Lookups — public, no auth required (static Indonesian geographic reference data)
Route::prefix('v1/hcm')->group(function () {
    Route::get('/wilayah/provinces', [WilayahLookupController::class, 'provinces']);
    Route::get('/wilayah/regencies', [WilayahLookupController::class, 'regencies']);
    Route::get('/wilayah/districts', [WilayahLookupController::class, 'districts']);
    Route::get('/wilayah/villages', [WilayahLookupController::class, 'villages']);
});

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context'])->group(function () {

    // Departments
    Route::get('/departments', [HcmEmployeeController::class, 'departments']);
    Route::get('/departments/export', [HcmEmployeeController::class, 'exportDepartments']);
    Route::post('/departments', [HcmEmployeeController::class, 'storeDepartment']);
    Route::put('/departments/{id}', [HcmEmployeeController::class, 'updateDepartment'])->whereNumber('id');
    Route::delete('/departments/{id}', [HcmEmployeeController::class, 'destroyDepartment'])->whereNumber('id');

    // Designations
    Route::get('/designations', [HcmEmployeeController::class, 'designations']);
    Route::get('/designations/export', [HcmEmployeeController::class, 'exportDesignations']);
    Route::post('/designations', [HcmEmployeeController::class, 'storeDesignation']);
    Route::put('/designations/{id}', [HcmEmployeeController::class, 'updateDesignation'])->whereNumber('id');
    Route::delete('/designations/{id}', [HcmEmployeeController::class, 'destroyDesignation'])->whereNumber('id');

    // Policies
    Route::get('/policies', [HcmEmployeeController::class, 'policies']);
    Route::get('/policies/export', [HcmEmployeeController::class, 'exportPolicies']);
    Route::post('/policies', [HcmEmployeeController::class, 'storePolicy']);
    Route::put('/policies/{id}', [HcmEmployeeController::class, 'updatePolicy'])->whereNumber('id');
    Route::delete('/policies/{id}', [HcmEmployeeController::class, 'destroyPolicy'])->whereNumber('id');

    // Teams
    Route::get('/teams', [HcmTeamController::class, 'index']);
    Route::post('/teams', [HcmTeamController::class, 'store']);
    Route::post('/teams/reassign-members', [HcmTeamController::class, 'reassignMembers']);
    Route::get('/teams/{id}/members', [HcmTeamController::class, 'members']);
    Route::get('/teams/{id}', [HcmTeamController::class, 'show']);
    Route::put('/teams/{id}', [HcmTeamController::class, 'update']);
    Route::delete('/teams/{id}', [HcmTeamController::class, 'destroy']);
});
