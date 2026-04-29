<?php

use Illuminate\Support\Facades\Route;

Route::get('/index', function () {
    return view('misc.index');
})->middleware('hcm.web.admin')->name('index');

Route::get('/employee-dashboard', function () {
    return view('hrm.employee-dashboard');
})->name('employee-dashboard');

Route::get('/dashboard', function () {
    return view('saas.saas-dashboard');
})->middleware('hcm.web.global-admin')->name('dashboard');

Route::get('/saas-dashboard', function () {
    return view('saas.saas-dashboard');
})->middleware('hcm.web.global-admin')->name('saas-dashboard');

Route::get('/activity', function () {
    return view('administration.super-admin.activity');
})->middleware('hcm.web.primary-super-admin')->name('activity');
