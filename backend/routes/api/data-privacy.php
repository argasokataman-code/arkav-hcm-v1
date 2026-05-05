<?php

use App\Http\Controllers\Api\HcmDataPrivacyController;
use App\Http\Controllers\Api\HcmDataPrivacyAiController;
use App\Http\Controllers\Api\HcmSecurityIncidentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/data-privacy')->middleware(['api.token', 'tenant.context'])->group(function () {
    // Employee: manage own biometric consent (Pasal 9 UU PDP — persetujuan biometrik)
    Route::post('/me/biometric-consent', [HcmDataPrivacyController::class, 'storeBiometricConsent']);
    Route::delete('/me/biometric-consent', [HcmDataPrivacyController::class, 'withdrawBiometricConsent']);
    Route::post('/me/withdraw-consent', [HcmDataPrivacyController::class, 'withdrawConsent']);

    // Employee: manage own AI Chat consent (UU PDP H3 — persetujuan AI)
    Route::post('/me/ai-consent', [HcmDataPrivacyAiController::class, 'grantAiConsent']);
    Route::delete('/me/ai-consent', [HcmDataPrivacyAiController::class, 'withdrawAiConsent']);
    Route::get('/me/ai-consent-status', [HcmDataPrivacyAiController::class, 'checkAiConsentStatus']);

    // Employee: request own data erasure (Pasal 43-44 UU PDP)
    Route::post('/me/erasure-requests', [HcmDataPrivacyController::class, 'requestErasure']);
    Route::get('/me/erasure-requests', [HcmDataPrivacyController::class, 'listMyErasureRequests']);

    // Admin: manage erasure requests (requires user_management.manage permission)
    Route::get('/erasure-requests', [HcmDataPrivacyController::class, 'listErasureRequests']);
    Route::post('/erasure-requests/{uuid}/process', [HcmDataPrivacyController::class, 'processErasure'])
        ->whereUuid('uuid');
});

Route::prefix('v1/admin')->middleware(['api.token', 'tenant.context'])->group(function () {
    // Cycle 5 (H2): security incident management + breach notifications
    Route::get('/security-incidents', [HcmSecurityIncidentController::class, 'index']);
    Route::post('/security-incidents', [HcmSecurityIncidentController::class, 'store']);
    Route::get('/security-incidents/{uuid}', [HcmSecurityIncidentController::class, 'show'])->whereUuid('uuid');
    Route::post('/security-incidents/{uuid}/notify-subjects', [HcmSecurityIncidentController::class, 'notifySubjects'])->whereUuid('uuid');
    Route::post('/security-incidents/{uuid}/resolve', [HcmSecurityIncidentController::class, 'resolve'])->whereUuid('uuid');
});
