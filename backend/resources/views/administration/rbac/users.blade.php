<?php $page = 'users'; ?>
@extends('layout.mainlayout')
@section('content')
    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Users</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Administration
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Users</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                    <div class="me-2 mb-2">
                        <div class="dropdown">
                            <a href="javascript:void(0);"
                                class="dropdown-toggle btn btn-white d-inline-flex align-items-center"
                                data-bs-toggle="dropdown">
                                <i class="ti ti-file-export me-1"></i>Export
                            </a>
                            <ul class="dropdown-menu  dropdown-menu-end p-3">
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1 disabled"><i
                                            class="ti ti-file-type-pdf me-1"></i>Export as PDF</a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" id="btn_um_export_csv" class="dropdown-item rounded-1"><i
                                            class="ti ti-file-type-csv me-1"></i>Export as CSV </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="mb-2">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#um_user_modal"
                            class="btn btn-primary d-flex align-items-center"><i class="ti ti-circle-plus me-2"></i>Add
                            User</a>
                    </div>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top"
                            data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <!-- Performance Indicator list -->
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <h5>Users List</h5>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                        <div class="me-3">
                            <div class="input-icon-end position-relative">
                                <input id="um_search" type="text" class="form-control" placeholder="Search name/email">
                                <span class="input-icon-addon">
                                    <i class="ti ti-search"></i>
                                </span>
                            </div>
                        </div>
                        <div class="me-3">
                            <select id="um_role_filter" class="form-select">
                                <option value="">All Roles</option>
                            </select>
                        </div>
                        <div class="me-3">
                            <select id="um_status_filter" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                        <div>
                            <button id="um_reset_filters" type="button" class="btn btn-white">Reset</button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="um_alert" class="alert d-none m-3" role="alert"></div>
                    <div class="custom-datatable-filter table-responsive">
                        <table class="table">
                            <thead class="thead-light">
                                <tr>
                                    <th class="no-sort">
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox" id="select-all">
                                        </div>
                                    </th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Created Date</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="um_users_tbody"></tbody>
                        </table>
                    </div>
                    <div class="d-flex align-items-center justify-content-between p-3 border-top">
                        <div id="um_pagination_meta" class="text-muted small"></div>
                        <div class="btn-group" role="group" aria-label="Pagination">
                            <button id="um_prev_page" type="button" class="btn btn-light btn-sm">Prev</button>
                            <button id="um_next_page" type="button" class="btn btn-light btn-sm">Next</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Performance Indicator list -->

        </div>

        <div class="modal fade" id="um_user_modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="um_user_modal_title" class="modal-title">Add User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="um_user_form" class="needs-validation" novalidate>
                        <div class="modal-body">
                            <input id="um_user_id" type="hidden">
                            <div class="mb-3">
                                <label class="form-label" for="um_name">Name</label>
                                <input id="um_name" type="text" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="um_email">Email</label>
                                <input id="um_email" type="email" class="form-control" required>
                            </div>
                            <div id="um_password_wrap" class="mb-3">
                                <label class="form-label" for="um_password">Password</label>
                                <input id="um_password" type="password" class="form-control" minlength="8">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="um_status">Status</label>
                                <select id="um_status" class="form-select">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div id="um_roles_wrap" class="mb-0">
                                <label class="form-label" for="um_role_codes">Roles</label>
                                <select id="um_role_codes" class="form-select" multiple></select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button id="um_save_btn" type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="um_role_modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Manage Role Assignment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input id="um_role_user_id" type="hidden">
                        <div class="mb-3">
                            <div id="um_role_user_name" class="fw-semibold"></div>
                            <div class="text-muted small">Assign or revoke roles for this user in active company.</div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-8">
                                <select id="um_assign_role_code" class="form-select">
                                    <option value="">Select role</option>
                                </select>
                            </div>
                            <div class="col-4 d-grid">
                                <button id="um_assign_role_btn" type="button" class="btn btn-primary">Assign</button>
                            </div>
                        </div>
                        <div id="um_role_loading" class="text-muted small mb-2 d-none">Loading role assignments...</div>
                        <div id="um_role_empty" class="text-muted small mb-2 d-none">No role assignment found.</div>
                        <div id="um_role_assignment_list" class="list-group list-group-flush border rounded"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <!-- /Page Wrapper -->
    @component('components.modal-popup')
    @endcomponent
@endsection
