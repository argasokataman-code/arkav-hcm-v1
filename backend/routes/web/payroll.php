<?php

use Illuminate\Support\Facades\Route;

Route::middleware('hcm.web.admin')->group(function (): void {
    Route::middleware('hcm.web.feature:payroll')->group(function (): void {
        Route::get('/salary-component-master', function () {
            return view('finance.salary-component-master');
        })->name('salary-component-master');

        Route::get('/employee-salary', function () {
            return view('hrm.employee-salary');
        })->name('employee-salary');

        Route::get('/payroll', function () {
            return view('finance.payroll');
        })->name('payroll');

        Route::get('/payroll-overtime', function () {
            return view('finance.payroll-overtime');
        })->name('payroll-overtime');

        Route::get('/payroll-deduction', function () {
            return view('finance.payroll-deduction');
        })->name('payroll-deduction');

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

Route::get('payslip', function () {
    return view('finance.payslip');
})->name('payslip');
