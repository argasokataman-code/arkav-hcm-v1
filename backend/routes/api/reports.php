<?php

use App\Http\Controllers\Api\ReportSnapshotController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/reports')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::prefix('snapshots')->group(function () {
        Route::post('/', [ReportSnapshotController::class, 'generate']);
        Route::get('/', [ReportSnapshotController::class, 'list']);
        Route::get('/{id}', [ReportSnapshotController::class, 'show'])->where('id', '[0-9a-fA-F\-]+');
        Route::post('/{id}/export', [ReportSnapshotController::class, 'export'])->where('id', '[0-9a-fA-F\-]+');
    });
});
