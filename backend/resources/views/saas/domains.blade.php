@extends('layout.mainlayout')

@section('content')
<?php $page = 'saas-domains'; ?>

@section('content')
<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3"><i class="fas fa-globe"></i> Domain Management</h1>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-primary btn-sm" id="btn_add_domain">
                <i class="fas fa-plus"></i> Add Domain
            </button>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="row mb-3">
        <div class="col-md-4">
            <input type="text" class="form-control" id="search_domains" placeholder="Search domains...">
        </div>
        <div class="col-md-3">
            <select class="form-select" id="filter_status">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="verified">Verified</option>
                <option value="failed">Failed</option>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select" id="filter_company">
                <option value="">All Companies</option>
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-outline-secondary w-100" id="btn_reset_filters">
                <i class="fas fa-redo"></i> Reset
            </button>
        </div>
    </div>

    <!-- Domains Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="domains_table">
                <thead class="table-light">
                    <tr>
                        <th>Domain Name</th>
                        <th>Company</th>
                        <th>Status</th>
                        <th>Verification Type</th>
                        <th>Verified At</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            Loading domains...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-muted" id="domains_info">Loading...</small>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0" id="domains_pagination">
                    <!-- Pagination will be rendered here -->
                </ul>
            </nav>
        </div>
    </div>

    <!-- Add/Edit Domain Modal -->
    <div class="modal fade" id="domainModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="domainModalTitle">Add Domain</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="domainForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company *</label>
                                <select class="form-select" id="input_domain_company" required>
                                    <option value="">Select company</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Domain Name *</label>
                                <input type="text" class="form-control" id="input_domain_name" placeholder="example.com" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Verification Type *</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="verification_type" id="verification_dns" value="dns" required>
                                    <label class="form-check-label" for="verification_dns">
                                        DNS Record
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="verification_type" id="verification_file" value="file">
                                    <label class="form-check-label" for="verification_file">
                                        File Upload
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="input_domain_notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Domain</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Verification Instructions Modal -->
    <div class="modal fade" id="verificationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Verification Instructions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="verification_instructions">
                    <!-- Instructions will be rendered here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="btn_verify_domain">
                        <i class="fas fa-check"></i> Verify Now
                    </button>
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
                    <p>Are you sure you want to delete this domain? This action cannot be undone.</p>
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

<script src="{{ asset('resources/js/domain-management.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.DomainManager?.init();
    });
</script>
