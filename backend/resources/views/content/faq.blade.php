<?php $page = 'faq'; ?>
@extends('layout.mainlayout')
@section('content')
    <div class="page-wrapper">
        <div class="content pb-4">
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">FAQ Workspace</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">Content</li>
                            <li class="breadcrumb-item active" aria-current="page">FAQ</li>
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
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1" id="faq-export-csv">
                                        <i class="ti ti-file-type-csv me-1"></i>Export as CSV
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1" id="faq-export-json">
                                        <i class="ti ti-code me-1"></i>Export as JSON
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="mb-2">
                        <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#add_faq" class="btn btn-primary d-flex align-items-center">
                            <i class="ti ti-circle-plus me-2"></i>Add FAQ
                        </a>
                    </div>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-xl-4 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary mb-3" style="width: 44px; height: 44px;">
                                <i class="ti ti-help fs-22"></i>
                            </span>
                            <p class="text-muted mb-1">Total FAQ</p>
                            <h3 class="mb-0" id="faq-total-count">0</h3>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success mb-3" style="width: 44px; height: 44px;">
                                <i class="ti ti-category fs-22"></i>
                            </span>
                            <p class="text-muted mb-1">Active Categories</p>
                            <h3 class="mb-0" id="faq-category-count">0</h3>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning-subtle text-warning mb-3" style="width: 44px; height: 44px;">
                                <i class="ti ti-clock-edit fs-22"></i>
                            </span>
                            <p class="text-muted mb-1">Last Updated</p>
                            <h5 class="mb-0" id="faq-last-updated">-</h5>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <div>
                        <h5 class="mb-1">FAQ List</h5>
                        <p class="text-muted fs-13 mb-0">Search, filter, and maintain common answers for your team workspace.</p>
                    </div>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-2 gap-2">
                        <div class="position-relative">
                            <span class="position-absolute top-50 start-0 translate-middle-y ps-3 text-muted">
                                <i class="ti ti-search"></i>
                            </span>
                            <input type="text" class="form-control ps-5" id="faq-search-input" placeholder="Search questions or answers">
                        </div>
                        <select class="form-select" id="faq-category-filter" style="min-width: 180px;">
                            <option value="all">All Categories</option>
                        </select>
                        <select class="form-select" id="faq-sort-select" style="min-width: 160px;">
                            <option value="recent">Recent Update</option>
                            <option value="az">Question A-Z</option>
                            <option value="category">Category</option>
                        </select>
                        <button type="button" class="btn btn-light" id="faq-reset-filters">Reset</button>
                    </div>
                </div>
                <div class="card-body border-bottom py-3 d-none" id="faq-selection-toolbar">
                    <div class="d-flex align-items-center justify-content-between flex-wrap row-gap-2">
                        <span class="text-muted fs-13" id="faq-selected-count">0 items selected</span>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="faq-delete-selected">
                            <i class="ti ti-trash me-1"></i>Delete Selected
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="custom-datatable-filter table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th class="no-sort">
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox" id="select-all">
                                        </div>
                                    </th>
                                    <th>Questions</th>
                                    <th>Answers</th>
                                    <th>Category</th>
                                    <th>Updated</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody id="faq-table-body">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <span class="spinner-border spinner-border-sm me-2"></span>Loading FAQ workspace...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @component('components.modal-popup')
    @endcomponent
@endsection

