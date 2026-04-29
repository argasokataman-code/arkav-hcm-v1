<?php

use Illuminate\Support\Facades\Route;

Route::get('/performance-indicator', function () {
    return view(view: 'performance-indicator');
})->middleware('hcm.web.admin')->name('performance-indicator');

Route::get('/performance-review', function () {
    return view(view: 'performance-review');
})->name('performance-review');

Route::get('/performance-appraisal', function () {
    return view(view: 'performance-appraisal');
})->middleware('hcm.web.admin')->name('performance-appraisal');

Route::get('/goal-tracking', function () {
    return view(view: 'goal-tracking');
})->name('goal-tracking');

Route::get('/goal-type', function () {
    return view(view: 'goal-type');
})->middleware('hcm.web.admin')->name('goal-type');
