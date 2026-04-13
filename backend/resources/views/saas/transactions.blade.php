@extends('layout.mainlayout')

@section('content')
<?php $page = 'saas-transactions'; ?>

@section('content')
<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3"><i class="fas fa-receipt"></i> Purchase Transactions</h1>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-primary btn-sm" id="btn_download_all">
                <i class="fas fa-download"></i> Download All
            </button>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-2 mb-3">
                    <input type="text" class="form-control" id="search_invoice_number" placeholder="Invoice #">
                </div>
                <div class="col-md-2 mb-3">
                    <input type="text" class="form-control" id="search_company" placeholder="Company">
                </div>
                <div class="col-md-2 mb-3">
                    <select class="form-select" id="filter_status">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <select class="form-select" id="filter_payment_method">
                        <option value="">All Methods</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="e_wallet">E-Wallet</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <input type="date" class="form-control" id="filter_date_from" placeholder="From Date">
                </div>
                <div class="col-md-2 mb-3">
                    <button class="btn btn-outline-secondary w-100" id="btn_reset_filters">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="transactions_table">
                <thead class="table-light">
                    <tr>
                        <th>Invoice</th>
                        <th>Company</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            Loading transactions...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-muted" id="transactions_info">Loading...</small>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0" id="transactions_pagination">
                    <!-- Pagination will be rendered here -->
                </ul>
            </nav>
        </div>
    </div>

    <!-- Transaction Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Transaction Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="details_content">
                    <!-- Details will be rendered here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="btn_download_invoice">
                        <i class="fas fa-download"></i> Download PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Processing Transactions Modal -->
    <div class="modal fade" id="processingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Processing Transaction</h5>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-spinner fa-spin me-2"></i>
                        <span id="processing_message">Processing your request...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11" data-toast-container>
    <div id="toast_notification" class="toast" role="alert">
        <div class="toast-header">
            <strong class="me-auto" id="toast_title">Notification</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" id="toast_message">
            <!-- Message will be rendered here -->
        </div>
    </div>
</div>

<script src="{{ asset('resources/js/purchase-transactions-data.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.TransactionsManager?.init();
    });
</script>