@push('scripts')
<script src="{{ URL::asset('build/js/documents/faq-data.js') }}"></script>
@endpush

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Faq</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Content
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Faq</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                    <div class="me-2 mb-2">
                        <div class="dropdown">
                            <a href="javascript:void(0);"
                                class="dropdown-toggle btn btn-white d-inline-flex align-items-center"
                                data-bs-toggle="dropdown">
                                <i class="ti ti-file-export me-1"></i>Export
                            </a>
                            <ul class="dropdown-menu  dropdown-menu-end p-3">
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1"><i
                                            class="ti ti-file-type-pdf me-1"></i>Export as PDF</a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1"><i
                                            class="ti ti-file-type-xls me-1"></i>Export as Excel </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="mb-2">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#add_faq"
                            class="btn btn-primary d-flex align-items-center"><i class="ti ti-circle-plus me-2"></i>Add
                            Faq</a>
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

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <h5>FAQ List</h5>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                        <div class="me-3">
                            <div class="input-icon-end position-relative">
                                <input type="text" class="form-control date-range bookingrange"
                                    placeholder="dd/mm/yyyy - dd/mm/yyyy">
                                <span class="input-icon-addon">
                                    <i class="ti ti-chevron-down"></i>
                                </span>
                            </div>
                        </div>
                        <div class="dropdown">
                            <a href="javascript:void(0);"
                                class="dropdown-toggle btn btn-white d-inline-flex align-items-center"
                                data-bs-toggle="dropdown">
                                Category
                            </a>
                            <ul class="dropdown-menu  dropdown-menu-end p-3">
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">General</a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">Feature</a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1">Employee</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="custom-datatable-filter table-responsive">
                        <table class="table datatable">
                            <thead class="thead-light">
                                <tr>
                                    <th class="no-sort">
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox" id="select-all">
                                        </div>
                                    </th>
                                    <th>Questions</th>
                                    <th>Answers</th>
                                    <th>Categories</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox">
                                        </div>
                                    </td>
                                    <td>
                                        <h6 class="fw-medium"><a href="#">What is an HRMS?</a></h6>
                                    </td>
                                    <td>Software system that automates and manages various human resources tasks</td>
                                    <td>General</td>
                                    <td>
                                        <div class="action-icon d-inline-flex">
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit_faq"
                                                class="me-2"><i class="ti ti-edit"></i></a>
                                            <a href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#delete_modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox">
                                        </div>
                                    </td>
                                    <td>
                                        <h6 class="fw-medium"><a href="#">How does an HRMS benefit organizations?</a>
                                        </h6>
                                    </td>
                                    <td>It enhances operational efficiency, reduces manual errors, and centralizes HR tasks
                                    </td>
                                    <td>General</td>
                                    <td>
                                        <div class="action-icon d-inline-flex">
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit_faq"
                                                class="me-2"><i class="ti ti-edit"></i></a>
                                            <a href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#delete_modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox">
                                        </div>
                                    </td>
                                    <td>
                                        <h6 class="fw-medium"><a href="#">Is the data stored in an SmartHR
                                                secure?</a></h6>
                                    </td>
                                    <td>Yes, SmartHR is design with advanced security measures, including data encryption
                                    </td>
                                    <td>Feature</td>
                                    <td>
                                        <div class="action-icon d-inline-flex">
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit_faq"
                                                class="me-2"><i class="ti ti-edit"></i></a>
                                            <a href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#delete_modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox">
                                        </div>
                                    </td>
                                    <td>
                                        <h6 class="fw-medium"><a href="#">How do I add a new employee to the
                                                HRMS?</a></h6>
                                    </td>
                                    <td>Add new employees by entering their personal details & setting up their profiles.
                                    </td>
                                    <td>Employee</td>
                                    <td>
                                        <div class="action-icon d-inline-flex">
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit_faq"
                                                class="me-2"><i class="ti ti-edit"></i></a>
                                            <a href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#delete_modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox">
                                        </div>
                                    </td>
                                    <td>
                                        <h6 class="fw-medium"><a href="#">How do I generate custom reports in the
                                                SmartHR?</a></h6>
                                    </td>
                                    <td>Custom reports can be generated using the reporting module within the HRMS</td>
                                    <td>Reports</td>
                                    <td>
                                        <div class="action-icon d-inline-flex">
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit_faq"
                                                class="me-2"><i class="ti ti-edit"></i></a>
                                            <a href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#delete_modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox">
                                        </div>
                                    </td>
                                    <td>
                                        <h6 class="fw-medium"><a href="#">How do I schedule training sessions in the
                                                HRMS?</a></h6>
                                    </td>
                                    <td>Creating training events, setting dates and times, and enrolling employees</td>
                                    <td>Leaves</td>
                                    <td>
                                        <div class="action-icon d-inline-flex">
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit_faq"
                                                class="me-2"><i class="ti ti-edit"></i></a>
                                            <a href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#delete_modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox">
                                        </div>
                                    </td>
                                    <td>
                                        <h6 class="fw-medium"><a href="#">How do I process payroll in the
                                                SmartHR?</a></h6>
                                    </td>
                                    <td>Reviewing employee hours and deductions and executing payments.</td>
                                    <td>Payroll</td>
                                    <td>
                                        <div class="action-icon d-inline-flex">
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit_faq"
                                                class="me-2"><i class="ti ti-edit"></i></a>
                                            <a href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#delete_modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox">
                                        </div>
                                    </td>
                                    <td>
                                        <h6 class="fw-medium"><a href="#">How do I export reports from the HRMS?</a>
                                        </h6>
                                    </td>
                                    <td>Export reports by selecting the desired report format and using the export function
                                    </td>
                                    <td>Reports</td>
                                    <td>
                                        <div class="action-icon d-inline-flex">
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit_faq"
                                                class="me-2"><i class="ti ti-edit"></i></a>
                                            <a href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#delete_modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox">
                                        </div>
                                    </td>
                                    <td>
                                        <h6 class="fw-medium"><a href="#">Can I track employee attendance and
                                                absences?</a></h6>
                                    </td>
                                    <td>Yes, track attendance and absences by using the attendance management</td>
                                    <td>Employee</td>
                                    <td>
                                        <div class="action-icon d-inline-flex">
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit_faq"
                                                class="me-2"><i class="ti ti-edit"></i></a>
                                            <a href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#delete_modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox">
                                        </div>
                                    </td>
                                    <td>
                                        <h6 class="fw-medium"><a href="#">Can I track employee attendance and
                                                absences?</a></h6>
                                    </td>
                                    <td>Yes, track attendance and absences by using the attendance management</td>
                                    <td>Employee</td>
                                    <td>
                                        <div class="action-icon d-inline-flex">
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit_faq"
                                                class="me-2"><i class="ti ti-edit"></i></a>
                                            <a href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#delete_modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox">
                                        </div>
                                    </td>
                                    <td>
                                        <h6 class="fw-medium"><a href="#">How can I assign a ticket to a specific
                                                team member?</a></h6>
                                    </td>
                                    <td>Assign a ticket by selecting the ticket from the queue, choosing the team member
                                    </td>
                                    <td>Tickets</td>
                                    <td>
                                        <div class="action-icon d-inline-flex">
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit_faq"
                                                class="me-2"><i class="ti ti-edit"></i></a>
                                            <a href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#delete_modal"><i class="ti ti-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

    </div>
    <!-- /Page Wrapper -->
    @component('components.modal-popup')
    @endcomponent
@endsection
