@extends('layouts.app')

@section('title', 'Packages Management')

@section('content')
<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3"><i class="fas fa-box"></i> SaaS Packages</h1>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-primary btn-sm" id="btn_add_package">
                <i class="fas fa-plus"></i> Add Package
            </button>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="row mb-3">
        <div class="col-md-6">
            <input type="text" class="form-control" id="search_packages" placeholder="Search packages...">
        </div>
        <div class="col-md-3">
            <select class="form-select" id="filter_status">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-outline-secondary w-100" id="btn_reset_filters">
                <i class="fas fa-redo"></i> Reset
            </button>
        </div>
    </div>

    <!-- Packages Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="packages_table">
                <thead class="table-light">
                    <tr>
                        <th>Package Name</th>
                        <th>Price</th>
                        <th>Billing Cycle</th>
                        <th>Features</th>
                        <th>Status</th>
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
                            Loading packages...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-muted" id="packages_info">Loading...</small>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0" id="packages_pagination">
                    <!-- Pagination will be rendered here -->
                </ul>
            </nav>
        </div>
    </div>

    <!-- Add/Edit Package Modal -->
    <div class="modal fade" id="packageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="packageModalTitle">Add Package</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="packageForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Package Name *</label>
                            <input type="text" class="form-control" id="input_package_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="input_package_description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price (Rp) *</label>
                                <input type="number" class="form-control" id="input_package_price" min="0" step="0.01" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Billing Cycle *</label>
                                <select class="form-select" id="input_package_cycle" required>
                                    <option value="">Select cycle</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="input_package_active" checked>
                                <label class="form-check-label" for="input_package_active">
                                    Active
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Features (comma-separated)</label>
                            <textarea class="form-control" id="input_package_features" rows="3" placeholder="Feature 1, Feature 2, Feature 3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Package</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Features Modal -->
    <div class="modal fade" id="featuresModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Package Features</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="features_container">
                    <!-- Features will be rendered here -->
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
                    <p>Are you sure you want to delete this package? This action cannot be undone.</p>
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
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
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

<script src="{{ asset('resources/js/packages-management.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.PackagesManager.init();
    });
</script>
@endsection
