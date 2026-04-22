<?php $page = 'companies'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Companies</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Application
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Companies List</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                    <div class="me-2 mb-2">
                        <div class="dropdown">
                            <a href="javascript:void(0);" class="dropdown-toggle btn btn-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
                                <i class="ti ti-file-export me-1"></i>Export
                            </a>
                            <ul class="dropdown-menu  dropdown-menu-end p-3">
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1"><i class="ti ti-file-type-pdf me-1"></i>Export as PDF</a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1"><i class="ti ti-file-type-xls me-1"></i>Export as Excel </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="mb-2">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#add_company" class="btn btn-primary d-flex align-items-center"><i class="ti ti-circle-plus me-2"></i>Add Company</a>
                    </div>
                    <div class="ms-2 head-icons">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <div class="row">

                <!-- Total Companies -->
                <div class="col-lg-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center overflow-hidden">
                                <span class="avatar avatar-lg bg-primary flex-shrink-0">
                                    <i class="ti ti-building fs-16"></i>
                                </span>
                                <div class="ms-2 overflow-hidden">
                                    <p class="fs-12 fw-medium mb-1 text-truncate">Total Companies</p>
                                    <h4 id="companies_total_count">0</h4>
                                </div>
                            </div>
                            <div id="total-chart"></div>
                        </div>
                    </div>
                </div>
                <!-- /Total Companies -->

                <!-- Total Companies -->
                <div class="col-lg-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center overflow-hidden">
                                <span class="avatar avatar-lg bg-success flex-shrink-0">
                                    <i class="ti ti-building fs-16"></i>
                                </span>
                                <div class="ms-2 overflow-hidden">
                                    <p class="fs-12 fw-medium mb-1 text-truncate">Active Companies</p>
                                    <h4 id="companies_active_count">0</h4>
                                </div>
                            </div>
                            <div id="active-chart"></div>
                        </div>
                    </div>
                </div>
                <!-- /Total Companies -->

                <!-- Inactive Companies -->
                <div class="col-lg-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center overflow-hidden">
                                <span class="avatar avatar-lg bg-danger flex-shrink-0">
                                    <i class="ti ti-building fs-16"></i>
                                </span>
                                <div class="ms-2 overflow-hidden">
                                    <p class="fs-12 fw-medium mb-1 text-truncate">Inactive Companies</p>
                                    <h4 id="companies_inactive_count">0</h4>
                                </div>
                            </div>
                            <div id="inactive-chart"></div>
                        </div>
                    </div>
                </div>
                <!-- /Inactive Companies -->

                <!-- Company Location -->
                <div class="col-lg-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center overflow-hidden">
                                <span class="avatar avatar-lg bg-skyblue flex-shrink-0">
                                    <i class="ti ti-map-pin-check fs-16"></i>
                                </span>
                                <div class="ms-2 overflow-hidden">
                                    <p class="fs-12 fw-medium mb-1 text-truncate">Company Location</p>
                                    <h4 id="companies_location_count">0</h4>
                                </div>
                            </div>
                            <div id="location-chart"></div>
                        </div>
                    </div>
                </div>
                <!-- /Company Location -->

            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <h5>Companies List</h5>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                        <div class="me-3">
                            <input type="text" id="company_search" class="form-control" placeholder="Search name / code / owner email...">
                        </div>
                        <div class="me-3">
                            <select id="status_filter" class="form-select">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div>
                            <button type="button" id="companies_refresh" class="btn btn-white d-inline-flex align-items-center">
                                <i class="ti ti-refresh me-1"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="custom-datatable-filter table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Company</th>
                                    <th>Company Code</th>
                                    <th>Owner</th>
                                    <th>Legal Name</th>
                                    <th>Subscription</th>
                                    <th>Region & Currency</th>
                                    <th>Created Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="companies_table_body">
                                <tr><td colspan="9" class="text-center"><i class="ti ti-loader"></i> Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer d-flex align-items-center justify-content-between flex-wrap row-gap-2">
                        <div class="text-muted small" id="companies_table_info">-</div>
                        <div id="companies_pagination"></div>
                    </div>
                </div>
            </div>

        </div>


    </div>
    <!-- /Page Wrapper -->


    <!-- Add Company Modal -->
    <div class="modal fade" id="add_company" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title">Add Company</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="add_company_form">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="company_code" class="form-label">Company Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="company_code" name="company_code" required>
                        </div>
                        <div class="mb-3">
                            <label for="company_name" class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="company_name" name="company_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="company_legal_name" class="form-label">Legal Name</label>
                            <input type="text" class="form-control" id="company_legal_name" name="company_legal_name">
                        </div>
                        <div class="mb-3">
                            <label for="company_status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="company_status" name="company_status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="company_timezone" class="form-label">Timezone <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="company_timezone" name="company_timezone" placeholder="UTC" required>
                        </div>
                        <div class="mb-3">
                            <label for="company_currency" class="form-label">Currency <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="company_currency" name="company_currency" placeholder="IDR" required>
                        </div>
                        <div class="mb-3">
                            <label for="company_country" class="form-label">Country Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="company_country" name="company_country" placeholder="ID" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Company</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Company Modal -->
    <div class="modal fade" id="edit_company" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title">Edit Company</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="edit_company_form">
                    <input type="hidden" id="edit_company_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_company_code" class="form-label">Company Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_company_code" name="edit_company_code" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_company_name" class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_company_name" name="edit_company_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_company_legal_name" class="form-label">Legal Name</label>
                            <input type="text" class="form-control" id="edit_company_legal_name" name="edit_company_legal_name">
                        </div>
                        <div class="mb-3">
                            <label for="edit_company_status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_company_status" name="edit_company_status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_company_timezone" class="form-label">Timezone <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_company_timezone" name="edit_company_timezone" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_company_currency" class="form-label">Currency <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_company_currency" name="edit_company_currency" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_company_country" class="form-label">Country Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_company_country" name="edit_company_country" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Company</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="delete_modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title">Delete Company</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="delete_company_name"></strong>? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="delete_confirm_btn" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('build/js/companies-management.js') }}?v={{ filemtime(public_path('build/js/companies-management.js')) }}"></script>

    @component('components.modal-popup')
    @endcomponent

@endsection