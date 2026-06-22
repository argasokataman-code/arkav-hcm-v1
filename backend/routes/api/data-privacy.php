<?php

use App\Http\Controllers\Api\DataPrivacy\HcmDataPrivacyAiController;
use App\Http\Controllers\Api\DataPrivacy\HcmDataPrivacyController;
use App\Http\Controllers\Api\DataPrivacy\HcmSecurityIncidentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/data-privacy')->middleware(['api.token', 'tenant.context'])->group(function () {
    // Employee: manage own biometric consent (Pasal 9 UU PDP — persetujuan biometrik)
    Route::post('/me/biometric-consent', [HcmDataPrivacyController::class, 'storeBiometricConsent']);
    Route::delete('/me/biometric-consent', [HcmDataPrivacyController::class, 'withdrawBiometricConsent']);
    Route::get('/me/biometric-consent-status', [HcmDataPrivacyController::class, 'checkBiometricConsentStatus']);
    Route::post('/me/withdraw-consent', [HcmDataPrivacyController::class, 'withdrawConsent']);

    // Employee: manage own AI Chat consent (UU PDP H3 — persetujuan AI)
    Route::post('/me/ai-consent', [HcmDataPrivacyAiController::class, 'grantAiConsent']);
    Route::delete('/me/ai-consent', [HcmDataPrivacyAiController::class, 'withdrawAiConsent']);
    Route::get('/me/ai-consent-status', [HcmDataPrivacyAiController::class, 'checkAiConsentStatus']);

    // M8: Session re-verification for sensitive operations (UU PDP Pasal 35)
    Route::post('/me/session-check', [HcmDataPrivacyController::class, 'sessionCheck']);

    // M6: Photo consent — profile photo = biometric data (UU PDP Pasal 4 ayat 2)
    Route::post('/me/photo-consent', [HcmDataPrivacyController::class, 'grantPhotoConsent']);
    Route::delete('/me/photo-consent', [HcmDataPrivacyController::class, 'withdrawPhotoConsent']);

    // H7: Cookie consent preferences (UU PDP Pasal 20a — persetujuan pengumpulan data)
    Route::post('/me/cookie-consent', [HcmDataPrivacyController::class, 'saveCookieConsent']);
    Route::get('/me/cookie-consent', [HcmDataPrivacyController::class, 'getCookieConsent']);

    // L2: "Data Saya" Portal (UU PDP Pasal 8 + 13 — hak akses & portabilitas)
    Route::get('/me/my-data', [HcmDataPrivacyController::class, 'myData']);
    Route::get('/me/my-data/export', [HcmDataPrivacyController::class, 'exportMyData']);

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
