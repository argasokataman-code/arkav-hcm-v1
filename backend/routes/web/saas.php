<?php

use Illuminate\Support\Facades\Route;

Route::get('/saas/packages', function () {
    return view('saas.packages');
})->middleware('hcm.web.global-admin')->name('saas.packages');

Route::get('/saas/subscriptions', function () {
    return view('saas.subscriptions');
})->middleware('hcm.web.global-admin')->name('saas.subscriptions');

Route::get('/saas/billing-overview', function () {
    return view('saas.billing-overview');
})->middleware('hcm.web.global-admin')->name('saas.billing-overview');

Route::get('/saas/billing-overview/invoices/{invoice}', function (\App\Models\Invoice $invoice) {
    return view('saas.billing-overview-invoice-detail', ['invoice' => $invoice]);
})->middleware('hcm.web.global-admin')->name('saas.billing-overview.invoice-detail');

Route::get('/saas/domains', function () {
    return view('saas.domains');
})->middleware('hcm.web.global-admin')->name('saas.domains');

Route::get('/saas/transactions', function () {
    return view('saas.transactions');
})->middleware('hcm.web.global-admin')->name('saas.transactions');

Route::get('/saas/invoices', function () {
    return view('saas.invoices');
})->middleware('hcm.web.global-admin')->name('saas.invoices');

Route::get('/saas/payments', function () {
    return view('saas.payments');
})->middleware('hcm.web.global-admin')->name('saas.payments');

Route::get('/saas/reports', function () {
    return view('saas.reports');
})->middleware('hcm.web.global-admin')->name('saas.reports');

Route::get('/saas/reminders', function () {
    return view('saas.reminders');
})->middleware('hcm.web.global-admin')->name('saas.reminders');

Route::get('/saas/platform-tax', function () {
    return view('saas.platform-tax');
})->middleware('hcm.web.global-admin')->name('saas.platform-tax');

Route::get('/saas/pricing', function () {
    return view('tax-billing-subscribers-settings', [
        'taxGovernanceScreen' => 'platform-billing',
    ]);
})->middleware('hcm.web.global-admin')->name('saas.pricing');

Route::get('/saas/pricing/reports', function () {
    return view('tax-billing-subscribers-settings', [
        'taxGovernanceScreen' => 'platform-billing',
    ]);
})->middleware('hcm.web.global-admin')->name('saas.pricing.reports');

Route::get('/companies', function () {
    return view('crm.companies');
})->middleware('hcm.web.global-admin')->name('companies');

Route::get('/packages-grid', function () {
    return view('administration.packages.packages-grid');
})->middleware('hcm.web.global-admin')->name('packages-grid');

Route::get('/domain', function () {
    return view('saas.domains');
})->middleware('hcm.web.global-admin')->name('domain');

Route::get('/purchase-transaction', function () {
    return view('saas.transactions');
})->middleware('hcm.web.global-admin')->name('purchase-transaction');

Route::get('/currencies', function () {
    return view(view: 'currencies');
})->middleware('hcm.web.global-admin')->name('currencies');
