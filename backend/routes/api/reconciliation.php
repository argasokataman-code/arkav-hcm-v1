<?php

use App\Http\Controllers\Api\ReconciliationExportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/reconciliation')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::post('/exports', [ReconciliationExportController::class, 'store']);
    Route::get('/exports', [ReconciliationExportController::class, 'index']);
    Route::get('/exports/{id}/download', [ReconciliationExportController::class, 'download'])->whereNumber('id');
});
