<?php

use App\Http\Controllers\Api\LogViewerController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/log-viewer')->middleware(['api.token', 'hcm.api.global-admin'])->group(function () {
    Route::get('/files', [LogViewerController::class, 'index']);
    Route::get('/files/{filename}', [LogViewerController::class, 'show']);
});
