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
        </div>

        <div class="modal fade" id="rp_role_modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="rp_role_modal_title" class="modal-title">Add Role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="rp_role_form" novalidate>
                        <div class="modal-body">
                            <input id="rp_role_id" type="hidden">
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
                                <textarea id="rp_description" class="form-control" rows="3" placeholder="Optional description"></textarea>
                            </div>
                            <div class="mb-0">
                                <label class="form-label" for="rp_role_status">Status</label>
                                <select id="rp_role_status" class="form-select">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="archived">Archived</option>
                                </select>
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
@endsection
