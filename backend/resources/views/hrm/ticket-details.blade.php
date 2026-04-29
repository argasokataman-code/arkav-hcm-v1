<?php $page = 'ticket-details'; ?>
@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper">
    <div class="content" data-tickets-page="detail" data-ticket-id="{{ (int) ($ticketId ?? 0) }}">
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="mb-2">
                <h6 class="fw-medium d-flex align-items-center"><a href="{{url('tickets')}}"><i class="ti ti-arrow-left me-2"></i>Ticket Details</a></h6>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                <div class="me-2 mb-2">
                    <button type="button" class="btn btn-white d-inline-flex align-items-center" data-ticket-export="single-csv">
                        <i class="ti ti-file-export me-1"></i>Export CSV
                    </button>
                </div>
                <div class="mb-2">
                    <button type="button" class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#arcav_ticket_create_modal">
                        <i class="ti ti-circle-plus me-2"></i>Add Ticket
                    </button>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-9 col-md-8" data-ticket-detail-main>
                <div class="card"><div class="card-body text-center text-muted py-4">Memuat detail ticket...</div></div>
            </div>
            <div class="col-xl-3 col-md-4" data-ticket-detail-side>
                <div class="card"><div class="card-body text-center text-muted py-4">Memuat metadata...</div></div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12" data-ticket-detail-actions></div>
        </div>
    </div>
</div>
@include('hcm.partials.ticket-modals')
@component('components.modal-popup')@endcomponent
@endsection
