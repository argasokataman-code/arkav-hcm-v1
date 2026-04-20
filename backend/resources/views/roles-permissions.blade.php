<?php $page = 'roles-permissions'; ?>
@extends('layout.mainlayout')
@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Roles</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Administration
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Roles</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                    <div class="me-2 mb-2">
                        <div class="dropdown">
                            <a href="javascript:void(0);" class="dropdown-toggle btn btn-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
                                <i class="ti ti-file-export me-1"></i>Export
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end p-3">
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1 disabled"><i class="ti ti-file-type-pdf me-1"></i>Export as PDF</a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" id="rp_export_roles_csv" class="dropdown-item rounded-1"><i class="ti ti-file-type-csv me-1"></i>Export as CSV</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="mb-2">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#rp_role_modal" class="btn btn-primary d-flex align-items-center" id="rp_open_create_modal"><i class="ti ti-circle-plus me-2"></i>Add Role</a>
                    </div>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <h5>Roles List</h5>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                        <div class="me-3">
                            <div class="input-icon-end position-relative">
                                <input id="rp_search" type="text" class="form-control" placeholder="Search code/name/description">
                                <span class="input-icon-addon">
                                    <i class="ti ti-search"></i>
                                </span>
                            </div>
                        </div>
                        <div class="me-3">
                            <select id="rp_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="archived">Archived</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                        <div>
                            <button id="rp_reset" type="button" class="btn btn-white">Reset</button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="rp_alert" class="alert d-none m-3" role="alert"></div>
                    <div class="custom-datatable-filter table-responsive">
                        <table class="table">
                            <thead class="thead-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Role</th>
                                    <th>Description</th>
                                    <th>Permissions</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="rp_roles_tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <div>
                        <h5 class="mb-1">Permission Blueprint</h5>
                        <p class="text-muted mb-0">Detail permission sekarang dibaca langsung di modal create/edit role dengan pola builder dua panel seperti create package.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span id="rp_permission_summary" class="badge badge-light text-dark">0 permissions</span>
                        <button id="rp_open_create_modal_secondary" type="button" class="btn btn-outline-primary">Open Role Builder</button>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div id="rp_permission_summary_modules" class="d-flex flex-wrap gap-2"></div>
                    <p id="rp_permission_catalog_empty" class="text-muted small d-none mb-0 mt-3">No permissions available for current company.</p>
                </div>
            </div>
        </div>

        <div class="modal fade" id="rp_role_modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="rp_role_modal_title" class="modal-title">Add Role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="rp_role_form" novalidate>
                        <div class="modal-body">
                            <div class="row g-4">
                                <div class="col-lg-4">
                                    <div class="rp-role-modal-panel p-3">
                                        <input id="rp_role_id" type="hidden">
                                        <h6 class="fw-bold mb-3">Role Information</h6>
                                        <div class="mb-3" id="rp_code_wrap">
                                            <label class="form-label" for="rp_code">Code</label>
                                            <input id="rp_code" type="text" class="form-control" placeholder="OPS_ADMIN" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label" for="rp_name">Name</label>
                                            <input id="rp_name" type="text" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label" for="rp_description">Description</label>
                                            <textarea id="rp_description" class="form-control" rows="4" placeholder="Ringkas tujuan role, tim pemakai, dan batas akses utamanya"></textarea>
                                        </div>
                                        <div class="mb-0">
                                            <label class="form-label" for="rp_role_status">Status</label>
                                            <select id="rp_role_status" class="form-select">
                                                <option value="active">Active</option>
                                                <option value="inactive">Inactive</option>
                                                <option value="archived">Archived</option>
                                            </select>
                                        </div>
                                        <div class="rp-permission-summary mt-3">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <p class="mb-0 fw-semibold">Selected Permissions</p>
                                                <span id="rp_form_permission_summary" class="badge text-bg-primary">0</span>
                                            </div>
                                            <div id="rp_form_permission_preview" class="d-flex flex-wrap gap-2">
                                                <span class="text-muted small">Belum ada permission dipilih</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-8">
                                    <div class="rp-role-modal-panel p-3">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                            <div>
                                                <h6 class="fw-bold mb-1">Permission Builder</h6>
                                                <p class="text-muted small mb-0">Pilih permission detail per modul, resource, dan action. Jadi saat create role Anda tidak perlu menebak-nebak lagi.</p>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <button id="rp_form_select_visible" type="button" class="btn btn-sm btn-outline-secondary">Select visible</button>
                                                <button id="rp_form_clear_all" type="button" class="btn btn-sm btn-outline-dark">Reset</button>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <input id="rp_form_permission_search" type="text" class="form-control" placeholder="Cari permission: payroll, approval, export, employee, reports, dll">
                                        </div>
                                        <div id="rp_form_permission_empty" class="text-muted small d-none">No permissions match the current filter.</div>
                                        <div id="rp_form_permission_list" class="rp-permission-catalog"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button id="rp_save_role" type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="rp_permissions_modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Role Permissions</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input id="rp_permissions_role_id" type="hidden">
                        <div id="rp_permissions_role_meta" class="mb-3 text-muted small"></div>
                        <div id="rp_permissions_loading" class="text-muted mb-2 d-none">Loading permissions...</div>
                        <div id="rp_permissions_list" class="row g-2"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="rp_save_permissions" class="btn btn-primary">Save Permissions</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    @component('components.modal-popup')
    @endcomponent

    <style>
        #rp_role_modal .modal-content {
            border-radius: 14px;
        }

        .rp-role-modal-panel {
            border: 1px solid #e4e7ec;
            border-radius: 12px;
            background: #ffffff;
        }

        .rp-permission-summary {
            border: 1px solid #e4e7ec;
            border-radius: 10px;
            background: #f8fafc;
            padding: 0.75rem;
        }

        .rp-permission-catalog {
            max-height: 58vh;
            overflow-y: auto;
            padding-right: 0.25rem;
        }

        .rp-permission-group {
            border: 1px solid #eef2f7;
            border-radius: 12px;
            padding: 0.85rem;
            margin-bottom: 0.75rem;
            background: #fcfdff;
        }

        .rp-permission-item {
            border: 1px solid #eef2f7;
            border-radius: 10px;
            padding: 0.65rem 0.75rem;
            background: #ffffff;
            height: 100%;
        }

        .rp-permission-item .form-check-label {
            display: flex;
            flex-direction: column;
            gap: 0.12rem;
        }

        .rp-permission-item-title {
            font-weight: 600;
            color: #344054;
        }

        .rp-permission-item-desc {
            font-size: 0.75rem;
            color: #667085;
        }

        .rp-preview-chip,
        .rp-module-chip,
        .rp-role-permission-chip {
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

        .rp-role-permission-chip {
            border-radius: 10px;
            font-weight: 500;
        }
    </style>
@endsection
