<?php

use Illuminate\Support\Facades\Route;

Route::middleware('hcm.web.admin')->group(function (): void {
    Route::get('/spt-masa-pph21', fn () => view('spt-masa.index'))->name('spt-masa-pph21.index');
    Route::get('/spt-masa-pph21/{sptUuid}', fn (string $sptUuid) => view('spt-masa.show', ['sptUuid' => $sptUuid]))->name('spt-masa-pph21.show');
});
