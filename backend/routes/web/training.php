<?php

use Illuminate\Support\Facades\Route;

Route::get('/training', function () {
    return view(view: 'training');
})->middleware(['hcm.web.admin', 'hcm.web.feature:training'])->name('training');

Route::get('/trainers', function () {
    return view(view: 'trainers');
})->middleware(['hcm.web.admin', 'hcm.web.feature:training'])->name('trainers');

Route::get('/training-type', function () {
    return view(view: 'training-type');
})->middleware(['hcm.web.admin', 'hcm.web.feature:training'])->name('training-type');
