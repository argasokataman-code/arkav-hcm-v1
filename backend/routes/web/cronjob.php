<?php

use App\Http\Controllers\CronjobController;
use Illuminate\Support\Facades\Route;

Route::get('/cronjob', [CronjobController::class, 'index'])->middleware('hcm.web.global-admin')->name('cronjob');
Route::post('/cronjob', [CronjobController::class, 'update'])->middleware('hcm.web.global-admin')->name('cronjob.update');

Route::get('/cronjob-schedule', function () {
    return view('settings.cronjob-schedule');
})->middleware('hcm.web.global-admin')->name('cronjob-schedule');
