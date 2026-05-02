<?php

use App\Http\Controllers\Api\HcmEmployeeDocumentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm/document-center')
    ->middleware(['api.token', 'tenant.context', 'hcm.api.feature:employee_document_center'])
    ->group(function (): void {
        // Categories
        Route::get('/categories', [HcmEmployeeDocumentController::class, 'categories']);
        Route::post('/categories', [HcmEmployeeDocumentController::class, 'storeCategory']);
        Route::put('/categories/{id}', [HcmEmployeeDocumentController::class, 'updateCategory'])->whereNumber('id');
        Route::delete('/categories/{id}', [HcmEmployeeDocumentController::class, 'destroyCategory'])->whereNumber('id');

        // Documents
        Route::get('/documents', [HcmEmployeeDocumentController::class, 'index']);
        Route::post('/documents', [HcmEmployeeDocumentController::class, 'store']);
        Route::put('/documents/{id}', [HcmEmployeeDocumentController::class, 'update'])->whereNumber('id');
        Route::delete('/documents/{id}', [HcmEmployeeDocumentController::class, 'destroy'])->whereNumber('id');
        Route::get('/documents/{id}/download', [HcmEmployeeDocumentController::class, 'download'])
            ->whereNumber('id')
            ->name('api.document-center.download');
    });
