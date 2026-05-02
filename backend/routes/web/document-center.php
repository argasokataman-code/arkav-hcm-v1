<?php

use Illuminate\Support\Facades\Route;

Route::get('/document-center', function () {
    return view('document-center');
})->middleware('hcm.web.feature:employee_document_center')->name('document-center');
