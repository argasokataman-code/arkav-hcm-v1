<?php

use App\Http\Controllers\Api\Settings\HcmApprovalSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/approval-settings')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::get('/', [HcmApprovalSettingsController::class, 'index']);
    Route::get('/eligible-approvers', [HcmApprovalSettingsController::class, 'eligibleApprovers']);
    Route::put('/{module}', [HcmApprovalSettingsController::class, 'update'])->where('module', '[a-z_]+');
});
