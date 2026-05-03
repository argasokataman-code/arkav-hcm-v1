<?php

use App\Http\Controllers\WilayahLocationController;
use Illuminate\Support\Facades\Route;

Route::get('/countries', [WilayahLocationController::class, 'countries'])->name('countries');
Route::get('/states', [WilayahLocationController::class, 'states'])->name('states');
Route::get('/cities', [WilayahLocationController::class, 'cities'])->name('cities');
Route::get('/villages', [WilayahLocationController::class, 'villages'])->name('villages');

Route::post('/locations/sync', [WilayahLocationController::class, 'sync'])->middleware('hcm.web.global-admin')->name('locations.sync');
Route::get('/locations/sync-status', [WilayahLocationController::class, 'syncStatus'])->name('locations.sync-status');
