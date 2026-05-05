<?php

use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('hcm.web.admin')->group(function (): void {
    // expenses-report hidden (not in use)

    Route::get('/invoice-report', function () {
        return view('finance.invoice-report');
    })->name('invoice-report');

    Route::get('/payment-report', function () {
        return view('finance.payment-report');
    })->middleware('hcm.web.global-admin')->name('payment-report');

    Route::get('user-report', function () {
        return view('reports.user-report');
    })->name('user-report');

    Route::get('employee-report', function () {
        return view('hrm.employee-report');
    })->name('employee-report');

    Route::get('payslip-report', function (Request $request) {
        $companyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        $company = $companyId > 0 ? Company::find($companyId) : null;
        $companyAddress = '';
        if ($company !== null) {
            $companyAddress = (string) (CompanySetting::query()
                ->where('company_id', $company->id)
                ->where('key', 'company_profile_address')
                ->value('value') ?? '');
        }

        return view('finance.payslip-report', [
            'companyName' => $company?->name ?? '',
            'companyAddress' => $companyAddress,
        ]);
    })->name('payslip-report');

    Route::get('attendance-report', function () {
        return view('hrm.attendance-report');
    })->name('attendance-report');

    Route::get('leave-report', function () {
        return view('hrm.leave-report');
    })->name('leave-report');

    Route::get('daily-report', function () {
        return view('reports.daily-report');
    })->name('daily-report');

    Route::get('reports', function () {
        return view('reports.hub');
    })->name('reports-hub');
});
