<?php

use App\Http\Controllers\Api\Ticket\HcmTicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::get('/tickets/assignable-users', [HcmTicketController::class, 'assignableUsers']);
    Route::get('/tickets/category-options', [HcmTicketController::class, 'categoryOptions']);

    Route::get('/tickets/categories', [HcmTicketController::class, 'categories']);
    Route::post('/tickets/categories', [HcmTicketController::class, 'storeCategory']);
    Route::put('/tickets/categories/{id}', [HcmTicketController::class, 'updateCategory'])->whereNumber('id');
    Route::delete('/tickets/categories/{id}', [HcmTicketController::class, 'destroyCategory'])->whereNumber('id');

    Route::get('/tickets', [HcmTicketController::class, 'index']);
    Route::post('/tickets', [HcmTicketController::class, 'store']);
    Route::get('/tickets/{id}', [HcmTicketController::class, 'show'])->whereNumber('id');
    Route::put('/tickets/{id}', [HcmTicketController::class, 'update'])->whereNumber('id');
    Route::delete('/tickets/{id}', [HcmTicketController::class, 'destroy'])->whereNumber('id');
    Route::post('/tickets/{id}/comments', [HcmTicketController::class, 'addComment'])->whereNumber('id');
    Route::post('/tickets/{id}/attachments', [HcmTicketController::class, 'addAttachment'])->whereNumber('id');
    Route::get('/tickets/{id}/attachments/{attachmentId}/preview', [HcmTicketController::class, 'previewAttachment'])
        ->whereNumber('id')
        ->whereNumber('attachmentId');
    Route::get('/tickets/{id}/attachments/{attachmentId}/download', [HcmTicketController::class, 'downloadAttachment'])
        ->whereNumber('id')
        ->whereNumber('attachmentId');
});
