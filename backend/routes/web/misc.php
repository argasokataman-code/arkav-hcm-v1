<?php

use Illuminate\Support\Facades\Route;

// Invoices (generic, not HCM-specific)
Route::get('/invoices', function () { return view('finance.invoices'); })->name('invoices');
Route::get('/add-invoices', function () { return view('finance.add-invoices'); })->name('add-invoices');
Route::get('/edit-invoices', function () { return view('finance.edit-invoices'); })->name('edit-invoices');
Route::get('/invoice-details', function () { return view('finance.invoice-details'); })->name('invoice-details');
Route::get('/invoice', function () { return view(view: 'invoice'); })->name('invoice');

// Finance/budget stubs
Route::get('/budget-expenses', function () { return view('finance.budget-expenses'); })->name('budget-expenses');
Route::get('/budget-revenues', function () { return view('finance.budget-revenues'); })->name('budget-revenues');
Route::get('/budgets', function () { return view('finance.budgets'); })->name('budgets');
Route::get('/categories', function () { return view('finance.categories'); })->name('categories');
Route::get('/taxes', function () { return view('finance.taxes'); })->name('taxes');
Route::get('/provident-fund', function () { return view('finance.provident-fund'); })->name('provident-fund');
Route::get('/expenses', function () { return view('finance.expenses'); })->name('expenses');
Route::get('/payments', function () { return view('finance.payments'); })->name('payments');
Route::get('/estimates', function () { return view('finance.estimates'); })->name('estimates');

// CRM / Clients
Route::get('/clients-grid', function () { return view(view: 'clients-grid'); })->name('clients-grid');
Route::get('/clients', function () { return view(view: 'clients'); })->name('clients');
Route::get('/client-details', function () { return view(view: 'client-details'); })->name('client-details');
Route::get('/contacts-grid', function () { return view(view: 'contacts-grid'); })->name('contacts-grid');
Route::get('/contacts', function () { return view(view: 'contacts'); })->name('contacts');
Route::get('/contact-details', function () { return view(view: 'contact-details'); })->name('contact-details');
Route::get('/companies-grid', function () { return view(view: 'companies-grid'); })->name('companies-grid');
Route::get('/companies-crm', function () { return view(view: 'companies-crm'); })->name('companies-crm');
Route::get('/company-details', function () { return view(view: 'company-details'); })->name('company-details');

// Recruitment stubs
Route::get('/job-grid', function () { return view(view: 'job-grid'); })->name('job-grid');
Route::get('/job-list', function () { return view(view: 'job-list'); })->name('job-list');
Route::get('/job-details', function () { return view(view: 'job-details'); })->name('job-details');
Route::get('/candidates-grid', function () { return view(view: 'candidates-grid'); })->name('candidates-grid');
Route::get('/candidates', function () { return view(view: 'candidates'); })->name('candidates');
Route::get('/candidates-kanban', function () { return view(view: 'candidates-kanban'); })->name('candidates-kanban');
Route::get('/refferals', function () { return view(view: 'refferals'); })->name('refferals');
Route::get('/aptitude-result', function () { return view(view: 'aptitude-result'); })->name('aptitude-result');
Route::get('/shortlist-candidates', function () { return view('recruitment.shortlist-candidates'); })->name('shortlist-candidates');
Route::get('/offer-approvals', function () { return view('recruitment.offer-approvals'); })->name('offer-approvals');
Route::get('/experience-level', function () { return view(view: 'experience-level'); })->name('experience-level');

// Blog / content (primary-super-admin)
Route::get('/blogs', function () { return view('content.blogs'); })->middleware('hcm.web.primary-super-admin')->name('blogs');
Route::get('/blog-2', function () { return view(view: 'blog-2'); })->name('blog-2');
Route::get('/blog-categories', function () { return view('content.blog-categories'); })->name('blog-categories');
Route::get('/blog-comments', function () { return view('content.blog-comments'); })->name('blog-comments');
Route::get('/blog-tags', function () { return view('content.blog-tags'); })->name('blog-tags');

// Content management (primary-super-admin)
Route::get('/pages', function () { return view('content.pages'); })->middleware('hcm.web.primary-super-admin')->name('pages');
Route::get('/testimonials', function () { return view('content.testimonials'); })->middleware('hcm.web.primary-super-admin')->name('testimonials');

// Misc public/shared pages
Route::get('/terms-condition', function () { return view('misc.terms-condition'); })->name('terms-condition');
Route::get('/privacy-policy', function () { return view('misc.privacy-policy'); })->name('privacy-policy');
Route::get('/faq', function () { return view('content.faq'); })->name('faq');
Route::get('/api-keys', function () { return view('misc.api-keys'); })->name('api-keys');

Route::get('/timeline', function () { return view('misc.timeline'); })->name('timeline');
Route::get('/search-result', function () { return view('misc.search-result'); })->name('search-result');
Route::get('/gallery', function () { return view('misc.gallery'); })->name('gallery');
Route::get('/profile', function () { return view('misc.profile'); })->name('profile');
Route::get('/starter', function () { return view('misc.starter'); })->name('starter');
