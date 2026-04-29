<?php

use App\Http\Controllers\Api\HcmTrainingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/training')->middleware(['api.token', 'tenant.context', 'hcm.api.feature:training'])->group(function () {
    // Training types
    Route::get('/types', [HcmTrainingController::class, 'types']);
    Route::post('/types', [HcmTrainingController::class, 'storeType']);
    Route::put('/types/{id}', [HcmTrainingController::class, 'updateType'])->whereNumber('id');
    Route::delete('/types/{id}', [HcmTrainingController::class, 'destroyType'])->whereNumber('id');

    // Trainings
    Route::get('/trainings', [HcmTrainingController::class, 'trainings']);
    Route::post('/trainings', [HcmTrainingController::class, 'storeTraining']);
    Route::put('/trainings/{id}', [HcmTrainingController::class, 'updateTraining'])->whereNumber('id');
    Route::delete('/trainings/{id}', [HcmTrainingController::class, 'destroyTraining'])->whereNumber('id');
    Route::get('/users/{userId}/trainings', [HcmTrainingController::class, 'trainingsForUser'])->whereNumber('userId');

    // Trainers
    Route::get('/trainers', [HcmTrainingController::class, 'trainers']);
    Route::post('/trainers', [HcmTrainingController::class, 'storeTrainer']);
    Route::put('/trainers/{id}', [HcmTrainingController::class, 'updateTrainer'])->whereNumber('id');
    Route::delete('/trainers/{id}', [HcmTrainingController::class, 'destroyTrainer'])->whereNumber('id');
});
