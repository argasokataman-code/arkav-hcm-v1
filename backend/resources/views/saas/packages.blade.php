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
            <div class="card">
                <div class="table-responsive">
                    <table class="table">
                        <tbody>
                            @for ($i = 0; $i < 5; $i++)
                            <tr>
                                @for ($j = 0; $j < 6; $j++)
                                <td><div class="placeholder-glow"><span class="placeholder col-10 rounded"></span></div></td>
                                @endfor
                            </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
<!-- /Page Wrapper -->

<!-- Add/Edit Package Modal -->
<div class="modal fade" id="packageModal" tabindex="-1" role="dialog" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header py-2 border-bottom">
                <h6 class="modal-title fw-bold" id="packageModalTitle">Add Package</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="packageForm" novalidate>

                {{-- ─── Wizard Nav ─────────────────────────────────────────── --}}
                <div class="add-details-wizard px-4 pt-2 pb-0 border-bottom">
                    <ul class="progress-bar-wizard d-flex align-items-stretch list-unstyled mb-0">
                        <li class="pkg-wizard-nav-item active flex-fill pe-4 pb-2" data-pkg-wizard-nav="1">
                            <div class="d-flex align-items-center gap-2">
                                <span class="pkg-wizard-num">1</span>
                                <div>
                                    <div class="fw-semibold small lh-sm">Informasi Paket</div>
                                    <div class="text-muted" style="font-size:.7rem">Nama, harga & status</div>
                                </div>
                            </div>
                        </li>
                        <li class="pkg-wizard-nav-item flex-fill pe-4 pb-2" data-pkg-wizard-nav="2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="pkg-wizard-num">2</span>
                                <div>
                                    <div class="fw-semibold small lh-sm">Pilih Fitur</div>
                                    <div class="text-muted" style="font-size:.7rem">Core & Addon</div>
                                </div>
                            </div>
                        </li>
                        <li class="pkg-wizard-nav-item flex-fill pb-2" data-pkg-wizard-nav="3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="pkg-wizard-num">3</span>
                                <div>
                                    <div class="fw-semibold small lh-sm">Review & Simpan</div>
                                    <div class="text-muted" style="font-size:.7rem">Konfirmasi</div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                {{-- ─── Step 1 : Informasi Paket ───────────────────────────── --}}
                <div class="modal-body p-0">

                <fieldset data-pkg-step="1" class="pkg-wizard-fieldset">
                    <div class="px-4 py-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="package-modal-panel p-3 h-100">
                                    <p class="fw-semibold small text-primary mb-3 d-flex align-items-center gap-1">
                                        <i class="ti ti-id-badge"></i> Identitas Paket
                                    </p>
                                    <div class="mb-2">
                                        <label class="form-label fw-medium mb-1" style="font-size:.8rem">Nama Paket <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" id="input_package_name" placeholder="e.g. Starter, Professional, Enterprise" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label fw-medium mb-1" style="font-size:.8rem">Deskripsi</label>
                                        <textarea class="form-control form-control-sm" id="input_package_description" rows="4" placeholder="Ringkasan target pengguna dan benefit utama paket"></textarea>
                                    </div>
                                    <div>
                                        <label class="form-label fw-medium mb-1" style="font-size:.8rem">Status</label>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" id="input_package_active" checked>
                                            <label class="form-check-label small" for="input_package_active">Aktif</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="package-modal-panel p-3 h-100">
                                    <p class="fw-semibold small text-success mb-3 d-flex align-items-center gap-1">
                                        <i class="ti ti-cash"></i> Harga & Billing
                                    </p>
                                    <div class="mb-2">
                                        <label class="form-label fw-medium mb-1" style="font-size:.8rem">Harga (Rp) <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" class="form-control" id="input_package_price" min="0" step="1" placeholder="0" required>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label fw-medium mb-1" style="font-size:.8rem">Billing Cycle <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" id="input_package_cycle" required>
                                            <option value="">Pilih siklus billing</option>
                                            <option value="monthly">Bulanan (Monthly)</option>
                                            <option value="yearly">Tahunan (Yearly)</option>
                                        </select>
                                        <div class="form-text" style="font-size:.72rem">Harga bulanan & tahunan dihitung otomatis.</div>
                                    </div>
                                    <div>
                                        <label class="form-label fw-medium mb-1" style="font-size:.8rem">Maksimal Employee</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" class="form-control" id="input_package_max_employees" min="1" step="1" placeholder="Kosongkan untuk unlimited">
                                            <span class="input-group-text">org</span>
                                        </div>
                                        <div class="form-text" style="font-size:.72rem">Mengatur fitur <strong>Maximum Employees</strong> otomatis.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </fieldset>

                {{-- ─── Step 2 : Pilih Fitur ───────────────────────────────── --}}
                <fieldset data-pkg-step="2" class="pkg-wizard-fieldset">
                    <div class="px-4 pt-3 pb-3">
                        {{-- Global toolbar --}}
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <div>
                                <h6 class="fw-bold mb-0">Pilih Fitur Paket</h6>
                                <p class="text-muted small mb-0">Setiap paket punya daftar Core & Addon sendiri.</p>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <input type="text" class="form-control form-control-sm" id="input_package_feature_search"
                                    placeholder="Cari fitur…" style="min-width:180px">
                                <button type="button" class="btn btn-sm btn-outline-dark" data-feature-clear-all>Reset semua</button>
                                <button type="button" class="btn btn-sm btn-outline-info" data-feature-healthcheck-trigger>Healthcheck</button>
                            </div>
                        </div>
                        <div class="small text-muted mb-3" data-feature-healthcheck-status>Healthcheck: belum dijalankan.</div>

                        <div class="package-modal-panel p-3">
                            <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom">
                                <span class="small text-muted">Centang fitur &rarr; pilih tier: <span class="badge text-bg-primary" style="font-size:.68rem">Core</span> (selalu ada) atau <span class="badge text-bg-secondary" style="font-size:.68rem">Addon</span> (opsional).</span>
                            </div>
                            <div class="package-feature-scroll-region">
                                <div id="input_package_feature_chips" class="package-feature-catalog"></div>
                            </div>
                        </div>

                        {{-- Compliance snapshot (full width) --}}
                        <div class="mt-3">
                            <div class="package-compliance-panel" data-package-compliance-snapshot>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h6 class="fw-bold mb-0 small">Package Compliance</h6>
                                    <span class="badge text-bg-light" data-package-compliance-overall>Checking...</span>
                                </div>
                                <p class="text-muted small mb-0">Snapshot compliance diperbarui otomatis saat fitur diubah.</p>
                            </div>
                        </div>

                    </div>
                </fieldset>

                {{-- ─── Step 3 : Review & Simpan ───────────────────────────── --}}
                <fieldset data-pkg-step="3" class="pkg-wizard-fieldset">
                    <div class="px-4 pt-3 pb-3">
                        <div class="row g-4">
                            <div class="col-md-5">
                                <div class="package-modal-panel p-4">
                                    <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
                                        <i class="ti ti-clipboard-check text-primary"></i> Ringkasan Paket
                                    </h6>
                                    <div id="pkg_review_summary" class="d-flex flex-column gap-2">
                                        <span class="text-muted small">Kembali ke langkah 1 untuk mengisi informasi paket.</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="package-modal-panel p-4 h-100">
                                    <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
                                        <i class="ti ti-list-check text-success"></i> Fitur Terpilih
                                        <span class="badge text-bg-primary ms-auto" data-feature-selected-count>0</span>
                                    </h6>
                                    {{-- Core features --}}
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge text-bg-primary" style="font-size:.68rem">Core</span>
                                            <span class="text-muted small">Selalu termasuk dalam paket</span>
                                            <span class="badge text-bg-light ms-auto" id="pkg_review_core_count">0</span>
                                        </div>
                                        <div class="d-flex flex-wrap gap-1" id="pkg_review_core_chips">
                                            <span class="text-muted small fst-italic">Belum ada fitur Core dipilih</span>
                                        </div>
                                    </div>
                                    {{-- Addon features --}}
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge text-bg-secondary" style="font-size:.68rem">Addon</span>
                                            <span class="text-muted small">Fitur opsional tambahan</span>
                                            <span class="badge text-bg-light ms-auto" id="pkg_review_addon_count">0</span>
                                        </div>
                                        <div class="d-flex flex-wrap gap-1" id="pkg_review_addon_chips">
                                            <span class="text-muted small fst-italic">Belum ada fitur Addon dipilih</span>
                                        </div>
                                    </div>
                                </div>

                {{-- ─── Purchasable Add-ons Assignment ─────────────────────── --}}
                <div class="mt-4" id="pkg_addon_assignment_section" style="display:none">
                    <div class="package-modal-panel p-4">
                        <h6 class="fw-bold mb-1 d-flex align-items-center gap-2">
                            <i class="ti ti-puzzle text-warning"></i>
                            Add-on Yang Bisa Dibeli Tenant
                            <span class="badge text-bg-light ms-auto" id="pkg_addon_assign_count">0</span>
                        </h6>
                        <p class="text-muted small mb-3">
                            Pilih add-on dari katalog global yang tersedia untuk dibeli oleh tenant pada paket ini.
                            Perubahan langsung tersimpan.
                        </p>
                        <div id="pkg_addon_assign_list" class="d-flex flex-wrap gap-2">
                            <span class="text-muted small fst-italic">Memuat...</span>
                        </div>
                    </div>
                </div>

                </fieldset>

                {{-- backward-compat hidden targets --}}
                <div class="d-none" aria-hidden="true">
                    <div data-feature-selected-preview></div>
                </div>

                </div>{{-- /modal-body --}}

            {{-- ─── Wizard Footer (outside form — Bootstrap modal-dialog-scrollable requires this) --}}
            <div class="modal-footer">
                <button type="button" class="btn btn-light" id="pkg_wizard_back" style="display:none">
                    <i class="ti ti-arrow-left me-1"></i>Back
                </button>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="pkg_wizard_next">
                        Next <i class="ti ti-arrow-right ms-1"></i>
                    </button>
                    <button type="submit" form="packageForm" class="btn btn-success d-none" id="pkg_wizard_save">
                        <i class="ti ti-device-floppy me-1"></i>Save Package
                    </button>
                </div>
            </div>

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

