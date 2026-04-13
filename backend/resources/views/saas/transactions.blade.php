<?php $page = 'saas-transactions'; ?>
@extends('layout.mainlayout')

@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content" data-saas-transactions-page>

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Purchase Transactions</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">SaaS</li>
                        <li class="breadcrumb-item active" aria-current="page">Transactions</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                <div class="mb-2">
                    <button class="btn btn-primary d-flex align-items-center" id="btn_download_all">
                        <i class="ti ti-download me-2"></i>Download All
                    </button>
                </div>
            </div>
        </div>
        <!-- /Breadcrumb -->

        <!-- Search & Filters -->
        <div class="card">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-2">
                        <input type="text" class="form-control" id="search_invoice_number" placeholder="Invoice #" data-transaction-filter-invoice>
                    </div>
                    <div class="col-md-2">
                        <input type="text" class="form-control" id="search_company" placeholder="Company" data-transaction-filter-company>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="filter_status" data-transaction-filter-status>
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="filter_payment_method" data-transaction-filter-method>
                            <option value="">All Methods</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="e_wallet">E-Wallet</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control" id="filter_date_from" data-transaction-filter-date>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary w-100" id="btn_reset_filters">
                            <i class="ti ti-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions List Container -->
        <div data-transactions-list-container>
            <div class="card"><div class="card-body text-center text-muted py-4">Loading transactions...</div></div>
        </div>

    </div>
</div>
<!-- /Page Wrapper -->

<!-- Transaction Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="details_content"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btn_download_invoice">
                    <i class="ti ti-download"></i> Download PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Processing Modal -->
<div class="modal fade" id="processingModal" tabindex="-1" role="dialog" data-bs-backdrop="static">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Processing...</h5>
            </div>
            <div class="modal-body">
                <div class="alert alert-info" role="alert">
                    <i class="ti ti-loader"></i> <span id="processing_message">Processing your request...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('resources/js/purchase-transactions-data.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.TransactionsManager?.init?.();
    });
</script>

@endsection
