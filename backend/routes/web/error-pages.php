<?php

use Illuminate\Support\Facades\Route;

Route::get('/error-404', function () {
    return view('auth.error-404');
})->name('error-404');

Route::get('/error-500', function () {
    return view('auth.error-500');
})->name('error-500');

Route::get('/coming-soon', function () {
    return view('auth.coming-soon');
})->name('coming-soon');

Route::get('/under-maintenance', function () {
    return view('auth.under-maintenance');
})->name('under-maintenance');

Route::get('/under-construction', function () {
    return view('auth.under-construction');
})->name('under-construction');

Route::get('/success', function () {
    return view('auth.success');
})->name('success');

Route::get('/success-2', function () {
    return view('auth.success-2');
})->name('success-2');

Route::get('/success-3', function () {
    return view('auth.success-3');
})->name('success-3');
