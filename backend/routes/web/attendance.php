<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['hcm.web.admin', 'hcm.web.feature:attendance'])->group(function (): void {
    Route::get('/attendance-admin', function () {
        return view(view: 'attendance-admin');
    })->name('attendance-admin');

    Route::get('/timesheets', function () {
        return view(view: 'timesheets');
    })->name('timesheets');

    Route::get('/schedule-timing', function () {
        return view(view: 'schedule-timing');
    })->name('schedule-timing');

    Route::get('/shift-master', function () {
        return view(view: 'shift-master');
    })->name('shift-master');
});

Route::get('/attendance-employee', function () {
    return view(view: 'attendance-employee');
})->name('attendance-employee');

Route::get('/schedules', function () {
    $user = request()->user();
    $activeCompanyId = (int) (request()->attributes->get('activeCompanyId') ?? 0);
    $isAdmin = $user && ($activeCompanyId > 0
        ? $user->isHcmAdminForCompany($activeCompanyId)
        : $user->isHcmAdmin());

    if ($isAdmin) {
        return redirect('/schedule-timing');
    }

    return redirect('/attendance-employee');
})->name('schedules-legacy');