<!-- Feature Catalog Modal -->
<div class="modal fade" id="featureCatalogModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">List All Features</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="feature_catalog_container"></div>
        </div>
    </div>
</div>

    <!-- Manage Feature Classifications Modal -->
    <div class="modal fade" id="featureClassificationModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Feature Classifications</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="feature_classifications_container"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

<!-- Module Preview Modal -->
<div class="modal fade" id="modulePreviewModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modulePreviewTitle">Module Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="module_preview_container"></div>
        </div>
    </div>
</div>

<!-- Feature Matrix Modal -->
<div class="modal fade" id="featureMatrixModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cross-Package Feature Matrix</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="feature_matrix_container"></div>
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
                        <div class="col-md-6" id="addon_code_field">
                            <label class="form-label">Code *</label>
                            <input type="text" class="form-control" id="input_addon_code" required>
                            <div id="addon_code_locked_note" class="form-text text-muted d-none">
                                <i class="ti ti-lock me-1"></i>Code terkunci — sudah ada transaksi paid.
                            </div>
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
    #packageModal .modal-dialog {
        max-width: min(1140px, 96vw);
        margin: 1rem auto;
    }

    #packageModal .modal-content {
        border-radius: 14px;
        max-height: calc(100vh - 2rem);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    #packageModal form#packageForm {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    #packageModal .modal-body {
        flex: 1 1 auto;
        overflow-y: auto;
        min-height: 0;
    }

    .package-modal-panel {
        border: 1px solid #e4e7ec;
        border-radius: 12px;
        background: #ffffff;
    }

    .package-feature-pane,
    .package-compliance-pane {
        display: flex;
        flex-direction: column;
        min-height: 0;
        flex: 1 1 auto;
    }

    .package-feature-search-wrap {
        flex: 0 0 auto;
    }

    .package-feature-scroll-region {
        padding-right: 0.25rem;
    }

    .package-feature-summary {
        border: 1px solid #e4e7ec;
        border-radius: 10px;
        background: #f8fafc;
        padding: 0.75rem;
    }

    .package-feature-catalog {
        min-height: 260px;
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
    .pkg-feat-label {
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
        min-width: 0;
        cursor: pointer;
    }
    .feat-tier-selector {
        flex-shrink: 0;
    }
    .feat-tier-selector .btn-group {
        display: inline-flex;
    }
    .feat-tier-btn-core {
        font-size: .72rem;
        padding: .2rem .65rem;
        white-space: nowrap;
        line-height: 1.5;
        color: #4361ee;
        border-color: #4361ee;
        background: transparent;
    }
    .feat-tier-btn-core:hover {
        background: #eef0fd;
        color: #4361ee;
        border-color: #4361ee;
    }
    .btn-check:checked + .feat-tier-btn-core {
        background-color: #4361ee !important;
        border-color: #4361ee !important;
        color: #fff !important;
        font-weight: 600;
    }
    .feat-tier-btn-addon {
        font-size: .72rem;
        padding: .2rem .65rem;
        white-space: nowrap;
        line-height: 1.5;
        color: #667085;
        border-color: #d0d5dd;
        background: transparent;
    }
    .feat-tier-btn-addon:hover {
        background: #f8fafc;
        color: #344054;
        border-color: #98a2b3;
    }
    .btn-check:checked + .feat-tier-btn-addon {
        background-color: #f45700 !important;
        border-color: #f45700 !important;
        color: #fff !important;
        font-weight: 600;
    }
    .package-feature-item:has(input[name="package_feature_include"]:checked) {
        background: #f0f7ff;
        border-color: #b3d4f0;
    }

    .package-feature-item-title {
        font-weight: 600;
        color: #344054;
    }

    .package-feature-item-desc {
        font-size: 0.75rem;
        color: #667085;
    }

    .package-feature-group-head {
        display: flex;
        align-items: stretch;
        width: 100%;
    }

    .package-feature-group-head .accordion-button {
        flex: 1 1 auto;
    }

    .package-feature-module-preview {
        border: 0;
        border-left: 1px solid #e4e7ec;
        min-width: 108px;
        background: #f8fafc;
        color: #344054;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .package-feature-module-preview:hover {
        background: #eef2f7;
    }

    .feature-catalog-module-card {
        border: 1px solid #e4e7ec;
        border-radius: 10px;
        background: #ffffff;
    }

    .feature-catalog-module-card + .feature-catalog-module-card {
        margin-top: 0.75rem;
    }

    .feature-catalog-list {
        max-height: 56vh;
        overflow-y: auto;
    }

    .feature-matrix-table thead th {
        white-space: nowrap;
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

    .package-compliance-panel {
        border: 1px solid #e4e7ec;
        border-radius: 10px;
        background: #f8fafc;
        padding: 0.75rem;
    }

    .package-compliance-section + .package-compliance-section {
        margin-top: 0.75rem;
    }

    .package-compliance-section-title {
        font-size: 0.78rem;
        font-weight: 700;
        color: #344054;
        margin-bottom: 0.25rem;
    }

    .package-compliance-item {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #ffffff;
        padding: 0.4rem 0.5rem;
        margin-bottom: 0.35rem;
    }

    .package-compliance-item-label {
        font-size: 0.76rem;
        font-weight: 600;
        color: #111827;
    }

    .package-compliance-item-note {
        font-size: 0.7rem;
        color: #6b7280;
    }

    .package-compliance-item:last-child {
        margin-bottom: 0;
    }

    @media (max-width: 991.98px) {
        #packageModal .col-lg-4 .package-modal-panel,
        #packageModal .col-lg-8 .package-modal-panel {
            max-height: none;
            overflow: visible;
        }

        .package-feature-catalog {
            min-height: 180px;
        }

        .package-feature-scroll-region {
            /* no fixed max-height — modal-body scrolls */
        }

        .package-feature-module-preview {
            min-width: 92px;
            font-size: 0.7rem;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
    }

    /* Package wizard nav */
    .pkg-wizard-nav-item {
        border-bottom: 2px solid transparent;
        transition: border-color 0.2s;
        cursor: default;
        color: #6c757d;
    }
    .pkg-wizard-nav-item.active {
        border-color: var(--bs-primary, #4361ee);
        color: var(--bs-primary, #4361ee);
    }
    .pkg-wizard-nav-item.activated {
        border-color: var(--bs-success, #17c653);
        color: var(--bs-success, #17c653);
    }
    .pkg-wizard-num {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #f1f3f9;
        font-size: .78rem;
        font-weight: 600;
        flex-shrink: 0;
        transition: background 0.2s, color 0.2s;
    }
    .pkg-wizard-nav-item.active .pkg-wizard-num {
        background: var(--bs-primary, #4361ee);
        color: #fff;
    }
    .pkg-wizard-nav-item.activated .pkg-wizard-num {
        background: var(--bs-success, #17c653);
        color: #fff;
    }

    /* Package wizard fieldsets */
    .pkg-wizard-fieldset {
        display: none;
    }
    .add-details-wizard {
        flex-shrink: 0;
    }
    #packageModal .modal-footer {
        flex-shrink: 0;
    }

    /* ── Package card ───────────────────────────────────── */
    .pkg-card {
        border: 1px solid #e4e7ec;
        border-radius: 12px;
        overflow: hidden;
        transition: box-shadow 0.18s ease, border-color 0.18s ease, transform 0.18s ease;
    }
    .pkg-card:hover {
        box-shadow: 0 6px 24px rgba(0,0,0,.09);
        border-color: #c8d0de;
        transform: translateY(-2px);
    }
    .pkg-card--admin {
        border-left: 3px solid #212529;
    }

    /* Accent bar at the top of each card */
    .pkg-card-accent {
        height: 4px;
        width: 100%;
        flex-shrink: 0;
    }
    .pkg-card-accent--default {
        background: #e4e7ec;
    }

    /* Card body */
    .pkg-card .card-body {
        padding: 1.375rem 1.5rem 1.25rem;
    }

    /* Description */
    .pkg-card-desc {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        color: #667085;
    }

    /* Section label */
    .pkg-section-label {
        font-size: 0.6875rem;
        font-weight: 700;
        color: #98a2b3;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        margin-bottom: 0.4rem;
    }

    /* Feature tag — neutral, all same style */
    .pkg-feat-tag {
        display: inline-flex;
        align-items: center;
        font-size: 0.72rem;
        font-weight: 500;
        color: #344054;
        background: #f2f4f7;
        border: 1px solid #e4e7ec;
        border-radius: 5px;
        padding: 2px 8px;
        line-height: 1.6;
        white-space: nowrap;
    }
    .pkg-feat-tag--more {
        color: #667085;
        background: transparent;
        border-color: #d0d5dd;
        font-style: normal;
    }

    /* Footer stats */
    .pkg-stat {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.75rem;
        color: #667085;
        white-space: nowrap;
    }
    .pkg-stat i {
        font-size: 0.85rem;
        opacity: 0.7;
    }

    /* Action btn-group */
    .pkg-card .btn-group .btn-icon {
        width: 30px;
        height: 30px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f9fafb;
        border-color: #e4e7ec;
        color: #667085;
    }
    .pkg-card .btn-group .btn-icon:hover {
        background: #f2f4f7;
        border-color: #c8d0de;
        color: #344054;
    }
    .pkg-card .btn-group .btn-icon.text-danger:hover {
        background: #fff1f0;
        border-color: #fca5a5;
        color: #dc2626;
    }
</style>

<script type="module" src="{{ asset('build/js/packages-management.js') }}?v={{ filemtime(public_path('build/js/packages-management.js')) }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.PackagesManager?.init?.();
    });
</script>

@endsection
