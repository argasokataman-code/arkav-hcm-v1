<?php $page = 'attendance-admin'; ?>
@extends('layout.mainlayout')
@section('content')
    <style>
        [data-attendance-admin-body]:not([data-hydrated="1"]) {
            display: none;
        }
    </style>

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Attendance Admin</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Employee
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Attendance Admin</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                    <div class="me-2 mb-2">
                        <div class="d-flex align-items-center border bg-white rounded p-1 me-2 icon-list">
                            <a href="{{url('attendance-employee')}}" class="btn btn-icon btn-sm  me-1"><i
                                    class="ti ti-brand-days-counter"></i></a>
                            <a href="{{url('attendance-admin')}}" class="btn btn-icon btn-sm active bg-primary text-white"><i
                                    class="ti ti-calendar-event"></i></a>
                        </div>
                    </div>
                    <div class="me-2 mb-2">
                        <div class="dropdown">
                            <a href="javascript:void(0);"
                                class="dropdown-toggle btn btn-white d-inline-flex align-items-center"
                                data-bs-toggle="dropdown">
                                <i class="ti ti-file-export me-1"></i>Export
                            </a>
                            <ul class="dropdown-menu  dropdown-menu-end p-3">
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1" data-attendance-admin-export="csv"><i
                                            class="ti ti-file-type-xls me-1"></i>Export CSV</a>
                                </li>
                                <li>
                                    <span class="dropdown-item rounded-1 text-muted">CSV format only</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="mb-2">
                        <a href="{{ url('attendance-report') }}" class="btn btn-primary d-flex align-items-center">
                            <i class="ti ti-file-analytics me-2"></i>Report
                        </a>
                    </div>
                    <!-- GAP-O: open attendance settings modal -->
                    <div class="ms-2 mb-2">
                        <button type="button" class="btn btn-light d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#arcav_attendance_settings" data-attendance-settings-open>
                            <i class="ti ti-settings me-1"></i>Settings
                        </button>
                    </div>
                    <div class="ms-2 head-icons">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <div class="card border-0">
                <div class="card-body">
                    <div class="row align-items-center mb-4">
                        <div class="col-md-5">
                            <div class="mb-3 mb-md-0">
                                <h4 class="mb-1" data-attendance-admin-heading>Attendance</h4>
                                <p data-attendance-admin-subtitle>Loading employee count…</p>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="d-flex align-items-center justify-content-md-end flex-wrap gap-3">
                                <div class="d-flex align-items-center" title="Karyawan sudah check-in untuk tanggal ini">
                                    <h6 class="mb-0 me-2 text-muted">Present</h6>
                                    <span class="avatar bg-success avatar-rounded text-fixed-white fs-12 d-inline-flex align-items-center justify-content-center"
                                        style="min-width:2.25rem; min-height:2.25rem;" data-attendance-admin-present-quick>—</span>
                                </div>
                                <div class="d-flex align-items-center" title="Karyawan belum check-in untuk tanggal ini">
                                    <h6 class="mb-0 me-2 text-muted">Absent</h6>
                                    <span class="avatar bg-warning avatar-rounded text-dark fs-12 d-inline-flex align-items-center justify-content-center"
                                        style="min-width:2.25rem; min-height:2.25rem;" data-attendance-admin-absentees>—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="border rounded">
                        <div class="row gx-0">
                            <div class="col-md col-sm-4 border-end">
                                <div class="p-3">
                                    <span class="fw-medium mb-1 d-block">Present</span>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <h5 data-attendance-admin-stat="present">—</h5>
                                        <span class="text-muted fs-12">—</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md col-sm-4 border-end">
                                <div class="p-3">
                                    <span class="fw-medium mb-1 d-block">Late Login</span>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <h5 data-attendance-admin-stat="late">—</h5>
                                        <span class="text-muted fs-12">—</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md col-sm-4 border-end">
                                <div class="p-3">
                                    <span class="fw-medium mb-1 d-block">Uninformed</span>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <h5 data-attendance-admin-stat="uninformed">—</h5>
                                        <span class="text-muted fs-12">—</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md col-sm-4 border-end">
                                <div class="p-3">
                                    <span class="fw-medium mb-1 d-block">Permission</span>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <h5 data-attendance-admin-stat="permission">—</h5>
                                        <span class="text-muted fs-12">—</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md col-sm-4">
                                <div class="p-3">
                                    <span class="fw-medium mb-1 d-block">Absent</span>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <h5 data-attendance-admin-stat="absent">—</h5>
                                        <span class="text-muted fs-12">—</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <h5 class="d-flex align-items-center gap-2">
                        Admin Attendance
                    </h5>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                    
                            <div class="me-3">
                                <input type="date" class="form-control" data-attendance-admin-date title="Work date">
                            </div>
                            <div class="me-3">
                                <select class="form-select" data-attendance-admin-filter-department title="Department filter">
                                    <option value="">All departments</option>
                                </select>
                            </div>
                            <div class="me-3">
                                <select class="form-select" data-attendance-admin-filter-status title="Status filter">
                                    <option value="">All status</option>
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="needs_review">Needs Review</option>
                                </select>
                            </div>
                            <div>
                                <select class="form-select" data-attendance-admin-sort title="Sort rows">
                                    <option value="name_asc">Sort: Name A-Z</option>
                                    <option value="name_desc">Sort: Name Z-A</option>
                                    <option value="checkin_asc">Sort: Check-in earliest</option>
                                    <option value="checkin_desc">Sort: Check-in latest</option>
                                    <option value="production_desc">Sort: Production high-low</option>
                                    <option value="production_asc">Sort: Production low-high</option>
                                </select>
                            </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="custom-datatable-filter table-responsive">
                        <table class="table" id="attendance-admin-table">
                            <thead class="thead-light">
                                <tr>
                                    <th class="no-sort">
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox" id="select-all">
                                        </div>
                                    </th>
                                    <th>Employee</th>
                                    <th>Status</th>
                                    <th>Check In</th>
                                    <th>Check In Location</th>
                                    <th>Check Out</th>
                                    <th>Check Out Location</th>
                                    <th>Break</th>
                                    <th>Late</th>
                                    <th>Production Hours</th>
                                    <th>Selfie</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-attendance-admin-body>
                                <tr>
                                    <td class="text-center text-muted py-4">Loading attendance...</td>
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
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2" data-attendance-admin-pagination style="display: none;">
                    <span class="text-muted small" data-attendance-admin-page-info></span>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-light border" data-attendance-admin-prev>Sebelumnya</button>
                        <button type="button" class="btn btn-sm btn-light border" data-attendance-admin-next>Berikutnya</button>
                    </div>
                </div>
            </div>

        </div>


    </div>
    <!-- /Page Wrapper -->

    <!-- GAP-O: Attendance Settings Modal -->
    <div class="modal fade" id="arcav_attendance_settings" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ti ti-settings me-1"></i>Attendance Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="correction_window_days_input">Correction Request Window (days)</label>
                        <input type="number" id="correction_window_days_input" class="form-control"
                            min="0" max="365"
                            data-attendance-settings-field="correctionWindowDays"
                            placeholder="30">
                        <div class="form-text">Jumlah hari ke belakang karyawan boleh mengajukan koreksi. Isi <strong>0</strong> untuk tanpa batas.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" data-attendance-settings-save>
                        <i class="ti ti-device-floppy me-1"></i>Save Settings
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- /Attendance Settings Modal -->

    @component('components.modal-popup')
    @endcomponent

@endsection