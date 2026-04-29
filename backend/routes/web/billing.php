<?php

use Illuminate\Support\Facades\Route;

// Company billing (tenant admin self-service)
Route::get('/company/invoices', function () {
    return view('company.invoices');
})->middleware('hcm.web.admin')->name('company.invoices');

Route::get('/upgrade', function () {
    return view('misc.upgrade');
})->name('upgrade');

Route::get('/subscription', function () {
    return view('saas.subscription-checkout');
})->middleware('hcm.web.admin')->name('subscription');

Route::get('/api-token', [\App\Http\Controllers\ApiTokenController::class, 'getToken'])->name('api-token');
