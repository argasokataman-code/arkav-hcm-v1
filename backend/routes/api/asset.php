<?php

use App\Http\Controllers\Api\Asset\HcmAssetController;
use App\Http\Controllers\Api\Asset\HcmAssetCategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context', 'hcm.api.feature:asset_management'])->group(function () {
    Route::get('/asset-categories', [HcmAssetCategoryController::class, 'index']);
    Route::post('/asset-categories', [HcmAssetCategoryController::class, 'store']);
    Route::put('/asset-categories/{category}', [HcmAssetCategoryController::class, 'update'])->whereNumber('category');
    Route::delete('/asset-categories/{category}', [HcmAssetCategoryController::class, 'destroy'])->whereNumber('category');

    Route::get('/assets', [HcmAssetController::class, 'index']);
    Route::post('/assets', [HcmAssetController::class, 'store']);
    Route::get('/assets/{asset}', [HcmAssetController::class, 'show'])->whereNumber('asset');
    Route::put('/assets/{asset}', [HcmAssetController::class, 'update'])->whereNumber('asset');
    Route::delete('/assets/{asset}', [HcmAssetController::class, 'destroy'])->whereNumber('asset');
    Route::post('/assets/{asset}/assign', [HcmAssetController::class, 'assign'])->whereNumber('asset');
    Route::post('/assets/{asset}/return', [HcmAssetController::class, 'returnAsset'])->whereNumber('asset');
    Route::post('/assets/{asset}/issue-report', [HcmAssetController::class, 'reportIssue'])->whereNumber('asset');
    Route::post('/assets/{asset}/attachments', [HcmAssetController::class, 'attach'])->whereNumber('asset');
});
