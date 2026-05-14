<?php

use App\Http\Controllers\Api\Faq\HcmFaqController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context'])->group(function (): void {
    Route::get('/faqs', [HcmFaqController::class, 'index']);
    Route::post('/faqs', [HcmFaqController::class, 'store']);
    Route::put('/faqs/{id}', [HcmFaqController::class, 'update'])->whereNumber('id');
    Route::delete('/faqs/{id}', [HcmFaqController::class, 'destroy'])->whereNumber('id');
    Route::post('/faqs/bulk-delete', [HcmFaqController::class, 'bulkDestroy']);
});
