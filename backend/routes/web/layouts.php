<?php

use Illuminate\Support\Facades\Route;

Route::get('/layout-horizontal', function () {
    return view(view: 'layout-horizontal');
})->name('layout-horizontal');

Route::get('/layout-detached', function () {
    return view(view: 'layout-detached');
})->name('layout-detached');

Route::get('/layout-modern', function () {
    return view(view: 'layout-modern');
})->name('layout-modern');

Route::get('/layout-two-column', function () {
    return view(view: 'layout-two-column');
})->name('layout-two-column');

Route::get('/layout-horizontal-overlay', function () {
    return view(view: 'layout-horizontal-overlay');
})->name('layout-horizontal-overlay');

Route::get('/layout-hovered', function () {
    return view(view: 'layout-hovered');
})->name('layout-hovered');

Route::get('/layout-box', function () {
    return view(view: 'layout-box');
})->name('layout-box');

Route::get('/layout-horizontal-single', function () {
    return view(view: 'layout-horizontal-single');
})->name('layout-horizontal-single');

Route::get('/layout-horizontal-box', function () {
    return view(view: 'layout-horizontal-box');
})->name('layout-horizontal-box');

Route::get('/layout-horizontal-sidemenu', function () {
    return view(view: 'layout-horizontal-sidemenu');
})->name('layout-horizontal-sidemenu');

Route::get('/layout-vertical-transparent', function () {
    return view(view: 'layout-vertical-transparent');
})->name('layout-vertical-transparent');

Route::get('/layout-without-header', function () {
    return view(view: 'layout-without-header');
})->name('layout-without-header');

Route::get('/layout-rtl', function () {
    return view(view: 'layout-rtl');
})->name('layout-rtl');

Route::get('/layout-dark', function () {
    return view(view: 'layout-dark');
})->name('layout-dark');
