<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['hcm.web.admin', 'hcm.web.asset-management'])->group(function (): void {
    Route::get('assets', function () {
        return view('administration.assets.assets');
    })->name('assets');

    Route::get('asset-categories', function () {
        return view('administration.assets.asset-categories');
    })->name('asset-categories');
});
