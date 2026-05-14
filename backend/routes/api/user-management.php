<?php

use App\Http\Controllers\Api\UserManagement\HcmUserManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/user-management')->middleware(['api.token', 'tenant.context'])->group(function () {
    // Users
    Route::get('/users', [HcmUserManagementController::class, 'users']);
    Route::get('/users/export', [HcmUserManagementController::class, 'usersExport']);
    Route::get('/users/{id}', [HcmUserManagementController::class, 'userDetail'])->whereNumber('id');
    Route::post('/users', [HcmUserManagementController::class, 'createUser']);
    Route::put('/users/{id}', [HcmUserManagementController::class, 'updateUser'])->whereNumber('id');
    Route::delete('/users/{id}', [HcmUserManagementController::class, 'deleteUser'])->whereNumber('id');

    // Roles
    Route::get('/roles', [HcmUserManagementController::class, 'roles']);
    Route::post('/roles', [HcmUserManagementController::class, 'createRole']);
    Route::put('/roles/{id}', [HcmUserManagementController::class, 'updateRole'])->whereNumber('id');
    Route::delete('/roles/{id}', [HcmUserManagementController::class, 'deleteRole'])->whereNumber('id');

    // Permissions
    Route::get('/permissions', [HcmUserManagementController::class, 'permissions']);
    Route::post('/roles/{id}/permissions:sync', [HcmUserManagementController::class, 'syncRolePermissions'])->whereNumber('id');

    // User Roles
    Route::get('/users/{id}/roles', [HcmUserManagementController::class, 'userRoles'])->whereNumber('id');
    Route::post('/users/{id}/roles', [HcmUserManagementController::class, 'assignUserRole'])->whereNumber('id');
    Route::delete('/users/{id}/roles/{assignmentId}', [HcmUserManagementController::class, 'revokeUserRole'])
        ->whereNumber('id')
        ->whereNumber('assignmentId');
});
