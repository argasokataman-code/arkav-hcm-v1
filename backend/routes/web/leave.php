<?php

use App\Models\HcmLeaveTypeSetting;
use Illuminate\Support\Facades\Route;

Route::middleware(['hcm.web.admin', 'hcm.web.feature:leave_management'])->group(function (): void {
    Route::get('/leaves', function () {
        return view(view: 'leaves');
    })->name('leaves');
});

Route::get('/leaves-employee', function () {
    return view(view: 'leaves-employee');
})->middleware(['hcm.web.feature:leave_management', 'hcm.web.employee:leaves'])->name('leaves-employee');

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

Route::middleware(['hcm.web.admin', 'hcm.web.feature:leave_management'])->group(function (): void {
    Route::get('/leave-settings', function () {
        return view(view: 'leave-settings');
    })->name('leave-settings');
});

Route::get('/leave-type', function () {
    $companyId = request()->attributes->get('activeCompanyId');
    $leaveTypes = HcmLeaveTypeSetting::query()
        ->where('company_id', $companyId)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get();

    return view('leave-type', ['leaveTypes' => $leaveTypes]);
})->middleware(['hcm.web.admin', 'hcm.web.feature:leave_management'])->name('leave-type');

Route::get('/holidays', function () {
    return view(view: 'holidays');
})->middleware(['hcm.web.admin', 'hcm.web.feature:holiday_calendar'])->name('holidays');
