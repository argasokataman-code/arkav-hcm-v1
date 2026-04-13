<?php $page = 'saas-subscriptions'; ?>
@extends('layout.mainlayout')

@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content" data-saas-subscriptions-page>

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">SaaS Subscriptions</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">SaaS</li>
                        <li class="breadcrumb-item active" aria-current="page">Subscriptions</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                <div class="mb-2">
                    <button class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#subscriptionModal" id="btn_add_subscription">
                        <i class="ti ti-circle-plus me-2"></i>Add Subscription
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
                        <input type="text" class="form-control" placeholder="Search subscriptions..." id="search_subscriptions" data-subscription-filter-search>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filter_status" data-subscription-filter-status>
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="paused">Paused</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="filter_cycle" data-subscription-filter-cycle>
                            <option value="">All Cycles</option>
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-secondary w-100" id="btn_reset_filters">
                            <i class="ti ti-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subscriptions List Container -->
        <div data-subscriptions-list-container>
            <div class="card"><div class="card-body text-center text-muted py-4">Loading subscriptions...</div></div>
        </div>

    </div>
</div>
<!-- /Page Wrapper -->

<!-- Add/Edit Subscription Modal -->
<div class="modal fade" id="subscriptionModal" tabindex="-1" role="dialog" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg" role="document">
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
                            <select class="form-select" id="input_subscription_company" required></select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Package *</label>
                            <select class="form-select" id="input_subscription_package" required></select>
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
<div class="modal fade" id="actionsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Subscription Actions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-grid gap-2 mb-3">
                    <button class="btn btn-outline-warning" id="btn_pause_subscription">
                        <i class="ti ti-pause"></i> Pause
                    </button>
                    <button class="btn btn-outline-info" id="btn_resume_subscription">
                        <i class="ti ti-play"></i> Resume
                    </button>
                    <button class="btn btn-outline-danger" id="btn_cancel_subscription">
                        <i class="ti ti-x"></i> Cancel
                    </button>
                </div>
            </div>
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
                <p>Are you sure you want to delete this subscription? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btn_confirm_delete">Delete</button>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('resources/js/subscriptions-management.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.SubscriptionsManager?.init?.();
    });
</script>

@endsection
