<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Billing\InvoiceController;

Route::post('/invoices', [InvoiceController::class, 'create'])->name('api.invoices.create');