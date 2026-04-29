<?php

use Illuminate\Support\Facades\Route;

Route::middleware('hcm.web.admin')->group(function (): void {
    Route::get('/promotion', function () {
        return view(view: 'promotion');
    })->name('promotion');

    Route::get('/resignation', function () {
        return view(view: 'resignation');
    })->name('resignation');

    Route::get('/termination', function () {
        return view(view: 'termination');
    })->name('termination');
});
