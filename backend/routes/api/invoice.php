<?php

use App\Http\Controllers\Api\Billing\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::post('/invoices', [InvoiceController::class, 'create'])->name('api.invoices.create');
