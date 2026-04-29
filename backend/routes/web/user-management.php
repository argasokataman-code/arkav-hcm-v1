<?php

use Illuminate\Support\Facades\Route;

Route::get('roles-permissions', function () {
    return view('administration.rbac.roles-permissions');
})->middleware('hcm.web.admin')->name('roles-permissions');

Route::get('permission', function () {
    return view('administration.rbac.permission');
})->middleware('hcm.web.admin')->name('permission');

Route::get('users', function () {
    return view('administration.rbac.users');
})->middleware('hcm.web.admin')->name('users');
