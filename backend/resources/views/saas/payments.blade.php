<?php $page = 'saas-payments'; ?>
@extends('layout.mainlayout')

@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content" data-saas-payments-page>

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">SaaS Payments</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">SaaS</li>
                        <li class="breadcrumb-item active" aria-current="page">Payments</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                <div class="mb-2">
                    <button class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#paymentModal" id="btn_add_payment">
                        <i class="ti ti-circle-plus me-2"></i>Record Payment
                    </button>
                </div>
            </div>
        </div>
        <!-- /Breadcrumb -->

        <!-- Filter Card -->
        <div class="card">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" class="form-control" placeholder="Search payments..." id="search_payments" data-payment-filter-search>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filter_status" data-payment-filter-status>
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                            <option value="disputed">Disputed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filter_method" data-payment-filter-method>
                            <option value="">All Methods</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="e_wallet">E-Wallet</option>
                            <option value="cash">Cash</option>
                            <option value="check">Check</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary w-100" id="btn_reset_filters">
                            <i class="ti ti-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payments List Container -->
        <div data-payments-list-container>
            <div class="card"><div class="card-body text-center text-muted py-4">Loading payments...</div></div>
        </div>

    </div>
</div>
<!-- /Page Wrapper -->

<!-- Record Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentModalTitle">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="paymentForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Company *</label>
                        <select class="form-select" id="input_payment_company_id" required>
                            <option value="">Loading companies...</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount *</label>
                            <input type="number" class="form-control" id="input_payment_amount" step="0.01" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Currency *</label>
                            <select class="form-select" id="input_payment_currency" required>
                                <option value="IDR">IDR</option>
                                <option value="USD">USD</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Method *</label>
                            <select class="form-select" id="input_payment_method" required>
                                <option value="">Select method</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="e_wallet">E-Wallet</option>
                                <option value="cash">Cash</option>
                                <option value="check">Check</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Gateway</label>
                            <input type="text" class="form-control" id="input_payment_gateway" placeholder="e.g., Midtrans">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gateway Reference</label>
                            <input type="text" class="form-control" id="input_payment_gateway_ref" placeholder="e.g., Transaction ID">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="input_payment_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this payment? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btn_confirm_delete">Delete</button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('build/js/saas/payments-management.js') }}?v={{ filemtime(public_path('build/js/saas/payments-management.js')) }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.PaymentsManager?.init?.();
    });
</script>

@endsection
