<?php

use App\Http\Controllers\Api\HcmNoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::get('/notes', [HcmNoteController::class, 'index']);
    Route::post('/notes', [HcmNoteController::class, 'store']);
    Route::put('/notes/{id}', [HcmNoteController::class, 'update'])->whereNumber('id');
    Route::delete('/notes/{id}', [HcmNoteController::class, 'destroy'])->whereNumber('id');
});
