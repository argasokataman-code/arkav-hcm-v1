<?php

use App\Http\Controllers\Api\Termination\HcmTerminationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/terminations')->middleware(['api.token', 'tenant.context', 'hcm.api.feature:termination'])->group(function () {
    Route::get('/', [HcmTerminationController::class, 'index']);
    Route::get('/settlement-preview', [HcmTerminationController::class, 'settlementPreviewByUser']);
    Route::get('/users/{userId}/terminations', [HcmTerminationController::class, 'terminationsForUser'])->whereNumber('userId');
    Route::post('/', [HcmTerminationController::class, 'store']);
    Route::get('/{id}/settlement-preview', [HcmTerminationController::class, 'settlementPreview'])->whereNumber('id');
    Route::post('/{id}/clearance-items/{assignmentId}/return', [HcmTerminationController::class, 'returnClearanceItem'])
        ->whereNumber('id')
        ->whereNumber('assignmentId');
    // Slice C — Structured checklist item management
    Route::post('/{id}/checklist-items', [HcmTerminationController::class, 'createChecklistItem'])->whereNumber('id');
    Route::get('/{id}/checklist-items', [HcmTerminationController::class, 'listChecklistItems'])->whereNumber('id');
    Route::patch('/{id}/checklist-items/{itemId}/complete', [HcmTerminationController::class, 'completeChecklistItem'])
        ->whereNumber('id')
        ->whereNumber('itemId');
    Route::patch('/{id}/checklist-items/{itemId}', [HcmTerminationController::class, 'updateChecklistItem'])
        ->whereNumber('id')
        ->whereNumber('itemId');
    Route::delete('/{id}/checklist-items/{itemId}', [HcmTerminationController::class, 'deleteChecklistItem'])
        ->whereNumber('id')
        ->whereNumber('itemId');
    Route::get('/{id}', [HcmTerminationController::class, 'show'])->whereNumber('id');
    Route::put('/{id}', [HcmTerminationController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [HcmTerminationController::class, 'destroy'])->whereNumber('id');
});
