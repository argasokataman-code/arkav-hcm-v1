<?php

use Illuminate\Support\Facades\Route;

Route::get('/employees', function () {
    return view(view: 'employees');
})->middleware('hcm.web.admin')->name('employees');

Route::get('/employees-grid', function () {
    return view(view: 'employees-grid');
})->middleware('hcm.web.admin')->name('employees-grid');

Route::get('/employee-details', function () {
    return view(view: 'employee-details');
})->name('employee-details');

Route::get('/departments', function () {
    return view(view: 'departments');
})->middleware('hcm.web.admin')->name('departments');

Route::get('/designations', function () {
    return view(view: 'designations');
})->middleware('hcm.web.admin')->name('designations');

Route::get('/teams', function () {
    return view(view: 'teams');
})->middleware('hcm.web.admin')->name('teams');

Route::get('/teams/{id}/members', function (string $id) {
    return view(view: 'team-members', data: ['teamId' => $id]);
})->middleware('hcm.web.admin')->name('team-members');

Route::get('/policy', function () {
    return view(view: 'policy');
})->middleware('hcm.web.admin')->name('policy');
