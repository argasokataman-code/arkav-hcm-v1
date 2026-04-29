<?php

use App\Models\HcmLeaveTypeSetting;
use Illuminate\Support\Facades\Route;

Route::get('/leaves', function () {
    return view(view: 'leaves');
})->middleware('hcm.web.admin')->name('leaves');

Route::get('/leaves-employee', function () {
    return view(view: 'leaves-employee');
})->name('leaves-employee');

Route::get('/leave-request', function () {
    $user = request()->user();
    $activeCompanyId = (int) (request()->attributes->get('activeCompanyId') ?? 0);
    $isAdmin = $user && ($activeCompanyId > 0
        ? $user->isHcmAdminForCompany($activeCompanyId)
        : $user->isHcmAdmin());

    if ($isAdmin) {
        return redirect('/leaves');
    }

    return redirect('/leaves-employee');
})->name('leave-request-legacy');

Route::get('/leave-settings', function () {
    return view(view: 'leave-settings');
})->middleware('hcm.web.admin')->name('leave-settings');

Route::get('/leave-type', function () {
    $leaveTypes = HcmLeaveTypeSetting::query()
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get();

    return view('leave-type', ['leaveTypes' => $leaveTypes]);
})->middleware('hcm.web.admin')->name('leave-type');

Route::get('/holidays', function () {
    return view(view: 'holidays');
})->middleware('hcm.web.admin')->name('holidays');
