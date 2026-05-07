<?php

use Illuminate\Support\Facades\Route;

Route::get('/overtime', function () {
    return view('overtime', ['arcavOvertimeEmployeeOnly' => false]);
})->middleware('hcm.web.admin')->name('overtime');

Route::get('/overtime-employee', function () {
    return view('overtime', ['arcavOvertimeEmployeeOnly' => true]);
})->middleware('hcm.web.employee:overtime')->name('overtime-employee');

Route::get('/overtime-request', function () {
    $user = request()->user();
    $activeCompanyId = (int) (request()->attributes->get('activeCompanyId') ?? 0);
    $isAdmin = $user && ($activeCompanyId > 0
        ? $user->isHcmAdminForCompany($activeCompanyId)
        : $user->isHcmAdmin());

    if ($isAdmin) {
        return redirect('/overtime');
    }

    return redirect('/overtime-employee');
})->name('overtime-request-legacy');

Route::get('/overtime-master', function () {
    return view(view: 'overtime-master');
})->middleware('hcm.web.admin')->name('overtime-master');
