<?php $page = 'tickets-grid'; ?>
@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper">
    <div class="content" data-tickets-page="grid">
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Tickets Grid</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item">Employee</li>
                        <li class="breadcrumb-item active" aria-current="page">Tickets Grid</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                <div class="me-2 mb-2">
                    <div class="d-flex align-items-center border bg-white rounded p-1 me-2 icon-list">
                        <a href="{{url('tickets')}}" class="btn btn-icon btn-sm me-1"><i class="ti ti-list-tree"></i></a>
                        <a href="{{url('tickets-grid')}}" class="btn btn-icon btn-sm active bg-primary text-white"><i class="ti ti-layout-grid"></i></a>
                    </div>
                </div>
                <div class="me-2 mb-2">
                    <button type="button" class="btn btn-white d-inline-flex align-items-center" data-ticket-export="csv">
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
        <div class="row" data-ticket-grid-container>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center text-muted py-4">Memuat tickets...</div>
                </div>
            </div>
        </div>
    </div>
</div>
@include('hcm.partials.ticket-modals')
@component('components.modal-popup')@endcomponent
@endsection
