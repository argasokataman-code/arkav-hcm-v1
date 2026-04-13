@extends('layout.mainlayout')

@section('content')
<?php $page = 'saas-subscriptions'; ?>

@section('content')
<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3"><i class="fas fa-credit-card"></i> Subscriptions</h1>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-primary btn-sm" id="btn_add_subscription">
                <i class="fas fa-plus"></i> Add Subscription
            </button>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="row mb-3">
        <div class="col-md-4">
            <input type="text" class="form-control" id="search_subscriptions" placeholder="Search subscriptions...">
        </div>
        <div class="col-md-3">
            <select class="form-select" id="filter_status">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="paused">Paused</option>
                <option value="cancelled">Cancelled</option>
                <option value="expired">Expired</option>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select" id="filter_cycle">
                <option value="">All Cycles</option>
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly</option>
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-outline-secondary w-100" id="btn_reset_filters">
                <i class="fas fa-redo"></i> Reset
            </button>
        </div>
    </div>

    <!-- Subscriptions Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="subscriptions_table">
                <thead class="table-light">
                    <tr>
                        <th>Company</th>
                        <th>Package</th>
                        <th>Status</th>
                        <th>Cycle</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            Loading subscriptions...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-muted" id="subscriptions_info">Loading...</small>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0" id="subscriptions_pagination">
                    <!-- Pagination will be rendered here -->
                </ul>
            </nav>
        </div>
    </div>

    <!-- Add/Edit Subscription Modal -->
    <div class="modal fade" id="subscriptionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="subscriptionModalTitle">Add Subscription</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="subscriptionForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company *</label>
                                <select class="form-select" id="input_subscription_company" required>
                                    <option value="">Select company</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Package *</label>
                                <select class="form-select" id="input_subscription_package" required>
                                    <option value="">Select package</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="input_subscription_start" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Billing Cycle *</label>
                                <select class="form-select" id="input_subscription_cycle" required>
                                    <option value="">Select cycle</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Special Instructions</label>
                            <textarea class="form-control" id="input_subscription_instructions" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Subscription</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Subscription Actions Modal -->
    <div class="modal fade" id="actionsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Subscription Actions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-grid gap-2 mb-3">
                        <button class="btn btn-outline-warning" id="btn_pause_subscription">
                            <i class="fas fa-pause"></i> Pause
                        </button>
                        <button class="btn btn-outline-info" id="btn_resume_subscription">
                            <i class="fas fa-play"></i> Resume
                        </button>
                        <button class="btn btn-outline-danger" id="btn_cancel_subscription">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this subscription? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="btn_confirm_delete">Delete</button>
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

<script src="{{ asset('resources/js/subscriptions-management.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.SubscriptionsManager?.init();
    });
</script>
