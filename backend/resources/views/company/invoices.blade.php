<?php $page = 'company-invoices'; ?>
@extends('layout.mainlayout')

@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content" data-company-invoices-page>

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">My Invoices & Billing</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Invoices</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                <div class="mb-2">
                    <a href="#" class="btn btn-outline-primary d-flex align-items-center">
                        <i class="ti ti-download me-2"></i>Export
                    </a>
                </div>
            </div>
        </div>
        <!-- /Breadcrumb -->

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Total Due</p>
                                <h3 class="mb-0" id="total_due">Rp 0</h3>
                            </div>
                            <i class="ti ti-coin text-primary fs-1 opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Unpaid</p>
                                <h3 class="mb-0" id="count_unpaid">0</h3>
                            </div>
                            <i class="ti ti-alert-hexagon text-warning fs-1 opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Overdue</p>
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
                                <p class="text-muted mb-1">Paid This Month</p>
                                <h3 class="mb-0 text-success" id="paid_this_month">Rp 0</h3>
                            </div>
                            <i class="ti ti-check-circle text-success fs-1 opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" class="form-control" placeholder="Search invoice #..." id="search_invoices">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filter_invoice_status">
                            <option value="">All Status</option>
                            <option value="draft">Draft</option>
                            <option value="sent">Sent</option>
                            <option value="viewed">Viewed</option>
                            <option value="paid">Paid</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filter_invoice_paid">
                            <option value="">All Payments</option>
                            <option value="1">Paid</option>
                            <option value="0">Unpaid</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary w-100" id="btn_reset_invoice_filters">
                            <i class="ti ti-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoices List Container -->
        <div data-company-invoices-list-container>
            <div class="card"><div class="card-body text-center text-muted py-4">
                <i class="ti ti-loader-quarter fs-1 spin"></i> Loading invoices...
            </div></div>
        </div>

        <!-- Info Alert -->
        <div class="alert alert-info mt-3" role="alert">
            <i class="ti ti-info-circle me-2"></i>
            <strong>Need Help?</strong> If you have questions about your invoice, please contact our support team at billing@arcav.com
        </div>

    </div>
</div>
<!-- /Page Wrapper -->

<!-- Invoice Details Modal -->
<div class="modal fade" id="invoiceDetailsModal" tabindex="-1" data-company-invoice-modal>
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Invoice Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p class="text-muted mb-1">Invoice Number</p>
                        <h5 class="mb-0" data-invoice-modal-number>INV-2026-001</h5>
                    </div>
                    <div class="col-md-6">
                        <p class="text-muted mb-1">Status</p>
                        <span class="badge bg-primary" data-invoice-modal-status>Sent</span>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <p class="text-muted mb-1">Company</p>
                        <h6 class="mb-0" data-invoice-modal-company>Company Name</h6>
                    </div>
                    <div class="col-md-6">
                        <p class="text-muted mb-1">Payment Status</p>
                        <span class="badge bg-warning" data-invoice-modal-payment-status>Unpaid</span>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <p class="text-muted mb-1">Issue Date</p>
                        <p class="mb-0" data-invoice-modal-issue-date>2026-04-13</p>
                    </div>
                    <div class="col-md-6">
                        <p class="text-muted mb-1">Due Date</p>
                        <p class="mb-0" data-invoice-modal-due-date>2026-05-13</p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <p class="text-muted mb-1">Amount Due</p>
                        <h4 class="mb-0 text-primary" data-invoice-modal-amount>Rp 5,000,000</h4>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <p class="text-muted mb-1">Notes</p>
                        <p class="mb-0" data-invoice-modal-notes>No notes</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="ti ti-printer me-2"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<style>
    .spin {
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>
<script src="{{ asset('js/company-invoices.js') }}?v={{ time() }}"></script>
@endpush

@endsection
