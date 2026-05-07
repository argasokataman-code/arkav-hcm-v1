<?php

use Illuminate\Support\Facades\Route;

Route::get('/tickets', function () {
    $user = request()->user();
    $activeCompanyId = (int) (request()->attributes->get('activeCompanyId') ?? 0);
    $isAdmin = $user && ($activeCompanyId > 0
        ? $user->isHcmAdminForCompany($activeCompanyId)
        : $user->isHcmAdmin());

    if ($isAdmin) {
        return redirect('/tickets-admin');
    }

    return redirect('/tickets-employee');
})->name('tickets');

Route::get('/tickets-admin', function () {
    return view(view: 'tickets', data: [
        'ticketMode' => 'admin',
        'ticketTitle' => 'Tickets (Admin)',
    ]);
})->middleware(['hcm.web.admin', 'hcm.web.feature:tickets'])->name('tickets-admin');

Route::get('/tickets-employee', function () {
    return view(view: 'tickets', data: [
        'ticketMode' => 'employee',
        'ticketTitle' => 'Tickets (Employee)',
    ]);
})->middleware(['hcm.web.feature:tickets', 'hcm.web.employee:tickets-admin'])->name('tickets-employee');

Route::get('/ticket-master', function () {
    return view(view: 'ticket-master');
})->middleware(['hcm.web.admin', 'hcm.web.feature:tickets'])->name('ticket-master');

Route::get('/tickets-grid', function () {
    return view(view: 'tickets-grid');
})->name('tickets-grid');

Route::get('/ticket-details', function () {
    $user = request()->user();
    $activeCompanyId = (int) (request()->attributes->get('activeCompanyId') ?? 0);
    $isAdmin = $user && ($activeCompanyId > 0
        ? $user->isHcmAdminForCompany($activeCompanyId)
        : $user->isHcmAdmin());

    if ($isAdmin) {
        return redirect('/tickets-admin');
    }

    return redirect('/tickets-employee');
})->name('ticket-details-legacy');

Route::get('/ticket-details/{id}', function (int $id) {
    return view(view: 'ticket-details', data: ['ticketId' => $id]);
})->whereNumber('id')->name('ticket-details');
