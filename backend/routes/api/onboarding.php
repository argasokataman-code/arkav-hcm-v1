<?php

use App\Http\Controllers\Api\Onboarding\PublicOnboardingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/public')->group(function () {
    Route::post('/onboarding', [PublicOnboardingController::class, 'store'])
        ->middleware(['throttle:10,1']);
});
