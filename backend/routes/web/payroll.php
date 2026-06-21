<?php

use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('hcm.web.admin')->group(function (): void {
    Route::middleware('hcm.web.feature:payroll')->group(function (): void {
        Route::get('/salary-component-master', function () {
            return view('finance.salary-component-master');
        })->name('salary-component-master');

        Route::get('/employee-salary', function () {
            return view('hrm.employee-salary');
        })->name('employee-salary');

        Route::get('/payroll-overtime', function () {
            return view('finance.payroll-overtime');
        })->name('payroll-overtime');

        Route::get('/payroll-thr', function () {
            return view('finance.payroll-thr');
        })->name('payroll-thr');

        Route::get('/payroll-pkwt-compensation', function () {
            return view('finance.payroll-pkwt-compensation');
        })->name('payroll-pkwt-compensation');

        Route::get('/payroll-run', function () {
            return view('finance.payroll-run');
        })->name('payroll-run');

        Route::get('/payroll-run-history', function () {
            return view('finance.payroll-run-history');
        })->name('payroll-run-history');
    });
});

Route::get('payslip', function (Request $request) {
    $companyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
    $company = $companyId > 0 ? Company::find($companyId) : null;
    $companyAddress = '';
    if ($company !== null) {
        $companyAddress = (string) (CompanySetting::query()
            ->where('company_id', $company->id)
            ->where('key', 'company_profile_address')
            ->value('value') ?? '');
    }

    $role = strtolower((string) ($request->attributes->get('activeCompanyRole', '')));
    $isEmployee = in_array($role, ['employee', 'member'], true);

    return view('finance.payslip', [
        'companyName' => $company?->name ?? '',
        'companyAddress' => $companyAddress,
        'isEmployee' => $isEmployee,
        'pageTitle' => $isEmployee ? 'My Payslip' : 'Payslips',
    ]);
})->name('payslip');
