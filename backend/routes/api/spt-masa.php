<?php

use App\Http\Controllers\Api\SptMasa\HcmSptMasaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/spt-masa')->middleware(['api.token', 'tenant.context'])->group(function (): void {
    // List SPT Masa headers for the active tenant.
    Route::get('/headers', [HcmSptMasaController::class, 'index']);

    // Generate a new SPT Masa snapshot (idempotent via generationKey).
    Route::post('/headers', [HcmSptMasaController::class, 'generate']);

    // Show detail of a single SPT Masa header.
    Route::get('/headers/{sptRef}', [HcmSptMasaController::class, 'show']);

    // Regenerate snapshot from latest finalized payroll (draft/ready only).
    Route::post('/headers/{sptRef}/regenerate', [HcmSptMasaController::class, 'regenerate']);

    // Transition draft -> ready.
    Route::post('/headers/{sptRef}/mark-ready', [HcmSptMasaController::class, 'markReady']);

    // Transition ready -> submitted.
    Route::post('/headers/{sptRef}/submit', [HcmSptMasaController::class, 'submit']);

    // Stream CSV export (DJP-style).
    Route::get('/headers/{sptRef}/export.csv', [HcmSptMasaController::class, 'exportCsv']);
});
