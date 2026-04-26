<?php $page = 'employee-dashboard'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Employee Dashboard</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Dashboard
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Employee Dashboard</li>
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
                                        <a href="javascript:void(0);" class="dropdown-item rounded-1" data-employee-export="pdf"><i
                                            class="ti ti-file-type-pdf me-1"></i>Export as PDF</a>
                                </li>
                                <li>
                                        <a href="javascript:void(0);" class="dropdown-item rounded-1" data-employee-export="excel"><i
                                            class="ti ti-file-type-xls me-1"></i>Export as Excel </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="input-icon w-120 position-relative mb-2">
                        <span class="input-icon-addon">
                            <i class="ti ti-calendar text-gray-9"></i>
                        </span>
                        <input type="text" class="form-control datetimepicker" data-employee-dashboard-date value="{{ now()->format('d-m-Y') }}">
                    </div>
                    <div class="ms-2 head-icons">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            @if (session('error'))
                <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @php($showModernEmployeeDashboard = false)
            @if($showModernEmployeeDashboard)
                @include('hcm.partials.employee-home-dashboard')
            @endif

            @php($showLegacyEmployeeDashboard = true)
            @if($showLegacyEmployeeDashboard)

            <div class="alert bg-secondary-transparent alert-dismissible fade show mb-4" data-employee-legacy-leave-alert>
                <span data-employee-legacy-leave-alert-text>Memuat status pengajuan cuti terbaru...</span>
                <button type="button" class="btn-close fs-14" data-bs-dismiss="alert" aria-label="Close"><i class="ti ti-x"></i></button>
            </div>
            <div class="row">
                <div class="col-xl-4 d-flex">
                    <div class="card position-relative flex-fill">
                        <div class="card-header bg-dark">
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-lg avatar-rounded border border-white border-2 flex-shrink-0 me-2">
                                    <img src="{{ URL::asset('build/img/users/user-01.jpg') }}" alt="Img">
                                </span>
                                <div>
                                    <h5 class="text-white mb-1" data-employee-legacy-name>User</h5>
                                    <div class="d-flex align-items-center">
                                    <p class="text-white fs-12 mb-0" data-employee-legacy-designation>Employee</p>
                                    <span class="mx-1"><i class="ti ti-point-filled text-primary"></i></span>
                                    <p class="fs-12" data-employee-legacy-team>General</p>
                                </div>
                                </div>
                            </div>
                            <a href="#" class="btn btn-icon btn-sm text-white rounded-circle edit-top"><i class="ti ti-edit"></i></a>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <span class="d-block mb-1 fs-13">Phone Number</span>
                                <p class="text-gray-9" data-employee-legacy-phone>-</p>
                            </div>
                            <div class="mb-3">
                                <span class="d-block mb-1 fs-13">Email Address</span>
                                <p class="text-gray-9" data-employee-legacy-email>-</p>
                            </div>
                            <div class="mb-3">
                                <span class="d-block mb-1 fs-13">Report Office</span>
                                <p class="text-gray-9" data-employee-legacy-report-office>-</p>
                            </div>
                            <div>
                                <span class="d-block mb-1 fs-13">Joined on</span>
                                <p class="text-gray-9" data-employee-legacy-join-date>-</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-5 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <div class="d-flex align-items-center justify-content-between flex-wrap row-gap-2">
                                <h5>Leave & Overtime Summary</h5>
                                <div class="dropdown">
                                    <a href="javascript:void(0);" class="btn btn-white border btn-sm d-inline-flex align-items-center" data-bs-toggle="dropdown" data-employee-dashboard-year>
                                        <i class="ti ti-calendar me-1"></i>{{ now()->year }}
                                    </a>
                                    <ul class="dropdown-menu  dropdown-menu-end p-3">
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">2024</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">2023</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">2022</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="mb-3">
                                            <p class="d-flex align-items-center"><i class="ti ti-circle-filled fs-8 text-dark me-1"></i>
                                                <span class="text-gray-9 fw-semibold me-1" data-employee-legacy-leave-approved>0</span>
                                                Approved Leave
                                            </p>
                                        </div>
                                        <div class="mb-3">
                                            <p class="d-flex align-items-center"><i class="ti ti-circle-filled fs-8 text-success me-1"></i>
                                                <span class="text-gray-9 fw-semibold me-1" data-employee-legacy-leave-pending>0</span>
                                                Pending Leave
                                            </p>
                                        </div>
                                        <div class="mb-3">
                                            <p class="d-flex align-items-center"><i class="ti ti-circle-filled fs-8 text-primary me-1"></i>
                                                <span class="text-gray-9 fw-semibold me-1" data-employee-legacy-ot-approved>0</span>
                                                Approved Overtime Request
                                            </p>
                                        </div>
                                        <div class="mb-3">
                                            <p class="d-flex align-items-center"><i class="ti ti-circle-filled fs-8 text-danger me-1"></i>
                                                <span class="text-gray-9 fw-semibold me-1" data-employee-legacy-leave-declined>0</span>
                                                Declined Leave
                                            </p>
                                        </div>
                                        <div>
                                            <p class="d-flex align-items-center"><i class="ti ti-circle-filled fs-8 text-warning me-1"></i>
                                                <span class="text-gray-9 fw-semibold me-1" data-employee-legacy-ot-hours>0</span>
                                                Approved Overtime (Hours)
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3 d-flex justify-content-md-end">
                                        <div id="leaves_chart"></div>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="todo1">
                                        <label class="form-check-label" for="todo1" data-employee-legacy-leave-insight>Data based on your current leave and overtime history</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <div class="d-flex align-items-center justify-content-between flex-wrap row-gap-2">
                                <h5>Leave Metrics</h5>
                                <div class="dropdown">
                                    <a href="javascript:void(0);" class="btn btn-white border btn-sm d-inline-flex align-items-center" data-bs-toggle="dropdown" data-employee-dashboard-year>
                                        <i class="ti ti-calendar me-1"></i>{{ now()->year }}
                                    </a>
                                    <ul class="dropdown-menu  dropdown-menu-end p-3">
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">2024</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">2023</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">2022</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-sm-6">
                                    <div class="mb-4">
                                        <span class="d-block mb-1">Total Requests</span>
                                        <h4 data-employee-legacy-leave-total>0</h4>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="mb-4">
                                        <span class="d-block mb-1">Approved</span>
                                        <h4 data-employee-legacy-leave-taken>0</h4>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="mb-4">
                                        <span class="d-block mb-1">Declined</span>
                                        <h4 data-employee-legacy-leave-absent>0</h4>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="mb-4">
                                        <span class="d-block mb-1">Pending</span>
                                        <h4 data-employee-legacy-leave-request>0</h4>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="mb-4">
                                        <span class="d-block mb-1">Worked Days</span>
                                        <h4 data-employee-legacy-worked-days>0</h4>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="mb-4">
                                        <span class="d-block mb-1">Leave Impact</span>
                                        <h4 data-employee-legacy-loss-of-pay>0</h4>
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div>
                                        <a href="{{ url('leaves-employee') }}" class="btn btn-dark w-100">Apply New Leave</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill border-primary attendance-bg">
                        <div class="card-body">
                            <div class="mb-4 text-center">
                                <h6 class="fw-medium text-gray-5 mb-1">Attendance</h6>
                                <h4 data-employee-legacy-now-label>08:35 AM, 11 Mar 2025</h4>
                            </div>
                            <div class="attendance-circle-progress attendance-progress mx-auto mb-3"  data-value='65' data-employee-legacy-attendance-progress>
                                <span class="progress-left">
                                    <span class="progress-bar border-success"></span>
                                </span>
                                <span class="progress-right">
                                    <span class="progress-bar border-success"></span>
                                </span>
                                <div class="total-work-hours text-center w-100">
                                    <span class="fs-13 d-block mb-1">Total Hours</span>
                                    <h6 data-employee-legacy-total-hours>5:45:32</h6>
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="badge badge-dark badge-md mb-3" data-employee-legacy-production-badge>Production :  3.45 hrs</div>
                                    <h6 class="fw-medium d-flex align-items-center justify-content-center mb-4" data-employee-legacy-punch-line>
                                    <i class="ti ti-fingerprint text-primary me-1"></i>
                                    Punch In at -
                                </h6>
                                <div class="rounded border mb-3" data-employee-legacy-attendance-map style="height: 180px;"></div>
                                <p class="small text-muted mb-3" data-employee-legacy-map-hint>Map attendance belum tersedia.</p>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-success" data-attendance-me-selfie-btn data-arcav-selfie-allowed="0" title="Memuat status absensi…">
                                        <i class="ti ti-camera me-1"></i> Ambil Selfie
                                    </button>
                                    <a href="{{ url('attendance-employee') }}" class="btn btn-primary" data-employee-legacy-punch-button>Punch Out</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-8 d-flex">
                    <div class="row flex-fill">
                        <div class="col-xl-3 col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="border-bottom mb-3 pb-2">
                                        <span class="avatar avatar-sm bg-primary mb-2"><i class="ti ti-clock-stop"></i></span>
                                        <h2 class="mb-2"><span data-employee-legacy-stat-today-hours>8.36</span> / <span class="fs-20 text-gray-5" data-employee-legacy-stat-today-target>9</span></h2>
                                        <p class="fw-medium text-truncate">Total Hours Today</p>
                                    </div>
                                    <div>
                                        <p class="d-flex align-items-center fs-13">
                                            <span class="avatar avatar-xs rounded-circle bg-secondary flex-shrink-0 me-2">
                                                <i class="ti ti-info-circle fs-12"></i>
                                            </span>
                                            <span>Data minggu ini belum tersedia</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="border-bottom mb-3 pb-2">
                                        <span class="avatar avatar-sm bg-dark mb-2"><i class="ti ti-clock-up"></i></span>
                                        <h2 class="mb-2"><span data-employee-legacy-stat-week-hours>10</span> / <span class="fs-20 text-gray-5" data-employee-legacy-stat-week-target>40</span></h2>
                                        <p class="fw-medium text-truncate">Total Hours Week</p>
                                    </div>
                                    <div>
                                        <p class="d-flex align-items-center fs-13">
                                            <span class="avatar avatar-xs rounded-circle bg-secondary flex-shrink-0 me-2">
                                                <i class="ti ti-info-circle fs-12"></i>
                                            </span>
                                            <span>Data pekan lalu belum tersedia</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="border-bottom mb-3 pb-2">
                                        <span class="avatar avatar-sm bg-info mb-2"><i class="ti ti-calendar-up"></i></span>
                                        <h2 class="mb-2"><span data-employee-legacy-stat-month-hours>75</span> / <span class="fs-20 text-gray-5" data-employee-legacy-stat-month-target>98</span></h2>
                                        <p class="fw-medium text-truncate">Total Hours Month</p>
                                    </div>
                                    <div>
                                        <p class="d-flex align-items-center fs-13 text-truncate">
                                            <span class="avatar avatar-xs rounded-circle bg-secondary flex-shrink-0 me-2">
                                                <i class="ti ti-info-circle fs-12"></i>
                                            </span>
                                            <span>Data bulan lalu belum tersedia</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="border-bottom mb-3 pb-2">
                                        <span class="avatar avatar-sm bg-pink mb-2"><i class="ti ti-calendar-star"></i></span>
                                        <h2 class="mb-2"><span data-employee-legacy-stat-ot-hours>16</span> / <span class="fs-20 text-gray-5" data-employee-legacy-stat-ot-target>28</span></h2>
                                        <p class="fw-medium text-truncate">Overtime this Month</p>
                                    </div>
                                    <div>
                                        <p class="d-flex align-items-center fs-13 text-truncate">
                                            <span class="avatar avatar-xs rounded-circle bg-secondary flex-shrink-0 me-2">
                                                <i class="ti ti-info-circle fs-12"></i>
                                            </span>
                                            <span>Data bulan lalu belum tersedia</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-xl-3">
                                            <div class="mb-4">
                                                <p class="d-flex align-items-center mb-1"><i class="ti ti-point-filled text-dark-transparent me-1"></i>Total Working hours</p>
                                                <h3 data-employee-legacy-summary-total-working>12h 36m</h3>
                                            </div>
                                        </div>
                                        <div class="col-xl-3">
                                            <div class="mb-4">
                                                <p class="d-flex align-items-center mb-1"><i class="ti ti-point-filled text-success me-1"></i>Productive Hours</p>
                                                <h3 data-employee-legacy-summary-productive>08h 36m</h3>
                                            </div>
                                        </div>
                                        <div class="col-xl-3">
                                            <div class="mb-4">
                                                <p class="d-flex align-items-center mb-1"><i class="ti ti-point-filled text-warning me-1"></i>Break hours</p>
                                                <h3 data-employee-legacy-summary-break>22m 15s</h3>
                                            </div>
                                        </div>
                                        <div class="col-xl-3">
                                            <div class="mb-4">
                                                <p class="d-flex align-items-center mb-1"><i class="ti ti-point-filled text-info me-1"></i>Overtime</p>
                                                <h3 data-employee-legacy-summary-overtime>02h 15m</h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="progress bg-transparent-dark mb-3" style="height: 24px;">
                                                <div class="progress-bar bg-white rounded" role="progressbar" style="width: 18%;"></div>
                                                <div class="progress-bar bg-success rounded me-2" role="progressbar" style="width: 18%;"></div>
                                                <div class="progress-bar bg-warning rounded me-2" role="progressbar" style="width: 5%;"></div>
                                                <div class="progress-bar bg-success rounded me-2" role="progressbar" style="width: 28%;"></div>
                                                <div class="progress-bar bg-warning rounded me-2" role="progressbar" style="width: 17%;"></div>
                                                <div class="progress-bar bg-success rounded me-2" role="progressbar" style="width: 22%;"></div>
                                                <div class="progress-bar bg-warning rounded me-2" role="progressbar" style="width: 5%;"></div>
                                                <div class="progress-bar bg-info rounded me-2" role="progressbar" style="width: 3%;"></div>
                                                <div class="progress-bar bg-info rounded" role="progressbar" style="width: 2%;"></div>
                                                <div class="progress-bar bg-white rounded" role="progressbar" style="width: 18%;"></div>
                                            </div>
                                            
                                        </div>
                                        <div class="co-md-12">
                                            <div class="d-flex align-items-center justify-content-between flex-wrap row-gap-2">
                                                <span class="fs-10">06:00</span>
                                                <span class="fs-10">07:00</span>
                                                <span class="fs-10">08:00</span>
                                                <span class="fs-10">09:00</span>
                                                <span class="fs-10">10:00</span>
                                                <span class="fs-10">11:00</span>
                                                <span class="fs-10">12:00</span>
                                                <span class="fs-10">01:00</span>
                                                <span class="fs-10">02:00</span>
                                                <span class="fs-10">03:00</span>
                                                <span class="fs-10">04:00</span>
                                                <span class="fs-10">05:00</span>
                                                <span class="fs-10">06:00</span>
                                                <span class="fs-10">07:00</span>
                                                <span class="fs-10">08:00</span>
                                                <span class="fs-10">09:00</span>
                                                <span class="fs-10">10:00</span>
                                                <span class="fs-10">11:00</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-xl-5 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <div class="d-flex align-items-center justify-content-between flex-wrap row-gap-2">
                                <h5>Performance</h5>
                                <div class="dropdown">
                                    <a href="javascript:void(0);" class="btn btn-white border btn-sm d-inline-flex align-items-center" data-bs-toggle="dropdown" data-employee-dashboard-year>
                                        <i class="ti ti-calendar me-1"></i>{{ now()->year }}
                                    </a>
                                    <ul class="dropdown-menu  dropdown-menu-end p-3">
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">2024</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">2023</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">2022</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div>
                                <div class="bg-light d-flex align-items-center rounded p-2">
                                    <h3 class="me-2" data-performance-current-percent>98%</h3>
                                    <span class="badge badge-outline-success bg-success-transparent rounded-pill me-1" data-performance-vs-last>12%</span>
                                    <span>vs previous review</span>
                                </div>
                                <div id="performance_chart2"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <div class="d-flex align-items-center justify-content-between flex-wrap row-gap-2">
                                <h5>My Skills</h5>
                                <div class="dropdown">
                                    <a href="javascript:void(0);" class="btn btn-white border btn-sm d-inline-flex align-items-center" data-bs-toggle="dropdown" data-employee-dashboard-year>
                                        <i class="ti ti-calendar me-1"></i>{{ now()->year }}
                                    </a>
                                    <ul class="dropdown-menu  dropdown-menu-end p-3">
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">2024</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">2023</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">2022</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div data-my-skills-body>
                                <div class="border border-dashed bg-transparent-light rounded p-2 mb-2">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <span class="d-block border border-2 h-12 border-primary rounded-5 me-2"></span>
                                            <div>
                                                <h6 class="fw-medium mb-1">Figma</h6>
                                                <p>Updated : 15 May 2025</p>
                                            </div>
                                        </div>
                                        <div class="circle-progress circle-progress-md"  data-value='95'>
                                            <span class="progress-left">
                                                <span class="progress-bar border-primary"></span>
                                            </span>
                                            <span class="progress-right">
                                                <span class="progress-bar border-primary"></span>
                                            </span>
                                            <div class="progress-value">95%</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="border border-dashed bg-transparent-light rounded p-2 mb-2">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <span class="d-block border border-2 h-12 border-success rounded-5 me-2"></span>
                                            <div>
                                                <h6 class="fw-medium mb-1">HTML</h6>
                                                <p>Updated : 12 May 2025</p>
                                            </div>
                                        </div>
                                        <div class="circle-progress circle-progress-md"  data-value='85'>
                                            <span class="progress-left">
                                                <span class="progress-bar border-success"></span>
                                            </span>
                                            <span class="progress-right">
                                                <span class="progress-bar border-success"></span>
                                            </span>
                                            <div class="progress-value">85%</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="border border-dashed bg-transparent-light rounded p-2 mb-2">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <span class="d-block border border-2 h-12 border-purple rounded-5 me-2"></span>
                                            <div>
                                                <h6 class="fw-medium mb-1">CSS</h6>
                                                <p>Updated : 12 May 2025</p>
                                            </div>
                                        </div>
                                        <div class="circle-progress circle-progress-md"  data-value='70'>
                                            <span class="progress-left">
                                                <span class="progress-bar border-purple"></span>
                                            </span>
                                            <span class="progress-right">
                                                <span class="progress-bar border-purple"></span>
                                            </span>
                                            <div class="progress-value">70%</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="border border-dashed bg-transparent-light rounded p-2 mb-2">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <span class="d-block border border-2 h-12 border-info rounded-5 me-2"></span>
                                            <div>
                                                <h6 class="fw-medium mb-1">Wordpress</h6>
                                                <p>Updated : 15 May 2025</p>
                                            </div>
                                        </div>
                                        <div class="circle-progress circle-progress-md"  data-value='61'>
                                            <span class="progress-left">
                                                <span class="progress-bar border-info"></span>
                                            </span>
                                            <span class="progress-right">
                                                <span class="progress-bar border-info"></span>
                                            </span>
                                            <div class="progress-value">61%</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="border border-dashed bg-transparent-light rounded p-2">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center">
                                            <span class="d-block border border-2 h-12 border-dark rounded-5 me-2"></span>
                                            <div>
                                                <h6 class="fw-medium mb-1">Javascript</h6>
                                                <p>Updated : 13 May 2025</p>
                                            </div>
                                        </div>
                                        <div class="circle-progress circle-progress-md"  data-value='58'>
                                            <span class="progress-left">
                                                <span class="progress-bar border-dark"></span>
                                            </span>
                                            <span class="progress-right">
                                                <span class="progress-bar border-dark"></span>
                                            </span>
                                            <div class="progress-value">58%</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 d-flex">
                    <div class="flex-fill">
                        <div class="card card-bg-5 bg-dark mb-3" data-team-birthday-card>
                            <div class="card-body">
                                <div class="text-center">
                                    <h5 class="text-white mb-4">Team Birthday</h5>
                                    <span class="avatar avatar-xl avatar-rounded mb-2">
                                        <img src="{{ URL::asset('build/img/users/user-35.jpg') }}" alt="Img" data-team-birthday-photo>
                                    </span>
                                    <div class="mb-3">
                                        <h6 class="text-white fw-medium mb-1" data-team-birthday-name>-</h6>
                                        <p data-team-birthday-role>-</p>
                                    </div>
                                    <a href="javascript:void(0);" class="btn btn-sm btn-primary" data-team-birthday-wish>Send Wishes</a>
                                </div>
                            </div>
                        </div>
                        <div class="card bg-secondary mb-3">
                            <div class="card-body d-flex align-items-center justify-content-between p-3">
                                <div>
                                    <h5 class="text-white mb-1">Leave Policy</h5>
                                    <p class="text-white" data-leave-policy-last-updated>Last Updated : Today</p>
                                </div>
                                <a href="#" class="btn btn-white btn-sm px-3">View All</a>
                            </div>
                        </div>
                        <div class="card bg-warning">
                            <div class="card-body d-flex align-items-center justify-content-between p-3">
                                <div>
                                    <h5 class="mb-1">Next Holiday</h5>
                                    <p class="text-gray-9" data-next-holiday-label>-</p>
                                </div>
                                <a href="{{url('holidays')}}" class="btn btn-white btn-sm px-3">View All</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                <h5>Team Members</h5>
                                <div>
                                    <a href="#" class="btn btn-light btn-sm">View All</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body" data-team-members-body>
                            <p class="text-muted mb-0">Memuat anggota tim...</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                <h5>Notifications</h5>
                                <div>
                                    <a href="#" class="btn btn-light btn-sm">View All</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-0">Belum ada notifikasi terbaru.</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <div class="d-flex align-items-center justify-content-between flex-wrap row-gap-2">
                                <h5>Meetings Schedule</h5>
                                <div class="dropdown">
                                    <a href="javascript:void(0);" class="btn btn-white border btn-sm d-inline-flex align-items-center" data-bs-toggle="dropdown">
                                        <i class="ti ti-calendar me-1"></i>Today
                                    </a>
                                    <ul class="dropdown-menu  dropdown-menu-end p-3">
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Today</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">This Month</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">This Year</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="card-body schedule-timeline">
                            <p class="text-muted mb-0">Belum ada jadwal rapat untuk hari ini.</p>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>

    </div>
    <!-- /Page Wrapper -->

    @component('components.modal-popup')
    @endcomponent

    <style>
        /* Selfie camera modal styles */
        .arcav-selfie-camera-modal .modal-header {
            border-bottom: 1px solid #e8e9f0;
        }

        .arcav-selfie-camera-video {
            width: 100%;
            height: auto;
            border-radius: 8px;
            background-color: #000;
        }

        .arcav-selfie-preview {
            width: 100%;
            height: auto;
            border-radius: 8px;
            max-width: 400px;
            display: block;
            margin: 0 auto;
        }

        .arcav-selfie-preview.show {
            display: block;
        }

        .arcav-selfie-control-group {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            margin-bottom: 1rem;
        }

        .arcav-selfie-encrypt-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #6c757d;
            padding: 0.5rem 0;
            border-top: 1px solid #e8e9f0;
            margin-top: 1rem;
            padding-top: 1rem;
        }

        .arcav-selfie-encrypt-badge i {
            font-size: 1rem;
        }

        [data-selfie-camera-video]:not([data-recording="1"]) {
            display: block;
        }

        [data-selfie-preview]:not([data-show="1"]) {
            display: none;
        }
    </style>

    <!-- Selfie: punch in required (app modal) -->
    <div class="modal fade" id="arcav_attendance_selfie_prereq_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title d-flex align-items-center gap-2">
                        <i class="ti ti-alert-circle text-warning"></i>
                        Punch masuk diperlukan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-2">
                    <p class="mb-3 text-gray-700" data-arcav-selfie-prereq-message>
                        Harap lakukan punch in terlebih dahulu sebelum mengambil selfie. Setelah absensi hari ini tercatat, Anda dapat membuka kamera selfie dari tombol yang sama.
                    </p>
                    <p class="small text-muted mb-0">Jika Anda sudah punch masuk namun pesan ini masih muncul, muat ulang halaman lalu coba lagi.</p>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Mengerti</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Selfie Camera Modal -->
    <div class="modal fade arcav-selfie-camera-modal" id="arcav_attendance_selfie_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="ti ti-camera me-2"></i>Ambil Selfie Absensi
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small" role="alert">
                        <i class="ti ti-info-circle me-1"></i>
                        Pastikan wajah Anda terlihat jelas dalam frame kamera.
                    </div>
                    
                    <!-- Camera stream -->
                    <video data-selfie-camera-video class="arcav-selfie-camera-video" playsinline></video>
                    
                    <!-- Preview after capture -->
                    <canvas data-selfie-preview class="arcav-selfie-preview" width="400" height="300"></canvas>
                    
                    <!-- Controls -->
                    <div class="arcav-selfie-control-group">
                        <button type="button" class="btn btn-primary flex-grow-1" data-selfie-capture-btn>
                            <i class="ti ti-circle me-1"></i>Ambil Foto
                        </button>
                        <button type="button" class="btn btn-outline-secondary flex-grow-1 d-none" data-selfie-retake-btn>
                            <i class="ti ti-refresh me-1"></i>Ulangi
                        </button>
                    </div>
                    
                    <!-- Encryption indicator -->
                    <div class="arcav-selfie-encrypt-badge">
                        <i class="ti ti-lock"></i>
                        <span>Foto akan dienkripsi sebelum dikirim</span>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success d-none" data-selfie-submit-btn>
                        <i class="ti ti-check me-1"></i>Simpan Selfie
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection