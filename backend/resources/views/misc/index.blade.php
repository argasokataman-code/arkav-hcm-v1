<?php $page = 'index'; ?>
@extends('layout.mainlayout')
@section('content')
    @php
        $dashboardUser = request()->user() ?: auth()->user();
        $dashboardUser?->loadMissing('employeeProfile:id,user_id,profile_photo_path');
        $dashboardProfilePhotoPath = trim((string) ($dashboardUser?->employeeProfile?->profile_photo_path ?? ''));
        $dashboardProfilePhotoUrl = $dashboardProfilePhotoPath !== ''
            ? asset('storage/' . ltrim($dashboardProfilePhotoPath, '/'))
            : URL::asset('build/img/profiles/avatar-31.jpg');
    @endphp

    <!-- Page Wrapper -->
    <div id="auth-guard-root" class="page-wrapper d-none">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Arkav Home Dashboard</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Dashboard
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Admin Dashboard</li>
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
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1" data-index-dashboard-export="csv"><i class="ti ti-file-type-csv me-1"></i>Export as CSV</a>
                                </li>
                                <li>
                                    <a href="javascript:void(0);" class="dropdown-item rounded-1" data-index-dashboard-export="xlsx"><i class="ti ti-file-type-xls me-1"></i>Export as Excel </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="input-icon w-120 position-relative">
                            <span class="input-icon-addon">
                                <i class="ti ti-calendar text-gray-9"></i>
                            </span>
                            <input type="text" class="form-control yearpicker" value="2025">
                        </div>
                    </div>
                    <div class="ms-2 head-icons">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <!-- Welcome Wrap -->
            <div class="card border-0">
                <div class="card-body d-flex align-items-center justify-content-between flex-wrap pb-1">
                    <div class="d-flex align-items-center mb-3">
                        <span class="avatar avatar-xl flex-shrink-0">
                            <img src="{{ $dashboardProfilePhotoUrl }}" class="rounded-circle" alt="img" data-index-welcome-avatar>
                        </span>
                        <div class="ms-3">
                            <h3 class="mb-2">Welcome Back, <span id="welcome-user-name">User</span> <a href="javascript:void(0);" class="edit-icon"><i class="ti ti-edit fs-14"></i></a></h3>
                            <p>Terdapat <span class="text-primary text-decoration-underline" data-approval-leave>-</span> Persetujuan Cuti & <span class="text-primary text-decoration-underline" data-approval-overtime>-</span> Pengajuan Lembur</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Welcome Wrap -->

            @php($showModernAdminDashboard = false)
            @if($showModernAdminDashboard)
                @include('hcm.partials.admin-home-dashboard')
            @endif

            @php($showLegacyIndexWidgets = true)
            @if($showLegacyIndexWidgets)
            <div class="row">

                <!-- Widget Info -->
                <div class="col-xxl-8 d-flex" data-legacy-index-dashboard>
                    <div class="row flex-fill">
                        <div class="col-md-3 d-flex">
                            <div class="card flex-fill">
                                <div class="card-body">
                                    <span class="avatar rounded-circle bg-primary mb-2">
                                        <i class="ti ti-calendar-share fs-16"></i>
                                    </span>
                                    <h6 class="fs-13 fw-medium text-default mb-1">Hadir Hari Ini</h6>
                                    <h3 class="mb-3"><span data-exec-att-present>-</span>/<span data-legacy-att-active>-</span></h3>
                                    <a href="{{url('attendance-employee')}}" class="link-default">Lihat Detail</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex">
                            <div class="card flex-fill">
                                <div class="card-body">
                                    <span class="avatar rounded-circle bg-warning mb-2">
                                        <i class="ti ti-clock-pause fs-16"></i>
                                    </span>
                                    <h6 class="fs-13 fw-medium text-default mb-1">Terlambat Hari Ini</h6>
                                    <h3 class="mb-3"><span data-exec-att-late>-</span></h3>
                                    <a href="{{url('attendance-employee')}}" class="link-default">Lihat Detail</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex">
                            <div class="card flex-fill">
                                <div class="card-body">
                                    <span class="avatar rounded-circle bg-danger mb-2">
                                        <i class="ti ti-user-x fs-16"></i>
                                    </span>
                                    <h6 class="fs-13 fw-medium text-default mb-1">Belum Check-In</h6>
                                    <h3 class="mb-3"><span data-exec-att-missing>-</span></h3>
                                    <a href="{{url('attendance-employee')}}" class="link-default">Lihat Detail</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex">
                            <div class="card flex-fill">
                                <div class="card-body">
                                    <span class="avatar rounded-circle bg-info mb-2">
                                        <i class="ti ti-beach fs-16"></i>
                                    </span>
                                    <h6 class="fs-13 fw-medium text-default mb-1">Cuti Menunggu</h6>
                                    <h3 class="mb-3"><span data-approval-leave>-</span></h3>
                                    <a href="{{url('leave-request')}}" class="link-default">Lihat Semua</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex">
                            <div class="card flex-fill">
                                <div class="card-body">
                                    <span class="avatar rounded-circle bg-secondary mb-2">
                                        <i class="ti ti-clock-bolt fs-16"></i>
                                    </span>
                                    <h6 class="fs-13 fw-medium text-default mb-1">Lembur Menunggu</h6>
                                    <h3 class="mb-3"><span data-approval-overtime>-</span></h3>
                                    <a href="{{url('overtime-request')}}" class="link-default">Lihat Semua</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex">
                            <div class="card flex-fill">
                                <div class="card-body">
                                    <span class="avatar rounded-circle bg-pink mb-2">
                                        <i class="ti ti-user-check fs-16"></i>
                                    </span>
                                    <h6 class="fs-13 fw-medium text-default mb-1">Probation</h6>
                                    <h3 class="mb-3"><span data-exec-probation>-</span></h3>
                                    <a href="{{url('employees')}}" class="link-default">Lihat Semua</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex">
                            <div class="card flex-fill">
                                <div class="card-body">
                                    <span class="avatar rounded-circle bg-purple mb-2">
                                        <i class="ti ti-file-time fs-16"></i>
                                    </span>
                                    <h6 class="fs-13 fw-medium text-default mb-1">Kontrak Habis 30 Hari</h6>
                                    <h3 class="mb-3"><span data-exec-pkwt-due>-</span></h3>
                                    <a href="{{url('employees')}}" class="link-default">Lihat Semua</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex">
                            <div class="card flex-fill">
                                <div class="card-body">
                                    <span class="avatar rounded-circle bg-success mb-2">
                                        <i class="ti ti-user-plus fs-16"></i>
                                    </span>
                                    <h6 class="fs-13 fw-medium text-default mb-1">Joiner Bulan Ini</h6>
                                    <h3 class="mb-3"><span data-signal-joiner>-</span></h3>
                                    <a href="{{url('employees')}}" class="link-default">Lihat Semua</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /Widget Info -->

                <!-- Employees By Department -->
                <div class="col-xxl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                            <h5 class="mb-2">Employees By Department</h5>
                            <div class="dropdown mb-2">
                                <a href="javascript:void(0);" class="btn btn-white border btn-sm d-inline-flex align-items-center" data-bs-toggle="dropdown">
                                    <i class="ti ti-calendar me-1"></i>This Week
                                </a>
                                <ul class="dropdown-menu  dropdown-menu-end p-3">
                                    <li>
                                        <a href="javascript:void(0);" class="dropdown-item rounded-1">This Month</a>
                                    </li>
                                    <li>
                                        <a href="javascript:void(0);" class="dropdown-item rounded-1">This Week</a>
                                    </li>
                                    <li>
                                        <a href="javascript:void(0);" class="dropdown-item rounded-1">Last Week</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="emp-department"></div>
                            <div data-legacy-department-breakdown class="mb-2"></div>
                            <p class="fs-13"><i class="ti ti-circle-filled me-2 fs-8 text-primary"></i>
                                <span data-legacy-department-summary>Distribusi departemen akan tampil otomatis.</span>
                            </p>
                        </div>
                    </div>
                </div>
                <!-- /Employees By Department -->

            </div>

            <div class="row">

                <!-- Total Employee -->
                <div class="col-xxl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                            <h5 class="mb-2">Employee Status</h5>
                            <div class="dropdown mb-2">
                                <a href="javascript:void(0);" class="btn btn-white border btn-sm d-inline-flex align-items-center" data-bs-toggle="dropdown">
                                    <i class="ti ti-calendar me-1"></i>This Week
                                </a>
                                <ul class="dropdown-menu  dropdown-menu-end p-3">
                                    <li>
                                        <a href="javascript:void(0);" class="dropdown-item rounded-1">This Month</a>
                                    </li>
                                    <li>
                                        <a href="javascript:void(0);" class="dropdown-item rounded-1">This Week</a>
                                    </li>
                                    <li>
                                        <a href="javascript:void(0);" class="dropdown-item rounded-1">Today</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <p class="fs-13 mb-3">Total Karyawan</p>
                                <h3 class="mb-3" data-exec-total-employees>-</h3>
                            </div>
                            <div class="progress-stacked emp-stack mb-3">
                                <div class="progress" role="progressbar" aria-label="Active" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" data-progress-active style="width: 0%">
                                    <div class="progress-bar bg-primary"></div>
                                </div>
                                <div class="progress" role="progressbar" aria-label="Probation" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" data-progress-probation style="width: 0%">
                                    <div class="progress-bar bg-warning"></div>
                                </div>
                                <div class="progress" role="progressbar" aria-label="Inactive" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" data-progress-inactive style="width: 0%">
                                    <div class="progress-bar bg-danger"></div>
                                </div>
                                <div class="progress" role="progressbar" aria-label="PKWT Due" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" data-progress-pkwt style="width: 0%">
                                    <div class="progress-bar bg-pink"></div>
                                </div>
                            </div>
                            <div class="border mb-3">
                                <div class="row gx-0">
                                    <div class="col-6">
                                        <div class="p-2 flex-fill border-end border-bottom">
                                            <p class="fs-13 mb-2"><i class="ti ti-square-filled text-primary fs-12 me-2"></i>Aktif <span class="text-gray-9" data-legacy-active-pct>(-%)</span></p>
                                            <h2 class="display-1" data-exec-active>-</h2>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 flex-fill border-bottom text-end">
                                            <p class="fs-13 mb-2"><i class="ti ti-square-filled me-2 text-warning fs-12"></i>Probation <span class="text-gray-9" data-legacy-probation-pct>(-%)</span></p>
                                            <h2 class="display-1" data-exec-probation-card>-</h2>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 flex-fill border-end">
                                            <p class="fs-13 mb-2"><i class="ti ti-square-filled me-2 text-danger fs-12"></i>Tidak Aktif <span class="text-gray-9" data-legacy-inactive-pct>(-%)</span></p>
                                            <h2 class="display-1" data-legacy-inactive>-</h2>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 flex-fill text-end">
                                            <p class="fs-13 mb-2"><i class="ti ti-square-filled text-pink me-2 fs-12"></i>PKWT Habis 30hr <span class="text-gray-9" data-legacy-pkwt-pct>(-%)</span></p>
                                            <h2 class="display-1" data-exec-pkwt-due-card>-</h2>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <h6 class="mb-2">Top Performer</h6>
                            <div class="p-2 d-flex align-items-center justify-content-between border border-primary bg-primary-100 br-5 mb-4">
                                <div class="d-flex align-items-center overflow-hidden">
                                    <span class="me-2">
                                        <i class="ti ti-award-filled text-primary fs-24"></i>
                                    </span>
                                    <a href="{{url('employee-details')}}" class="avatar avatar-md me-2">
                                        <img src="{{ URL::asset('build/img/profiles/avatar-24.jpg') }}" class="rounded-circle border border-white" alt="img">
                                    </a>
                                    <div>
                                        <h6 class="text-truncate mb-1 fs-14 fw-medium"><a href="employee-details" data-legacy-top-performer-name>Daniel Esbella</a></h6>
                                        <p class="fs-13" data-legacy-top-performer-role>IOS Developer</p>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <p class="fs-13 mb-1">Performance</p>
                                    <h5 class="text-primary" data-legacy-top-performer-score>99%</h5>
                                </div>
                            </div>
                            <a href="{{url('employees')}}" class="btn btn-light btn-md w-100">View All Employees</a>
                        </div>
                    </div>
                </div>
                <!-- /Total Employee -->

                <!-- Attendance Overview -->
                <div class="col-xxl-4 col-xl-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                            <h5 class="mb-2">Attendance Overview</h5>
                            <div class="dropdown mb-2">
                                <a href="javascript:void(0);" class="btn btn-white border btn-sm d-inline-flex align-items-center" data-bs-toggle="dropdown">
                                    <i class="ti ti-calendar me-1"></i>Today
                                </a>
                                <ul class="dropdown-menu  dropdown-menu-end p-3">
                                    <li>
                                        <a href="javascript:void(0);" class="dropdown-item rounded-1">This Month</a>
                                    </li>
                                    <li>
                                        <a href="javascript:void(0);" class="dropdown-item rounded-1">This Week</a>
                                    </li>
                                    <li>
                                        <a href="javascript:void(0);" class="dropdown-item rounded-1">Today</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chartjs-wrapper-demo position-relative mb-4">
                                <canvas id="attendance" height="200"></canvas>
                                <div class="position-absolute text-center attendance-canvas">
                                    <p class="fs-13 mb-1">Total Attendance</p>
                                    <h3 data-legacy-attendance-total>0</h3>
                                </div>
                            </div>
                            <h6 class="mb-3">Status</h6>
                            <div class="d-flex align-items-center justify-content-between">
                                <p class="f-13 mb-2"><i class="ti ti-circle-filled text-success me-1"></i>Present</p>
                                <p class="f-13 fw-medium text-gray-9 mb-2" data-legacy-attendance-present-pct>0%</p>
                            </div>
                            <div class="d-flex align-items-center justify-content-between">
                                <p class="f-13 mb-2"><i class="ti ti-circle-filled text-secondary me-1"></i>Late</p>
                                <p class="f-13 fw-medium text-gray-9 mb-2" data-legacy-attendance-late-pct>0%</p>
                            </div>
                            <div class="d-flex align-items-center justify-content-between">
                                <p class="f-13 mb-2"><i class="ti ti-circle-filled text-warning me-1"></i>Permission</p>
                                <p class="f-13 fw-medium text-gray-9 mb-2" data-legacy-attendance-permission-pct>0%</p>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <p class="f-13 mb-2"><i class="ti ti-circle-filled text-danger me-1"></i>Absent</p>
                                <p class="f-13 fw-medium text-gray-9 mb-2" data-legacy-attendance-absent-pct>0%</p>
                            </div>
                            <div class="bg-light br-5 box-shadow-xs p-2 pb-0 d-flex align-items-center justify-content-between flex-wrap">
                                <div class="d-flex align-items-center">
                                    <p class="mb-2 me-2">Total Absenties: <span data-legacy-attendance-absent-total>0</span></p>
                                    <div class="avatar-list-stacked avatar-group-sm mb-2">
                                        <span class="avatar avatar-rounded">
                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-27.jpg') }}" alt="img">
                                        </span>
                                        <span class="avatar avatar-rounded">
                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-30.jpg') }}" alt="img">
                                        </span>
                                        <span class="avatar avatar-rounded">
                                            <img src="{{ URL::asset('build/img/profiles/avatar-14.jpg') }}" alt="img">
                                        </span>
                                        <span class="avatar avatar-rounded">
                                            <img src="{{ URL::asset('build/img/profiles/avatar-29.jpg') }}" alt="img">
                                        </span>
                                        <a class="avatar bg-primary avatar-rounded text-fixed-white fs-10" href="javascript:void(0);">
                                            +1
                                        </a>
                                    </div>
                                </div>
                                <a href="{{url('leaves')}}" class="fs-13 link-primary text-decoration-underline mb-2">View Details</a>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /Attendance Overview -->

                <!-- Clock-In/Out -->
                <div class="col-xxl-4 col-xl-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                            <h5 class="mb-2">Clock-In/Out</h5>
                            <div class="d-flex align-items-center">
                                <div class="dropdown mb-2">
                                    <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-sm d-inline-flex align-items-center border-0 fs-13 me-2" data-bs-toggle="dropdown">
                                        All Departments
                                    </a>
                                    <ul class="dropdown-menu  dropdown-menu-end p-3">
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Finance</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Development</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Marketing</a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="dropdown mb-2">
                                    <a href="javascript:void(0);" class="btn btn-white border btn-sm d-inline-flex align-items-center" data-bs-toggle="dropdown">
                                        <i class="ti ti-calendar me-1"></i>Today
                                    </a>
                                    <ul class="dropdown-menu  dropdown-menu-end p-3">
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">This Month</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">This Week</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Today</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div data-legacy-clock-list></div>
                            <h6 class="mb-2">Late</h6>
                            <div data-legacy-late-list></div>
                            <a href="{{url('attendance-report') }}" class="btn btn-light btn-md w-100">View All Attendance</a>
                        </div>
                    </div>
                </div>
                <!-- /Clock-In/Out -->

            </div>

            <div class="row">

                @if(false)
                <!-- Jobs Applicants -->
                <div class="col-xxl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                            <h5 class="mb-2">Jobs Applicants</h5>
                            <a href="{{url('job-list')}}" class="btn btn-light btn-md mb-2">View All</a>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs tab-style-1 nav-justified d-sm-flex d-block p-0 mb-4" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link fw-medium" data-bs-toggle="tab" data-bs-target="#openings" aria-current="page" href="#openings" aria-selected="true" role="tab">Openings</a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link fw-medium active" data-bs-toggle="tab" data-bs-target="#applicants" href="#applicants" aria-selected="false" tabindex="-1" role="tab">Applicants</a>
                                </li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane fade" id="openings">
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center">
                                            <a href="#" class="avatar overflow-hidden flex-shrink-0 bg-gray-100">
                                                <img src="{{ URL::asset('build/img/icons/apple.svg') }}" class="img-fluid rounded-circle w-auto h-auto" alt="img">
                                            </a>
                                            <div class="ms-2 overflow-hidden">
                                                <p class="text-dark fw-medium text-truncate mb-0"><a href="javascript:void(0);">Senior IOS Developer</a></p>
                                                <span class="fs-12">No of Openings : 25 </span>
                                            </div>
                                        </div>
                                        <a href="javascript:void(0);" class="btn btn-light btn-sm p-0 btn-icon d-flex align-items-center justify-content-center"><i class="ti ti-edit"></i></a>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center">
                                            <a href="#" class="avatar overflow-hidden flex-shrink-0 bg-gray-100">
                                                <img src="{{ URL::asset('build/img/icons/php.svg') }}" class="img-fluid w-auto h-auto" alt="img">
                                            </a>
                                            <div class="ms-2 overflow-hidden">
                                                <p class="text-dark fw-medium text-truncate mb-0"><a href="javascript:void(0);">Junior PHP Developer</a></p>
                                                <span class="fs-12">No of Openings : 20 </span>
                                            </div>
                                        </div>
                                        <a href="javascript:void(0);" class="btn btn-light btn-sm p-0 btn-icon d-flex align-items-center justify-content-center"><i class="ti ti-edit"></i></a>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center">
                                            <a href="#" class="avatar overflow-hidden flex-shrink-0 bg-gray-100">
                                                <img src="{{ URL::asset('build/img/icons/react.svg') }}" class="img-fluid w-auto h-auto" alt="img">
                                            </a>
                                            <div class="ms-2 overflow-hidden">
                                                <p class="text-dark fw-medium text-truncate mb-0"><a href="javascript:void(0);">Junior React Developer </a></p>
                                                <span class="fs-12">No of Openings : 30 </span>
                                            </div>
                                        </div>
                                        <a href="javascript:void(0);" class="btn btn-light btn-sm p-0 btn-icon d-flex align-items-center justify-content-center"><i class="ti ti-edit"></i></a>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-0">
                                        <div class="d-flex align-items-center">
                                            <a href="#" class="avatar overflow-hidden flex-shrink-0 bg-gray-100">
                                                <img src="{{ URL::asset('build/img/icons/laravel-icon.svg') }}" class="img-fluid w-auto h-auto" alt="img">
                                            </a>
                                            <div class="ms-2 overflow-hidden">
                                                <p class="text-dark fw-medium text-truncate mb-0"><a href="javascript:void(0);">Senior Laravel Developer</a></p>
                                                <span class="fs-12">No of Openings : 40 </span>
                                            </div>
                                        </div>
                                        <a href="javascript:void(0);" class="btn btn-light btn-sm p-0 btn-icon d-flex align-items-center justify-content-center"><i class="ti ti-edit"></i></a>
                                    </div>
                                </div>
                                <div class="tab-pane fade show active" id="applicants">
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center">
                                            <a href="#" class="avatar overflow-hidden flex-shrink-0">
                                                <img src="{{ URL::asset('build/img/users/user-09.jpg') }}" class="img-fluid rounded-circle" alt="img">
                                            </a>
                                            <div class="ms-2 overflow-hidden">
                                                <p class="text-dark fw-medium text-truncate mb-0"><a href="#">Brian Villalobos</a></p>
                                                <span class="fs-13 d-inline-flex align-items-center">Exp : 5+ Years<i class="ti ti-circle-filled fs-4 mx-2 text-primary"></i>USA</span>
                                            </div>
                                        </div>
                                        <span class="badge badge-secondary badge-xs">UI/UX Designer</span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center">
                                            <a href="#" class="avatar overflow-hidden flex-shrink-0">
                                                <img src="{{ URL::asset('build/img/users/user-32.jpg') }}" class="img-fluid rounded-circle" alt="img">
                                            </a>
                                            <div class="ms-2 overflow-hidden">
                                                <p class="text-dark fw-medium text-truncate mb-0"><a href="#">Anthony Lewis</a></p>
                                                <span class="fs-13 d-inline-flex align-items-center">Exp : 4+ Years<i class="ti ti-circle-filled fs-4 mx-2 text-primary"></i>USA</span>
                                            </div>
                                        </div>
                                        <span class="badge badge-info badge-xs">Python Developer</span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center">
                                            <a href="#" class="avatar overflow-hidden flex-shrink-0">
                                                <img src="{{ URL::asset('build/img/users/user-32.jpg') }}" class="img-fluid rounded-circle" alt="img">
                                            </a>
                                            <div class="ms-2 overflow-hidden">
                                                <p class="text-dark fw-medium text-truncate mb-0"><a href="#">Stephan Peralt</a></p>
                                                <span class="fs-13 d-inline-flex align-items-center">Exp : 6+ Years<i class="ti ti-circle-filled fs-4 mx-2 text-primary"></i>USA</span>
                                            </div>
                                        </div>
                                        <span class="badge badge-pink badge-xs">Android Developer</span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-0">
                                        <div class="d-flex align-items-center">
                                            <a href="javascript:void(0);" class="avatar overflow-hidden flex-shrink-0">
                                                <img src="{{ URL::asset('build/img/users/user-34.jpg') }}" class="img-fluid rounded-circle" alt="img">
                                            </a>
                                            <div class="ms-2 overflow-hidden">
                                                <p class="text-dark fw-medium text-truncate mb-0"><a href="javascript:void(0);">Doglas Martini</a></p>
                                                <span class="fs-13 d-inline-flex align-items-center">Exp : 2+ Years<i class="ti ti-circle-filled fs-4 mx-2 text-primary"></i>USA</span>
                                            </div>
                                        </div>
                                        <span class="badge badge-purple badge-xs">React Developer</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /Jobs Applicants -->
                @endif
                
                <!-- Employees -->
                <div class="col-xxl-12 col-xl-12 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                            <h5 class="mb-2">Employees</h5>
                            <a href="{{url('employees')}}" class="btn btn-light btn-md mb-2">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">	
                                <table class="table table-nowrap mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Department</th>
                                        </tr>
                                    </thead>
                                    <tbody data-legacy-employees-body>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /Employees -->
                
            </div>

            <div class="row">
                
                @if(false)
                <!-- Sales Overview -->
                <div class="col-xl-7 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                            <h5 class="mb-2">Sales Overview</h5>
                            <div class="d-flex align-items-center">
                                <div class="dropdown mb-2">
                                    <a href="javascript:void(0);" class="dropdown-toggle btn btn-white border-0 btn-sm d-inline-flex align-items-center fs-13 me-2" data-bs-toggle="dropdown">
                                        All Departments
                                    </a>
                                    <ul class="dropdown-menu  dropdown-menu-end p-3">
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">UI/UX Designer</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">HR Manager</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Junior Tester</a>
                                        </li>
                                    </ul>
                                </div>	
                            </div>
                        </div>
                        <div class="card-body pb-0">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                <div class="d-flex align-items-center mb-1">
                                    <p class="fs-13 text-gray-9 me-3 mb-0"><i class="ti ti-square-filled me-2 text-primary"></i>Income</p>
                                    <p class="fs-13 text-gray-9 mb-0"><i class="ti ti-square-filled me-2 text-gray-2"></i>Expenses</p>
                                </div>
                                <p class="fs-13 mb-1">Last Updated at 11:30PM</p>
                            </div>
                            <div id="sales-income"></div>
                        </div>
                    </div>
                </div>
                <!-- /Sales Overview -->
                @endif
                
                <!-- Invoices -->
                <div class="col-xl-12 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                            <h5 class="mb-2">Invoices</h5>
                            <div class="d-flex align-items-center">
                                <div class="dropdown mb-2">
                                    <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-sm d-inline-flex align-items-center fs-13 me-2 border-0" data-bs-toggle="dropdown">
                                        Invoices
                                    </a>
                                    <ul class="dropdown-menu  dropdown-menu-end p-3">
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Invoices</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Paid</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Unpaid</a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="dropdown mb-2">
                                    <a href="javascript:void(0);" class="btn btn-white border btn-sm d-inline-flex align-items-center"  data-bs-toggle="dropdown">
                                        <i class="ti ti-calendar me-1"></i>This Week
                                    </a>
                                    <ul class="dropdown-menu  dropdown-menu-end p-3">
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">This Month</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">This Week</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Today</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pt-2">
                            <div class="table-responsive pt-1">	
                                <table class="table table-nowrap table-borderless mb-0">
                                    <tbody data-legacy-invoices-body>
                                    </tbody>
                                </table>
                            </div>
                            <a href="{{url('invoice')}}" class="btn btn-light btn-md w-100 mt-2">View All</a>
                        </div>
                    </div>
                </div>
                <!-- /Invoices -->

            </div>

            </div>

            <div class="row">

                @if(false)
                <!-- Schedules -->
                <div class="col-xxl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                            <h5 class="mb-2">Schedules</h5>
                            <a href="{{url('candidates')}}" class="btn btn-light btn-md mb-2">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="bg-light p-3 br-5 mb-4">
                                <span class="badge badge-secondary badge-xs mb-1">UI/ UX Designer</span>
                                <h6 class="mb-2 text-truncate">Interview Candidates - UI/UX Designer</h6>
                                <div class="d-flex align-items-center flex-wrap">
                                    <p class="fs-13 mb-1 me-2"><i class="ti ti-calendar-event me-2"></i>Thu, 15 Feb 2025</p>
                                    <p class="fs-13 mb-1"><i class="ti ti-clock-hour-11 me-2"></i>01:00 PM - 02:20 PM</p>
                                </div>
                                <div class="d-flex align-items-center justify-content-between border-top mt-2 pt-3">
                                    <div class="avatar-list-stacked avatar-group-sm">
                                        <span class="avatar avatar-rounded">
                                            <img class="border border-white" src="{{ URL::asset('build/img/users/user-49.jpg') }}" alt="img">
                                        </span>
                                        <span class="avatar avatar-rounded">
                                            <img class="border border-white" src="{{ URL::asset('build/img/users/user-13.jpg') }}" alt="img">
                                        </span>
                                        <span class="avatar avatar-rounded">
                                            <img class="border border-white" src="{{ URL::asset('build/img/users/user-11.jpg') }}" alt="img">
                                        </span>
                                        <span class="avatar avatar-rounded">
                                            <img class="border border-white" src="{{ URL::asset('build/img/users/user-22.jpg') }}" alt="img">
                                        </span>
                                        <span class="avatar avatar-rounded">
                                            <img class="border border-white" src="{{ URL::asset('build/img/users/user-58.jpg') }}" alt="img">
                                        </span>
                                        <a class="avatar bg-primary avatar-rounded text-fixed-white fs-10 fw-medium" href="javascript:void(0);">
                                            +3
                                        </a>
                                    </div>
                                    <a href="#" class="btn btn-primary btn-xs">Join Meeting</a>
                                </div>
                            </div>
                            <div class="bg-light p-3 br-5 mb-0">
                                <span class="badge badge-dark badge-xs mb-1">IOS Developer</span>
                                <h6 class="mb-2 text-truncate">Interview Candidates - IOS Developer</h6>
                                <div class="d-flex align-items-center flex-wrap">
                                    <p class="fs-13 mb-1 me-2"><i class="ti ti-calendar-event me-2"></i>Thu, 15 Feb 2025</p>
                                    <p class="fs-13 mb-1"><i class="ti ti-clock-hour-11 me-2"></i>02:00 PM - 04:20 PM</p>
                                </div>
                                <div class="d-flex align-items-center justify-content-between border-top mt-2 pt-3">
                                    <div class="avatar-list-stacked avatar-group-sm">
                                        <span class="avatar avatar-rounded">
                                            <img class="border border-white" src="{{ URL::asset('build/img/users/user-49.jpg') }}" alt="img">
                                        </span>
                                        <span class="avatar avatar-rounded">
                                            <img class="border border-white" src="{{ URL::asset('build/img/users/user-13.jpg') }}" alt="img">
                                        </span>
                                        <span class="avatar avatar-rounded">
                                            <img class="border border-white" src="{{ URL::asset('build/img/users/user-11.jpg') }}" alt="img">
                                        </span>
                                        <span class="avatar avatar-rounded">
                                            <img class="border border-white" src="{{ URL::asset('build/img/users/user-22.jpg') }}" alt="img">
                                        </span>
                                        <span class="avatar avatar-rounded">
                                            <img class="border border-white" src="{{ URL::asset('build/img/users/user-58.jpg') }}" alt="img">
                                        </span>
                                        <a class="avatar bg-primary avatar-rounded text-fixed-white fs-10 fw-medium" href="javascript:void(0);">
                                            +3
                                        </a>
                                    </div>
                                    <a href="#" class="btn btn-primary btn-xs">Join Meeting</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /Schedules -->
                @endif

                <!-- Recent Activities -->
                <div class="col-xxl-6 col-xl-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                            <h5 class="mb-2">Recent Activities</h5>
                            <a href="javascript:void(0);" class="btn btn-light btn-md mb-2">View All</a>
                        </div>
                        <div class="card-body" data-legacy-activities-body>
                        </div>
                    </div>
                </div>
                <!-- /Recent Activities -->

                <!-- Birthdays -->
                <div class="col-xxl-6 col-xl-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                            <h5 class="mb-2">Birthdays</h5>
                            <a href="javascript:void(0);" class="btn btn-light btn-md mb-2">View All</a>
                        </div>
                        <div class="card-body pb-1" data-legacy-birthdays-body>
                            <h6 class="mb-2">Today</h6>
                            <div class="bg-light p-2 border border-dashed rounded-top mb-3" data-legacy-birthdays-today>
                            </div>
                            <h6 class="mb-2">Tomorow</h6>
                            <div class="bg-light p-2 border border-dashed rounded-top mb-3" data-legacy-birthdays-tomorrow>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /Birthdays -->

            </div>
            @endif
        </div>
    </div>
    <!-- /Page Wrapper -->

    @component('components.modal-popup')
    @endcomponent

@endsection

<script src="{{ URL::asset('build/js/core/api-client.js') }}"></script>
<script src="{{ URL::asset('build/js/core/auth-guard.js') }}"></script>