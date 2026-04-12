<?php $page = 'tickets'; ?>
@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper">
    <div class="content" data-tickets-page="list" data-ticket-mode="{{ $ticketMode ?? 'admin' }}">
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">{{ $ticketTitle ?? 'Tickets' }}</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item">HCM</li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $ticketTitle ?? 'Tickets' }}</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                <div class="me-2 mb-2">
                    <div class="d-flex align-items-center border bg-white rounded p-1 me-2 icon-list">
                        <a href="{{url('tickets-admin')}}" class="btn btn-icon btn-sm active bg-primary text-white me-1"><i class="ti ti-list-tree"></i></a>
                        <a href="{{url('tickets-grid')}}" class="btn btn-icon btn-sm"><i class="ti ti-layout-grid"></i></a>
                    </div>
                </div>
                <div class="me-2 mb-2">
                    <button type="button" class="btn btn-white d-inline-flex align-items-center" data-ticket-export="csv">
                        <i class="ti ti-file-export me-1"></i>Export CSV
                    </button>
                </div>
                <div class="mb-2">
                    <button type="button" class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#arcav_ticket_create_modal">
                        <i class="ti ti-circle-plus me-2"></i>Add New Ticket
                    </button>
                </div>
            </div>
        </div>

        <div class="row" data-ticket-summary-cards>
            <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><p class="fw-medium fs-12 mb-1">Total</p><h4 data-ticket-summary-total>0</h4></div></div></div>
            <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><p class="fw-medium fs-12 mb-1">Open</p><h4 data-ticket-summary-open>0</h4></div></div></div>
            <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><p class="fw-medium fs-12 mb-1">In Progress</p><h4 data-ticket-summary-progress>0</h4></div></div></div>
            <div class="col-xl-3 col-md-6 d-flex"><div class="card flex-fill"><div class="card-body"><p class="fw-medium fs-12 mb-1">Resolved/Closed</p><h4 data-ticket-summary-done>0</h4></div></div></div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4"><input class="form-control" placeholder="Cari subject / kode..." data-ticket-filter-q></div>
                    <div class="col-md-3">
                        <select class="form-select" data-ticket-filter-status>
                            <option value="">Semua status</option><option value="open">Open</option><option value="in_progress">In Progress</option><option value="resolved">Resolved</option><option value="closed">Closed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" data-ticket-filter-priority>
                            <option value="">Semua prioritas</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button type="button" class="btn btn-outline-primary w-100" data-ticket-filter-apply>Apply</button></div>
                </div>
            </div>
        </div>

        <div data-ticket-list-container>
            <div class="card"><div class="card-body text-center text-muted py-4">Memuat tickets...</div></div>
        </div>
    </div>
</div>

@include('hcm.partials.ticket-modals')
@component('components.modal-popup')@endcomponent

@endsection