<?php $page = 'saas-packages'; ?>
@extends('layout.mainlayout')

@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content" data-saas-packages-page>

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">SaaS Packages</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">SaaS</li>
                        <li class="breadcrumb-item active" aria-current="page">Packages</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                <div class="mb-2">
                    <button class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#packageModal" id="btn_add_package">
                        <i class="ti ti-circle-plus me-2"></i>Add Package
                    </button>
                </div>
            </div>
        </div>
        <!-- /Breadcrumb -->

        <!-- Filter Card -->
        <div class="card">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <input type="text" class="form-control" placeholder="Search packages..." id="search_packages" data-package-filter-search>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" id="filter_status" data-package-filter-status>
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="archived">Archived</option>
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

        <!-- Packages List Container -->
        <div data-packages-list-container>
            <div class="card"><div class="card-body text-center text-muted py-4">Loading packages...</div></div>
        </div>

        <!-- Package Add-ons List Container -->
        <div class="mt-4" data-package-addons-list-container>
            <div class="card"><div class="card-body text-center text-muted py-4">Loading add-ons...</div></div>
        </div>

    </div>
</div>
<!-- /Page Wrapper -->

<!-- Add/Edit Package Modal -->
<div class="modal fade" id="packageModal" tabindex="-1" role="dialog" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="packageModalTitle">Add Package</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="packageForm">
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-lg-4">
                            <div class="package-modal-panel p-3">
                                <h6 class="fw-bold mb-3">Informasi paket</h6>
                                <div class="mb-3">
                                    <label class="form-label">Package Name *</label>
                                    <input type="text" class="form-control" id="input_package_name" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" id="input_package_description" rows="4" placeholder="Ringkasan target pengguna, benefit utama, dan batasan paket"></textarea>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Price (Rp) *</label>
                                        <input type="number" class="form-control" id="input_package_price" min="0" step="0.01" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Billing Cycle *</label>
                                        <select class="form-select" id="input_package_cycle" required>
                                            <option value="">Select cycle</option>
                                            <option value="monthly">Monthly</option>
                                            <option value="yearly">Yearly</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label">Status</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="input_package_active" checked>
                                        <label class="form-check-label" for="input_package_active">Active</label>
                                    </div>
                                </div>
                                <div class="package-feature-summary mt-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <p class="mb-0 fw-semibold">Fitur terpilih</p>
                                        <span class="badge text-bg-primary" data-feature-selected-count>0</span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2" data-feature-selected-preview>
                                        <span class="text-muted small">Belum ada fitur dipilih</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <div class="package-modal-panel p-3">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                    <div>
                                        <h6 class="fw-bold mb-1">Detail fitur paket</h6>
                                        <p class="text-muted small mb-0">Pilih kapabilitas detail per modul agar paket bisa disesuaikan untuk segmen customer berbeda.</p>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-feature-select-visible>Centang semua terlihat</button>
                                        <button type="button" class="btn btn-sm btn-outline-dark" data-feature-clear-all>Reset</button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <input type="text" class="form-control" id="input_package_feature_search" placeholder="Cari fitur: payroll, approval, API, laporan, dll">
                                </div>
                                <div id="input_package_feature_chips" class="package-feature-catalog"></div>
                            </div>
                        </div>
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
<div class="modal fade" id="featuresModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Package Features</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="features_container"></div>
        </div>
    </div>
</div>

<!-- Add/Edit Add-on Modal -->
<div class="modal fade" id="addonModal" tabindex="-1" role="dialog" data-bs-backdrop="static">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addonModalTitle">Add Add-on</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addonForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Code *</label>
                            <input type="text" class="form-control" id="input_addon_code" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Name *</label>
                            <input type="text" class="form-control" id="input_addon_name" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="input_addon_description" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Price per Unit *</label>
                            <input type="number" class="form-control" id="input_addon_price" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unit Name *</label>
                            <input type="text" class="form-control" id="input_addon_unit" placeholder="users, GB, API calls" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Status</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="input_addon_active" checked>
                            <label class="form-check-label" for="input_addon_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Add-on</button>
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
                <p>Are you sure you want to delete this package? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btn_confirm_delete">Delete</button>
            </div>
        </div>
    </div>
</div>

<style>
    #packageModal .modal-content {
        border-radius: 14px;
    }

    .package-modal-panel {
        border: 1px solid #e4e7ec;
        border-radius: 12px;
        background: #ffffff;
    }

    .package-feature-summary {
        border: 1px solid #e4e7ec;
        border-radius: 10px;
        background: #f8fafc;
        padding: 0.75rem;
    }

    .package-feature-catalog {
        max-height: 58vh;
        overflow-y: auto;
        padding-right: 0.25rem;
    }

    .package-feature-item {
        border: 1px solid #eef2f7;
        border-radius: 10px;
        padding: 0.65rem 0.75rem;
        margin-bottom: 0.5rem;
        background: #fcfdff;
    }

    .package-feature-item .form-check-label {
        display: flex;
        flex-direction: column;
        gap: 0.12rem;
    }

    .package-feature-item-title {
        font-weight: 600;
        color: #344054;
    }

    .package-feature-item-desc {
        font-size: 0.75rem;
        color: #667085;
    }

    .package-feature-chip {
        color: #344054 !important;
        background: #f8fafc;
        border: 1px solid #d0d5dd;
        font-weight: 600;
    }

    .btn-check:checked + .package-feature-chip {
        color: #ffffff !important;
        background: #f45700;
        border-color: #f45700;
    }

    .package-feature-preview-chip {
        display: inline-flex;
        align-items: center;
        border: 1px solid #d0d5dd;
        border-radius: 999px;
        padding: 0.15rem 0.55rem;
        background: #ffffff;
        font-size: 0.75rem;
        font-weight: 600;
        color: #344054;
    }
</style>

<script src="{{ asset('build/js/packages-management.js') }}?v={{ filemtime(public_path('build/js/packages-management.js')) }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.PackagesManager?.init?.();
    });
</script>

@endsection
