<?php

use Illuminate\Support\Facades\Route;

Route::get('/attendance-admin', function () {
    return view(view: 'attendance-admin');
})->middleware('hcm.web.admin')->name('attendance-admin');

Route::get('/attendance-employee', function () {
    return view(view: 'attendance-employee');
})->name('attendance-employee');

Route::get('/timesheets', function () {
    return view(view: 'timesheets');
})->middleware('hcm.web.admin')->name('timesheets');

Route::get('/schedule-timing', function () {
    return view(view: 'schedule-timing');
})->middleware('hcm.web.admin')->name('schedule-timing');

Route::get('/shift-master', function () {
    return view(view: 'shift-master');
})->middleware('hcm.web.admin')->name('shift-master');

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
