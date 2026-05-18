<?php $page = 'employees'; ?>
@extends('layout.mainlayout')
@section('content')
    <style>
        [data-employees-list-body]:not([data-hydrated="1"]),
        [data-employees-grid-body]:not([data-hydrated="1"]) {
            display: none;
        }

        .employee-step-btn.active,
        .employee-step-btn.btn-primary {
            background: var(--bs-primary);
            border-color: var(--bs-primary);
            color: #fff;
        }

        .employee-step-pane .form-label {
            font-weight: 600;
        }
    </style>

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Employee</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Employee
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Employee List</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                    <div class="me-2 mb-2">
                        <div class="d-flex align-items-center border bg-white rounded p-1 me-2 icon-list" data-employees-view-toggle>
                            <a href="/employees?view=list" data-view="list" class="btn btn-icon btn-sm me-1"><i class="ti ti-list-tree"></i></a>
                            <a href="/employees?view=grid" data-view="grid" class="btn btn-icon btn-sm"><i class="ti ti-layout-grid"></i></a>
                        </div>
                    </div>
                    <div class="me-2 mb-2">
                        <div class="dropdown">
                            <a href="javascript:void(0);" class="dropdown-toggle btn btn-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
                                <i class="ti ti-file-export me-1"></i>Export
                            </a>
                            <ul class="dropdown-menu  dropdown-menu-end p-3">
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1" data-employees-export="pdf"><i class="ti ti-file-type-pdf me-1"></i>Export as PDF</a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1" data-employees-export="xlsx"><i class="ti ti-file-type-xls me-1"></i>Export as Excel </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="me-2 mb-2">
                        <a href="/v1/hcm/employees/bulk-template" class="btn btn-outline-secondary d-flex align-items-center" data-employee-bulk-template-link>
                            <i class="ti ti-file-download me-2"></i>Template Bulk Employee
                        </a>
                    </div>
                        <div class="me-2 mb-2">
                            <button type="button" class="btn btn-outline-warning d-flex align-items-center" data-employees-bulk-reassign-open disabled>
                                <i class="ti ti-users-group me-2"></i>Reassign Team (<span data-employees-selected-count>0</span>)
                            </button>
                        </div>
                    <div class="me-2 mb-2">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#employee_bulk_upload" class="btn btn-outline-primary d-flex align-items-center" data-employee-bulk-upload-open>
                            <i class="ti ti-upload me-2"></i>Bulk Upload Employee
                        </a>
                    </div>
                    <div class="mb-2">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#add_employee" class="btn btn-primary d-flex align-items-center"><i class="ti ti-circle-plus me-2"></i>Add Employee</a>
                    </div>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <div class="card mb-3 d-none" data-employees-scope-tabs-wrap>
                <div class="card-body py-2 px-3">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <h6 class="mb-1">Employee Scope</h6>
                            <small class="text-muted">Pilih tampilan lintas tenant atau fokus ke tenant aktif untuk Super Admin Code 1.</small>
                        </div>
                        <ul class="nav nav-pills" data-employees-scope-tabs role="tablist" aria-label="Employee scope tabs">
                            <li class="nav-item" role="presentation">
                                <button type="button" class="nav-link" data-employees-scope-tab="global" aria-pressed="false">Semua Tenant</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button type="button" class="nav-link" data-employees-scope-tab="active_company" aria-pressed="false">Employee Tenant Aktif (Super Admin Code 1)</button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="row">

                <!-- Total Employee -->
                <div class="col-lg-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body d-flex align-items-center">
                            <div class="d-flex align-items-center overflow-hidden">
                                <div>
                                    <span class="avatar avatar-lg bg-dark rounded-circle"><i class="ti ti-users"></i></span>
                                </div>
                                <div class="ms-2 overflow-hidden">
                                    <p class="fs-12 fw-medium mb-1 text-truncate">Total Employee</p>
                                    <h4 data-employees-total>0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /Total Employee -->

                <!-- Active -->
                <div class="col-lg-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body d-flex align-items-center">
                            <div class="d-flex align-items-center overflow-hidden">
                                <div>
                                    <span class="avatar avatar-lg bg-success rounded-circle"><i class="ti ti-user-share"></i></span>
                                </div>
                                <div class="ms-2 overflow-hidden">
                                    <p class="fs-12 fw-medium mb-1 text-truncate">Active</p>
                                    <h4 data-employees-active>0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /Active -->

                <!-- Inactive -->
                <div class="col-lg-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body d-flex align-items-center">
                            <div class="d-flex align-items-center overflow-hidden">
                                <div>
                                    <span class="avatar avatar-lg bg-danger rounded-circle"><i class="ti ti-user-pause"></i></span>
                                </div>
                                <div class="ms-2 overflow-hidden">
                                    <p class="fs-12 fw-medium mb-1 text-truncate">Inactive</p>
                                    <h4 data-employees-inactive>0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /Inactive -->

                <!-- New Joiners -->
                <div class="col-lg-3 col-md-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-body d-flex align-items-center">
                            <div class="d-flex align-items-center overflow-hidden">
                                <div>
                                    <span class="avatar avatar-lg bg-info rounded-circle"><i class="ti ti-user-plus"></i></span>
                                </div>
                                <div class="ms-2 overflow-hidden">
                                    <p class="fs-12 fw-medium mb-1 text-truncate">New Joiners</p>
                                    <h4 data-employees-new-joiners>0</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /New Joiners -->

            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <h5>Plan List</h5>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                        <div class="me-3">
                            <div class="input-icon-end position-relative">
                                <input type="text" class="form-control" data-employees-search placeholder="Search name, email, phone, NIK...">
                                <span class="input-icon-addon">
                                    <i class="ti ti-search"></i>
                                </span>
                            </div>
                        </div>
                        <div class="me-3">
                            <select class="form-select" data-employees-filter-department>
                                <option value="">All Departments</option>
                            </select>
                        </div>
                        <div class="me-3">
                            <select class="form-select" data-employees-filter-designation>
                                <option value="">All Designations</option>
                            </select>
                        </div>
                            <div class="me-3">
                                <select class="form-select" data-employees-filter-team>
                                    <option value="">All Teams</option>
                                </select>
                            </div>
                        <div class="me-3">
                            <select class="form-select" data-employees-filter-status>
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="probation">Probation</option>
                                <option value="inactive">Inactive</option>
                                <option value="resigned">Resigned</option>
                                <option value="terminated">Terminated</option>
                            </select>
                        </div>
                        <div>
                            <select class="form-select" data-employees-per-page>
                                <option value="10">10 / page</option>
                                <option value="20" selected>20 / page</option>
                                <option value="50">50 / page</option>
                                <option value="100">100 / page</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="px-3 py-2 border-bottom bg-light">
                        <small class="text-muted">
                            Tip: klik baris employee untuk quick preview, lalu lanjut ke detail lengkap jika diperlukan.
                        </small>
                    </div>
                    <div class="custom-datatable-filter table-responsive">
                        <table class="table" data-employees-table>
                            <thead class="thead-light">
                                <tr>
                                    <th class="no-sort">
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox" data-employees-select-all>
                                        </div>
                                    </th>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Team</th>
                                    <th>Team Leader</th>
                                    <th>Department</th>
                                    <th>Designation</th>
                                    <th>Joining Date</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-employees-list-body>
                                <tr>
                                    <td class="text-center text-muted py-4">Loading...</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-3 border-top" data-employees-pagination-wrap>
                        <small class="text-muted" data-employees-showing>Showing 0 - 0 of 0 entries</small>
                        <ul class="pagination pagination-sm mb-0" data-employees-pagination></ul>
                    </div>
                </div>
            </div>

        </div>

    </div>
    <!-- /Page Wrapper -->

    @component('components.modal-popup')
    @endcomponent
    @include('hcm.partials.employee-stepper-modal', ['mode' => 'add'])

    <div class="offcanvas offcanvas-end" tabindex="-1" id="employee_quick_preview" aria-labelledby="employee_quick_preview_label">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="employee_quick_preview_label">Employee Quick Preview</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div data-employee-quick-preview-content>
                <p class="text-muted mb-0">Pilih employee dari list untuk melihat ringkasan.</p>
            </div>
            <div class="mt-3">
                <a href="#" class="btn btn-primary w-100 d-none" data-employee-quick-open-link>
                    Lihat detail lengkap
                </a>
            </div>
        </div>
    </div>

    <div class="modal fade" id="employee_bulk_team_reassign" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Reassign Team</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form data-employees-bulk-reassign-form>
                    <div class="modal-body">
                        <p class="text-muted mb-3">
                            Aksi ini akan memindahkan semua employee terpilih ke team tujuan yang sama.
                        </p>
                        <div class="alert alert-light border mb-3 py-2 px-3">
                            Selected employee: <strong data-employees-bulk-selected-count>0</strong>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Target Team</label>
                            <select class="form-select" data-employees-bulk-target-team required>
                                <option value="">Select target team</option>
                            </select>
                            <small class="text-muted d-block mt-1">Pilih "Unassign Team" jika ingin mengosongkan assignment team.</small>
                        </div>
                        <div class="alert d-none mb-0" data-employees-bulk-reassign-result></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning" data-employees-bulk-submit>Apply Reassign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="employee_bulk_upload" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Upload Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form data-employee-bulk-upload-form>
                    <div class="modal-body">
                        <p class="text-muted mb-3">
                            Gunakan template Excel resmi untuk update/create data employee secara massal berdasarkan identifier employee (kolom teknis: employee_uuid) atau email.
                        </p>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <a href="{{ url('/v1/hcm/employees/bulk-template') }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener noreferrer" data-employee-bulk-template-link>
                                <i class="ti ti-download me-1"></i>Download latest template
                            </a>
                            <span class="badge bg-soft-info text-info align-self-center">Multi-sheet workbook + master references</span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">File Excel</label>
                            <input type="file" class="form-control" accept=".xlsx,.xls,.csv" data-employee-bulk-upload-file required>
                            <small class="text-muted d-block mt-1">Format didukung: `.xlsx`, `.xls`, `.csv`.</small>
                        </div>
                        <div class="alert alert-light border mb-3">
                            <strong>Kolom penting:</strong> employee_uuid, name, email, department, designation, department_id, designation_id, team_id, employment_status, contract_type, contract_status, probation_end_date, tax_status, dll.<br>
                            Kolom <strong>department</strong> dan <strong>designation</strong> sekarang tersedia sebagai dropdown berdasarkan master aktif. Kolom ID lama tetap bisa dipakai untuk kompatibilitas.<br>
                            Untuk create baru wajib `name`, `email`, `password`, `confirm_password`. Jika ada 1 baris invalid, seluruh import dibatalkan agar data tetap konsisten.
                        </div>
                        <div class="alert d-none mb-0" data-employee-bulk-upload-results></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="employee_bulk_org_required" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lengkapi Master Organisasi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">
                        Bulk employee template membutuhkan master organisasi aktif supaya dropdown department dan designation di workbook bisa terisi dengan benar.
                    </p>
                    <div class="alert alert-warning mb-3" data-employee-bulk-org-required-message>
                        Isi minimal 1 department dan 1 designation sebelum download template atau upload bulk employee.
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ url('/departments') }}" class="btn btn-outline-primary btn-sm">Buka Departments</a>
                        <a href="{{ url('/designations') }}" class="btn btn-outline-primary btn-sm">Buka Designations</a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="employees_photo_preview_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" data-employees-photo-modal-title>Employee Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" alt="Employee photo" class="img-fluid rounded border d-none" data-employees-photo-modal-image>
                    <p class="text-muted mb-0 d-none" data-employees-photo-modal-empty>No profile photo uploaded.</p>
                </div>
            </div>
        </div>
    </div>

@endsection