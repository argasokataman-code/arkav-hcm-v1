<?php

use App\Http\Controllers\Api\HcmTerminationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/terminations')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::get('/', [HcmTerminationController::class, 'index']);
    Route::get('/settlement-preview', [HcmTerminationController::class, 'settlementPreviewByUser']);
    Route::get('/users/{userId}/terminations', [HcmTerminationController::class, 'terminationsForUser'])->whereNumber('userId');
    Route::post('/', [HcmTerminationController::class, 'store']);
    Route::get('/{id}/settlement-preview', [HcmTerminationController::class, 'settlementPreview'])->whereNumber('id');
    Route::post('/{id}/clearance-items/{assignmentId}/return', [HcmTerminationController::class, 'returnClearanceItem'])
        ->whereNumber('id')
        ->whereNumber('assignmentId');
    Route::get('/{id}', [HcmTerminationController::class, 'show'])->whereNumber('id');
    Route::put('/{id}', [HcmTerminationController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [HcmTerminationController::class, 'destroy'])->whereNumber('id');
});
