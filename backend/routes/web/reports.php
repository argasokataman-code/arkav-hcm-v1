<?php

use Illuminate\Support\Facades\Route;

Route::middleware('hcm.web.admin')->group(function (): void {
    Route::get('/expenses-report', function () {
        return view('finance.expenses-report');
    })->name('expenses-report');

    Route::get('/invoice-report', function () {
        return view('finance.invoice-report');
    })->name('invoice-report');

    Route::get('/payment-report', function () {
        return view('finance.payment-report');
    })->name('payment-report');

    Route::get('user-report', function () {
        return view('reports.user-report');
    })->name('user-report');

    Route::get('employee-report', function () {
        return view('hrm.employee-report');
    })->name('employee-report');

    Route::get('payslip-report', function () {
        return view('finance.payslip-report');
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
