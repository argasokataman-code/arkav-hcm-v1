<?php

use App\Http\Controllers\Api\HcmAiChatController;
use App\Http\Controllers\Api\HcmDashboardController;
use App\Http\Controllers\Api\HcmGlobalSearchController;
use App\Http\Controllers\Api\HcmNotificationController;
use App\Http\Controllers\Api\HcmActivityController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/hcm')->middleware(['api.token', 'tenant.context'])->group(function () {
    Route::get('/dashboard-summary', [HcmDashboardController::class, 'summary']);
    Route::get('/dashboard-summary/export', [HcmDashboardController::class, 'exportSummary']);
    Route::get('/employee-dashboard-summary', [HcmDashboardController::class, 'employeeSummary']);
    Route::get('/super-admin/employees-monitor', [HcmDashboardController::class, 'globalEmployeeMonitor'])->middleware('throttle:60,1');
    Route::get('/super-admin/package-compliance', [HcmDashboardController::class, 'packageComplianceMonitor'])->middleware('throttle:60,1');
    Route::get('/super-admin/package-compliance/{companyId}/employees', [HcmDashboardController::class, 'packageComplianceEmployees'])
        ->whereNumber('companyId')
        ->middleware('throttle:60,1');
    Route::get('/search', [HcmGlobalSearchController::class, 'index'])->middleware('throttle:120,1');
    
    Route::get('/notifications', [HcmNotificationController::class, 'index']);
    Route::post('/notifications/read-all', [HcmNotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{notification}/read', [HcmNotificationController::class, 'markAsRead']);
    Route::get('/notifications/unread-count', [HcmNotificationController::class, 'unreadCount']);
    Route::get('/notifications/delivery-summary', [HcmNotificationController::class, 'deliverySummary'])->middleware('throttle:100,1');
    Route::get('/notifications/delivery-details', [HcmNotificationController::class, 'deliveryDetails'])->middleware('throttle:100,1');
    Route::get('/notifications/delivery-export', [HcmNotificationController::class, 'exportDeliveries'])->middleware('throttle:50,1');
    Route::post('/notifications/delivery/{id}/retry', [HcmNotificationController::class, 'retryDelivery'])->whereNumber('id')->middleware('throttle:30,1');
    Route::get('/notifications/templates', [HcmNotificationController::class, 'templateCatalog'])->middleware('throttle:30,1');
    
    Route::get('/activity-feed', [HcmActivityController::class, 'index']);
    Route::get('/activity-feed-companies', [HcmActivityController::class, 'listCompanies']);
    Route::post('/activity-manual', [HcmActivityController::class, 'storeManual']);
    Route::put('/activity-manual/{id}', [HcmActivityController::class, 'updateManual'])->whereNumber('id');
    Route::delete('/activity-manual/{id}', [HcmActivityController::class, 'destroyManual'])->whereNumber('id');

    // AI assistant
    Route::post('/ai/chat', [HcmAiChatController::class, 'chat'])->middleware('throttle:30,1');
    Route::get('/ai/intents', [HcmAiChatController::class, 'intents']);
});
