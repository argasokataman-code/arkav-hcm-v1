<?php $page = 'saas-reminders'; ?>
@extends('layout.mainlayout')

@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content" data-saas-reminders-page>

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Payment Reminders</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">SaaS</li>
                        <li class="breadcrumb-item active" aria-current="page">Reminders</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                <div class="mb-2">
                    <button class="btn btn-primary d-flex align-items-center" id="btn_send_reminders">
                        <i class="ti ti-bell me-2"></i>Send Reminders Now
                    </button>
                </div>
            </div>
        </div>
        <!-- /Breadcrumb -->

        <!-- Summary Section -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Overdue Invoices</p>
                                <h3 class="mb-0 text-danger" id="count_overdue">0</h3>
                            </div>
                            <i class="ti ti-alert-circle text-danger fs-1 opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Due Soon (7 days)</p>
                                <h3 class="mb-0 text-warning" id="count_due_soon">0</h3>
                            </div>
                            <i class="ti ti-calendar-event text-warning fs-1 opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Overdue Amount</p>
                                <h3 class="mb-0 text-danger" id="amount_overdue">Rp 0</h3>
                            </div>
                            <i class="ti ti-coin text-danger fs-1 opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Last Sent</p>
                                <h5 class="mb-0" id="last_reminder_sent">Never</h5>
                            </div>
                            <i class="ti ti-send text-primary fs-1 opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-3">
                        <select class="form-select" id="filter_reminder_type" data-reminder-filter-type>
                            <option value="">All Types</option>
                            <option value="overdue">Overdue</option>
                            <option value="due_soon">Due Soon</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control" placeholder="Search company..." id="search_reminders">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary w-100" id="btn_reset_reminder_filters">
                            <i class="ti ti-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reminders List Container -->
        <div data-reminders-list-container>
            <div class="card"><div class="card-body text-center text-muted py-4">Loading reminders...</div></div>
        </div>

    </div>
</div>
<!-- /Page Wrapper -->

@push('scripts')
<script src="{{ asset('js/reminders-management.js') }}?v={{ time() }}"></script>
@endpush

@endsection
