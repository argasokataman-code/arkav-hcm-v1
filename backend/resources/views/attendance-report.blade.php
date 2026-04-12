<?php $page = 'attendance-report'; ?>
@extends('layout.mainlayout')
@section('content')
    <style>
        [data-attendance-report-body]:not([data-hydrated="1"]) {
            display: none;
        }
    </style>

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content">

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Attendance Report</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            HR
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Attendance Report</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                <div class="mb-2 me-2">
                    <a href="{{ url('attendance-admin') }}" class="btn btn-light d-inline-flex align-items-center">
                        <i class="ti ti-arrow-left me-1"></i>Back
                    </a>
                </div>
                <div class="mb-2 me-2">
                    <div class="dropdown">
                        <a href="javascript:void(0);" class="dropdown-toggle btn btn-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
                            <i class="ti ti-file-export me-1"></i>Export
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end p-3">
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1" data-attendance-report-export="csv">
                                    <i class="ti ti-file-type-xls me-1"></i>Export CSV
                                </a>
                            </li>
                            <li>
                                <span class="dropdown-item rounded-1 text-muted">CSV format only</span>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="head-icons ms-2">
                    <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                        <i class="ti ti-chevrons-up"></i>
                    </a>
                </div>
            </div>
        </div>
        <!-- /Breadcrumb -->
        <div class="row">
            <div class="col-xl-6 d-flex">
                <div class="row flex-fill">
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="d-flex align-items-center overflow-hidden mb-2">
                                    <div class="attendence-icon">
                                        <span><i class="ti ti-calendar text-primary"></i></span>
                                    </div>
                                    <div class="ms-2 overflow-hidden">
                                        <p class="fs-12 fw-normal mb-1 text-truncate">Present</p>
                                        <h4 data-attendance-report-stat-working>—</h4>
                                    </div>
                                </div>
                                <div class="attendance-report-bar mb-2">
                                    <div class="progress" role="progressbar" style="height: 5px;">
                                        <div class="progress-bar bg-secondary" style="width: 0%"></div>
                                    </div>
                                </div>
                                <p class="fs-12 fw-normal text-muted mb-0 text-truncate" data-attendance-report-stat-foot-working>—</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="d-flex align-items-center overflow-hidden mb-2">
                                    <div class="attendence-icon">
                                        <span><i class="ti ti-calendar text-info"></i></span>
                                    </div>
                                    <div class="ms-2 overflow-hidden">
                                        <p class="fs-12 fw-normal mb-1 text-truncate">Absent</p>
                                        <h4 data-attendance-report-stat-leave>—</h4>
                                    </div>
                                </div>
                                <div class="attendance-report-bar mb-2">
                                    <div class="progress" role="progressbar" style="height: 5px;">
                                        <div class="progress-bar bg-secondary" style="width: 0%"></div>
                                    </div>
                                </div>
                                <p class="fs-12 fw-normal text-muted mb-0 text-truncate" data-attendance-report-stat-foot-leave>—</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="d-flex align-items-center overflow-hidden mb-2">
                                    <div class="attendence-icon">
                                        <span><i class="ti ti-calendar text-pink"></i></span>
                                    </div>
                                    <div class="ms-2 overflow-hidden">
                                        <p class="fs-12 fw-normal mb-1 text-truncate">Late login</p>
                                        <h4 data-attendance-report-stat-holiday>—</h4>
                                    </div>
                                </div>
                                <div class="attendance-report-bar mb-2">
                                    <div class="progress" role="progressbar" style="height: 5px;">
                                        <div class="progress-bar bg-secondary" style="width: 0%"></div>
                                    </div>
                                </div>
                                <p class="fs-12 fw-normal text-muted mb-0 text-truncate" data-attendance-report-stat-foot-holiday>—</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="d-flex align-items-center overflow-hidden mb-2">
                                    <div class="attendence-icon">
                                        <span><i class="ti ti-calendar text-warning"></i></span>
                                    </div>
                                    <div class="ms-2 overflow-hidden">
                                        <p class="fs-12 fw-normal mb-1 text-truncate">On roster</p>
                                        <h4 data-attendance-report-stat-halfday>—</h4>
                                    </div>
                                </div>
                                <div class="attendance-report-bar mb-2">
                                    <div class="progress" role="progressbar" style="height: 5px;">
                                        <div class="progress-bar bg-secondary" style="width: 0%"></div>
                                    </div>
                                </div>
                                <p class="fs-12 fw-normal text-muted mb-0 text-truncate" data-attendance-report-stat-foot-halfday>—</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header border-0 pb-0">
                        <div class="d-flex flex-wrap justify-content-between align-items-center">
                            <div class="d-flex align-items-center ">
                                <span class="me-2"><i class="ti ti-chart-line text-danger"></i></span>
                                <h5>Attendance</h5>
                            </div>
                        </div>
                    </div>
                    <div class="card-body py-3 px-3">
                        <div id="attendance-report-chart" style="min-height: 200px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                <h5>Employee Attendance</h5>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                    <div class="me-3">
                        <input type="date" class="form-control" data-attendance-report-date title="Work date">
                    </div>
                    <div class="me-3">
                        <select class="form-select" data-attendance-report-filter-department title="Department filter">
                            <option value="">All departments</option>
                        </select>
                    </div>
                    <div class="me-3">
                        <select class="form-select" data-attendance-report-filter-status title="Status filter">
                            <option value="">All status</option>
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                            <option value="needs_review">Needs Review</option>
                        </select>
                    </div>
                    <div>
                        <select class="form-select" data-attendance-report-sort title="Sort rows">
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
                    <table class="table" id="attendance-report-table">
                        <thead class="thead-light">
                            <tr>
                                <th>Name</th>
                                <th>Date</th>
                                <th>Check In</th>
                                <th>Status</th>
                                <th>Check Out</th>
                                <th>Break</th>
                                <th>Late</th>
                                <th>Overtime</th>
                                <th>Production Hours</th>
                            </tr>
                        </thead>
                        <tbody data-attendance-report-body>
                            <tr>
                                <td class="text-center text-muted py-4">Loading…</td>
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
        </div>

    </div>


</div>
<!-- /Page Wrapper -->

@endsection
