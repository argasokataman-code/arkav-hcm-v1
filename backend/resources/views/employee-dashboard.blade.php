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
                                    <h5 class="text-white mb-1" data-employee-legacy-name>Stephan Peralt</h5>
                                    <div class="d-flex align-items-center">
                                    <p class="text-white fs-12 mb-0" data-employee-legacy-designation>Senior Product Designer</p>
                                    <span class="mx-1"><i class="ti ti-point-filled text-primary"></i></span>
                                    <p class="fs-12" data-employee-legacy-team>UI/UX Design</p>
                                </div>
                                </div>
                            </div>
                            <a href="#" class="btn btn-icon btn-sm text-white rounded-circle edit-top"><i class="ti ti-edit"></i></a>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <span class="d-block mb-1 fs-13">Phone Number</span>
                                <p class="text-gray-9" data-employee-legacy-phone>+1 324 3453 545</p>
                            </div>
                            <div class="mb-3">
                                <span class="d-block mb-1 fs-13">Email Address</span>
                                <p class="text-gray-9" data-employee-legacy-email>Steperde124@example.com</p>
                            </div>
                            <div class="mb-3">
                                <span class="d-block mb-1 fs-13">Report Office</span>
                                <p class="text-gray-9" data-employee-legacy-report-office>-</p>
                            </div>
                            <div>
                                <span class="d-block mb-1 fs-13">Joined on</span>
                                <p class="text-gray-9" data-employee-legacy-join-date>15  Jan 2024</p>
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
                                    Punch In at  10.00 AM
                                </h6>
                                <a href="{{ url('attendance-employee') }}" class="btn btn-primary w-100" data-employee-legacy-punch-button>Punch Out</a>
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
                                            <span class="avatar avatar-xs rounded-circle bg-success flex-shrink-0 me-2">
                                                <i class="ti ti-arrow-up fs-12"></i>
                                            </span>
                                            <span>5% This Week</span>
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
                                            <span class="avatar avatar-xs rounded-circle bg-success flex-shrink-0 me-2">
                                                <i class="ti ti-arrow-up fs-12"></i>
                                            </span>
                                            <span>7% Last Week</span>
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
                                            <span class="avatar avatar-xs rounded-circle bg-danger flex-shrink-0 me-2">
                                                <i class="ti ti-arrow-down fs-12"></i>
                                            </span>
                                            <span>8% Last Month</span>
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
                                            <span class="avatar avatar-xs rounded-circle bg-danger flex-shrink-0 me-2">
                                                <i class="ti ti-arrow-down fs-12"></i>
                                            </span>
                                            <span>6% Last Month</span>
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
                <div class="col-xl-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <div class="d-flex align-items-center justify-content-between flex-wrap row-gap-2">
                                <h5>Projects</h5>
                                <div class="dropdown">
                                    <a href="javascript:void(0);" class="btn btn-white border-0 dropdown-toggle border btn-sm d-inline-flex align-items-center" data-bs-toggle="dropdown">
                                        Ongoing Projects
                                    </a>
                                    <ul class="dropdown-menu  dropdown-menu-end p-3">
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">All Projects</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Ongoing Projects</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-4 shadow-none mb-md-0">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center justify-content-between mb-3">
                                                <h6>Office Management</h6>
                                                <div class="dropdown">
                                                    <a href="javascript:void(0);" class="d-inline-flex align-items-center" data-bs-toggle="dropdown">
                                                        <i class="ti ti-dots-vertical"></i>
                                                    </a>
                                                    <ul class="dropdown-menu dropdown-menu-end p-3">
                                                        <li>
                                                            <a href="javascript:void(0);" class="dropdown-item rounded-1" data-bs-toggle="modal" data-bs-target="#edit_task"><i class="ti ti-edit me-2"></i>Edit</a>
                                                        </li>
                                                        <li>
                                                            <a href="javascript:void(0);" class="dropdown-item rounded-1" data-bs-toggle="modal" data-bs-target="#delete_modal"><i class="ti ti-trash me-2"></i>Delete</a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="d-flex align-items-center mb-3">
                                                    <a href="javascript:void(0);" class="avatar">
                                                        <img src="{{ URL::asset('build/img/users/user-32.jpg') }}" class="img-fluid rounded-circle" alt="img">
                                                    </a>
                                                    <div class="ms-2">
                                                        <h6 class="fw-normal"><a href="javascript:void(0);">Anthony Lewis</a></h6>
                                                        <span class="fs-13 d-block">Project Leader</span>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center mb-3">
                                                    <a href="javascript:void(0);" class="avatar bg-soft-primary rounded-circle">
                                                        <i class="ti ti-calendar text-primary fs-16"></i>
                                                    </a>
                                                    <div class="ms-2">
                                                        <h6 class="fw-normal">14 Jan 2024</h6>
                                                        <span class="fs-13 d-block">Deadline</span>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-between bg-transparent-light border border-dashed rounded p-2 mb-3">
                                                    <div class="d-flex align-items-center">
                                                        <span class="avatar avatar-sm bg-success-transparent rounded-circle me-1"><i class="ti ti-checklist fs-16"></i></span>
                                                        <p>Tasks : <span class="text-gray-9">6 </span> /10</p>
                                                    </div>
                                                    <div class="avatar-list-stacked avatar-group-sm">
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-06.jpg') }}" alt="img">
                                                        </span>
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-07.jpg') }}" alt="img">
                                                        </span>
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-08.jpg') }}" alt="img">
                                                        </span>
                                                        <a class="avatar bg-primary avatar-rounded text-fixed-white fs-12 fw-medium" href="javascript:void(0);">
                                                            +2
                                                        </a>
                                                    </div>
                                                </div>
                                                <div class="bg-soft-secondary p-2 rounded d-flex align-items-center justify-content-between">
                                                    <p class="text-secondary mb-0 text-truncate">Time Spent</p>
                                                    <h5 class="text-secondary text-truncate">65/120 <span class="fs-14 fw-normal">Hrs</span></h5>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card shadow-none mb-0">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center justify-content-between mb-3">
                                                <h6>Office Management</h6>
                                                <div class="dropdown">
                                                    <a href="javascript:void(0);" class="d-inline-flex align-items-center" data-bs-toggle="dropdown">
                                                        <i class="ti ti-dots-vertical"></i>
                                                    </a>
                                                    <ul class="dropdown-menu dropdown-menu-end p-3">
                                                        <li>
                                                            <a href="javascript:void(0);" class="dropdown-item rounded-1" data-bs-toggle="modal" data-bs-target="#edit_task"><i class="ti ti-edit me-2"></i>Edit</a>
                                                        </li>
                                                        <li>
                                                            <a href="javascript:void(0);" class="dropdown-item rounded-1" data-bs-toggle="modal" data-bs-target="#delete_modal"><i class="ti ti-trash me-2"></i>Delete</a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="d-flex align-items-center mb-3">
                                                    <a href="javascript:void(0);" class="avatar">
                                                        <img src="{{ URL::asset('build/img/users/user-33.jpg') }}" class="img-fluid rounded-circle" alt="img">
                                                    </a>
                                                    <div class="ms-2">
                                                        <h6 class="fw-normal"><a href="javascript:void(0);">Anthony Lewis</a></h6>
                                                        <span class="fs-13 d-block">Project Leader</span>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center mb-3">
                                                    <a href="javascript:void(0);" class="avatar bg-soft-primary rounded-circle">
                                                        <i class="ti ti-calendar text-primary fs-16"></i>
                                                    </a>
                                                    <div class="ms-2">
                                                        <h6 class="fw-normal">14 Jan 2024</h6>
                                                        <span class="fs-13 d-block">Deadline</span>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-between bg-transparent-light border border-dashed rounded p-2 mb-3">
                                                    <div class="d-flex align-items-center">
                                                        <span class="avatar avatar-sm bg-success-transparent rounded-circle me-1"><i class="ti ti-checklist fs-16"></i></span>
                                                        <p>Tasks : <span class="text-gray-9">6 </span> /10</p>
                                                    </div>
                                                    <div class="avatar-list-stacked avatar-group-sm">
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-06.jpg') }}" alt="img">
                                                        </span>
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-07.jpg') }}" alt="img">
                                                        </span>
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-08.jpg') }}" alt="img">
                                                        </span>
                                                        <a class="avatar bg-primary avatar-rounded text-fixed-white fs-12 fw-medium" href="javascript:void(0);">
                                                            +2
                                                        </a>
                                                    </div>
                                                </div>
                                                <div class="bg-soft-secondary p-2 rounded d-flex align-items-center justify-content-between">
                                                    <p class="text-secondary mb-0 text-truncate">Time Spent</p>
                                                    <h5 class="text-secondary text-truncate">65/120 <span class="fs-14 fw-normal">Hrs</span></h5>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <div class="d-flex align-items-center justify-content-between flex-wrap row-gap-2">
                                <h5>Tasks</h5>
                                <div class="dropdown">
                                    <a href="javascript:void(0);" class="btn btn-white border-0 dropdown-toggle border btn-sm d-inline-flex align-items-center" data-bs-toggle="dropdown">
                                        All Tasks
                                    </a>
                                    <ul class="dropdown-menu  dropdown-menu-end p-3">
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">All Tasks</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Open Tasks</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item border rounded mb-3 p-2">
                                    <div class="row align-items-center row-gap-3">
                                        <div class="col-md-8">
                                            <div class="todo-inbox-check d-flex align-items-center">
                                                <span><i class="ti ti-grid-dots me-2"></i></span>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox">
                                                </div>
                                                <span class="me-2 d-flex align-items-center rating-select"><i class="ti ti-star-filled filled"></i></span>
                                                <div class="strike-info">
                                                    <h4 class="fs-14 text-truncate">Submit leave request supporting document</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center justify-content-md-end flex-wrap row-gap-3">
                                                <span class="badge bg-soft-pink d-inline-flex align-items-center me-2"><i class="fas fa-circle fs-6 me-1"></i>On Hold</span>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-list-stacked avatar-group-sm">
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-13.jpg') }}" alt="img">
                                                        </span>
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-14.jpg') }}" alt="img">
                                                        </span>
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-15.jpg') }}" alt="img">
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="list-group-item border rounded mb-3 p-2">
                                    <div class="row align-items-center row-gap-3">
                                        <div class="col-md-8">
                                            <div class="todo-inbox-check d-flex align-items-center">
                                                <span><i class="ti ti-grid-dots me-2"></i></span>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox">
                                                </div>
                                                <span class="me-2 rating-select d-flex align-items-center"><i class="ti ti-star"></i></span>
                                                <div class="strike-info">
                                                    <h4 class="fs-14 text-truncate">Review payroll slip for current period</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center justify-content-md-end flex-wrap row-gap-3">
                                                <span class="badge bg-transparent-purple d-flex align-items-center me-2"><i class="fas fa-circle fs-6 me-1"></i>In Progress</span>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-list-stacked avatar-group-sm">
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-20.jpg') }}" alt="img">
                                                        </span>
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-21.jpg') }}" alt="img">
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="list-group-item border rounded mb-3 p-2">
                                    <div class="row align-items-center row-gap-3">
                                        <div class="col-md-8">
                                            <div class="todo-inbox-check d-flex align-items-center">
                                                <span><i class="ti ti-grid-dots me-2"></i></span>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox">
                                                </div>
                                                <span class="me-2 rating-select d-flex align-items-center"><i class="ti ti-star"></i></span>
                                                <div class="strike-info">
                                                    <h4 class="fs-14 text-truncate">Complete attendance correction form</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center justify-content-md-end flex-wrap row-gap-3">
                                                <span class="badge badge-soft-success align-items-center me-2"><i class="fas fa-circle fs-6 me-1"></i>Completed</span>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-list-stacked avatar-group-sm">
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-28.jpg') }}" alt="img">
                                                        </span>
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-29.jpg') }}" alt="img">
                                                        </span>
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-24.jpg') }}" alt="img">
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="list-group-item border rounded mb-3 p-2">
                                    <div class="row align-items-center row-gap-3">
                                        <div class="col-md-8">
                                            <div class="todo-inbox-check d-flex align-items-center todo-strike-content">
                                                <span><i class="ti ti-grid-dots me-2"></i></span>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" checked="">
                                                </div>
                                                <span class="me-2 rating-select d-flex align-items-center"><i class="ti ti-star"></i></span>
                                                <div class="strike-info">
                                                    <h4 class="fs-14 text-truncate">Update emergency contact information</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center justify-content-md-end flex-wrap row-gap-3">
                                                <span class="badge badge-secondary-transparent d-flex align-items-center me-2"><i class="fas fa-circle fs-6 me-1"></i>Pending</span>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-list-stacked avatar-group-sm">
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-23.jpg') }}" alt="img">
                                                        </span>
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-24.jpg') }}" alt="img">
                                                        </span>
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-25.jpg') }}" alt="img">
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="list-group-item border rounded p-2">
                                    <div class="row align-items-center row-gap-3">
                                        <div class="col-md-8">
                                            <div class="todo-inbox-check d-flex align-items-center">
                                                <span><i class="ti ti-grid-dots me-2"></i></span>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox">
                                                </div>
                                                <span class="me-2 rating-select d-flex align-items-center"><i class="ti ti-star"></i></span>
                                                <div class="strike-info">
                                                    <h4 class="fs-14 text-truncate">Submit monthly overtime claim draft</h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center justify-content-md-end flex-wrap row-gap-3">
                                                <span class="badge bg-transparent-purple d-flex align-items-center me-2"><i class="fas fa-circle fs-6 me-1"></i>In Progress</span>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-list-stacked avatar-group-sm">
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-28.jpg') }}" alt="img">
                                                        </span>
                                                        <span class="avatar avatar-rounded">
                                                            <img class="border border-white" src="{{ URL::asset('build/img/profiles/avatar-29.jpg') }}" alt="img">
                                                        </span>
                                                    </div>
                                                </div>
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
                                        <h6 class="text-white fw-medium mb-1" data-team-birthday-name>Andrew Jermia</h6>
                                        <p data-team-birthday-role>IOS Developer</p>
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
                                    <p class="text-gray-9" data-next-holiday-label>Diwali, 15 Sep 2025</p>
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
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <div class="d-flex align-items-center">
                                    <a href="javascript:void(0);" class="avatar flex-shrink-0">
                                        <img src="{{ URL::asset('build/img/users/user-27.jpg') }}" class="rounded-circle border border-2" alt="img">
                                    </a>
                                    <div class="ms-2">
                                        <h6 class="fs-14 fw-medium text-truncate mb-1"><a href="#">Alexander Jermai</a></h6>
                                        <p class="fs-13">UI/UX Designer</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <a href="#" class="btn btn-light btn-icon btn-sm me-2"><i class="ti ti-phone fs-16"></i></a>
                                    <a href="#" class="btn btn-light btn-icon btn-sm me-2"><i class="ti ti-mail-bolt fs-16"></i></a>
                                    <a href="#" class="btn btn-light btn-icon btn-sm"><i class="ti ti-brand-hipchat fs-16"></i></a>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <div class="d-flex align-items-center">
                                    <a href="javascript:void(0);" class="avatar flex-shrink-0">
                                        <img src="{{ URL::asset('build/img/users/user-42.jpg') }}" class="rounded-circle border border-2" alt="img">
                                    </a>
                                    <div class="ms-2">
                                        <h6 class="fs-14 fw-medium text-truncate mb-1"><a href="#">Doglas Martini</a></h6>
                                        <p class="fs-13">Product Designer</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <a href="#" class="btn btn-light btn-icon btn-sm me-2"><i class="ti ti-phone fs-16"></i></a>
                                    <a href="#" class="btn btn-light btn-icon btn-sm me-2"><i class="ti ti-mail-bolt fs-16"></i></a>
                                    <a href="#" class="btn btn-light btn-icon btn-sm"><i class="ti ti-brand-hipchat fs-16"></i></a>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <div class="d-flex align-items-center">
                                    <a href="javascript:void(0);" class="avatar flex-shrink-0">
                                        <img src="{{ URL::asset('build/img/users/user-43.jpg') }}" class="rounded-circle border border-2" alt="img">
                                    </a>
                                    <div class="ms-2">
                                        <h6 class="fs-14 fw-medium text-truncate mb-1"><a href="#">Daniel Esbella</a></h6>
                                        <p class="fs-13">Project Manager</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <a href="#" class="btn btn-light btn-icon btn-sm me-2"><i class="ti ti-phone fs-16"></i></a>
                                    <a href="#" class="btn btn-light btn-icon btn-sm me-2"><i class="ti ti-mail-bolt fs-16"></i></a>
                                    <a href="#" class="btn btn-light btn-icon btn-sm"><i class="ti ti-brand-hipchat fs-16"></i></a>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <div class="d-flex align-items-center">
                                    <a href="javascript:void(0);" class="avatar flex-shrink-0">
                                        <img src="{{ URL::asset('build/img/users/user-11.jpg') }}" class="rounded-circle border border-2" alt="img">
                                    </a>
                                    <div class="ms-2">
                                        <h6 class="fs-14 fw-medium text-truncate mb-1"><a href="#">Daniel Esbella</a></h6>
                                        <p class="fs-13">Team Lead</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <a href="#" class="btn btn-light btn-icon btn-sm me-2"><i class="ti ti-phone fs-16"></i></a>
                                    <a href="#" class="btn btn-light btn-icon btn-sm me-2"><i class="ti ti-mail-bolt fs-16"></i></a>
                                    <a href="#" class="btn btn-light btn-icon btn-sm"><i class="ti ti-brand-hipchat fs-16"></i></a>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <div class="d-flex align-items-center">
                                    <a href="javascript:void(0);" class="avatar flex-shrink-0">
                                        <img src="{{ URL::asset('build/img/users/user-44.jpg') }}" class="rounded-circle border border-2" alt="img">
                                    </a>
                                    <div class="ms-2">
                                        <h6 class="fs-14 fw-medium text-truncate mb-1"><a href="#">Stephan Peralt</a></h6>
                                        <p class="fs-13">Team Lead</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <a href="#" class="btn btn-light btn-icon btn-sm me-2"><i class="ti ti-phone fs-16"></i></a>
                                    <a href="#" class="btn btn-light btn-icon btn-sm me-2"><i class="ti ti-mail-bolt fs-16"></i></a>
                                    <a href="#" class="btn btn-light btn-icon btn-sm"><i class="ti ti-brand-hipchat fs-16"></i></a>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <a href="javascript:void(0);" class="avatar flex-shrink-0">
                                        <img src="{{ URL::asset('build/img/users/user-54.jpg') }}" class="rounded-circle border border-2" alt="img">
                                    </a>
                                    <div class="ms-2">
                                        <h6 class="fs-14 fw-medium text-truncate mb-1"><a href="#">Andrew Jermia</a></h6>
                                        <p class="fs-13">Project  Lead</p>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <a href="#" class="btn btn-light btn-icon btn-sm me-2"><i class="ti ti-phone fs-16"></i></a>
                                    <a href="#" class="btn btn-light btn-icon btn-sm me-2"><i class="ti ti-mail-bolt fs-16"></i></a>
                                    <a href="#" class="btn btn-light btn-icon btn-sm"><i class="ti ti-brand-hipchat fs-16"></i></a>
                                </div>
                            </div>
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
                            <div class="d-flex align-items-start mb-4">
                                <a href="javascript:void(0);" class="avatar flex-shrink-0">
                                    <img src="{{ URL::asset('build/img/users/user-27.jpg') }}" class="rounded-circle border border-2" alt="img">
                                </a>
                                <div class="ms-2">
                                    <h6 class="fs-14 fw-medium text-truncate mb-1">Leave request approved by manager</h6>
                                    <p class="fs-13 mb-2">Today at 9:42 AM</p>
                                    <div class="d-flex align-items-center">
                                        <a href="#" class="avatar avatar-sm border flex-shrink-0 me-2"><img src="{{ URL::asset('build/img/social/pdf-icon.svg') }}" class="w-auto h-auto" alt="Img"></a>
                                        <h6 class="fw-normal"><a href="#">leave-approval-note.pdf</a></h6>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-start mb-4">
                                <a href="javascript:void(0);" class="avatar flex-shrink-0">
                                    <img src="{{ URL::asset('build/img/users/user-28.jpg') }}" class="rounded-circle border border-2" alt="img">
                                </a>
                                <div class="ms-2">
                                    <h6 class="fs-14 fw-medium text-truncate mb-1">Payroll slip for current month is available</h6>
                                    <p class="fs-13 mb-0">Today at 10:00 AM</p>
                                </div>
                            </div>
                            <div class="d-flex align-items-start mb-4">
                                <a href="javascript:void(0);" class="avatar flex-shrink-0">
                                    <img src="{{ URL::asset('build/img/users/user-29.jpg') }}" class="rounded-circle border border-2" alt="img">
                                </a>
                                <div class="ms-2">
                                    <h6 class="fs-14 fw-medium text-truncate mb-1">Attendance correction request needs confirmation</h6>
                                    <p class="fs-13 mb-2">Today at 10:50 AM</p>
                                    <div class="d-flex align-items-center">
                                        <a href="#" class="btn btn-primary btn-sm me-2">Approve</a>
                                        <a href="#" class="btn btn-outline-primary btn-sm">Decline</a>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-start mb-4">
                                <a href="javascript:void(0);" class="avatar flex-shrink-0">
                                    <img src="{{ URL::asset('build/img/users/user-30.jpg') }}" class="rounded-circle border border-2" alt="img">
                                </a>
                                <div class="ms-2">
                                    <h6 class="fs-14 fw-medium text-truncate mb-1">Upcoming holiday reminder has been posted</h6>
                                    <p class="fs-13 mb-0">Today at 12:00 PM</p>
                                </div>
                            </div>
                            <div class="d-flex align-items-start">
                                <a href="javascript:void(0);" class="avatar flex-shrink-0">
                                    <img src="{{ URL::asset('build/img/users/user-33.jpg') }}" class="rounded-circle border border-2" alt="img">
                                </a>
                                <div class="ms-2">
                                    <h6 class="fs-14 fw-medium text-truncate mb-1">Performance cycle self-review opens this week</h6>
                                    <p class="fs-13 mb-0">Today at 05:00 PM</p>
                                </div>
                            </div>
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
                            <div class="d-flex align-items-start">
                                <div class="d-flex align-items-center active-time">
                                    <span>09:25 AM</span>
                                    <span><i class="ti ti-point-filled text-primary fs-20"></i></span>
                                </div>
                                <div class="flex-fill ps-3 pb-4 timeline-flow">
                                    <div class="bg-light p-2 rounded">
                                        <p class="fw-medium text-gray-9 mb-1">Daily attendance and shift sync</p>
                                        <span>Operations</span>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-start">
                                <div class="d-flex align-items-center active-time">
                                    <span>09:20 AM</span>
                                    <span><i class="ti ti-point-filled text-secondary fs-20"></i></span>
                                </div>
                                <div class="flex-fill ps-3 pb-4 timeline-flow">
                                    <div class="bg-light p-2 rounded">
                                        <p class="fw-medium text-gray-9 mb-1">Leave and overtime approval standup</p>
                                        <span>Review</span>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-start">
                                <div class="d-flex align-items-center active-time">
                                    <span>09:18 AM</span>
                                    <span><i class="ti ti-point-filled text-warning fs-20"></i></span>
                                </div>
                                <div class="flex-fill ps-3 pb-4 timeline-flow">
                                    <div class="bg-light p-2 rounded">
                                        <p class="fw-medium text-gray-9 mb-1">Monthly payroll preparation briefing</p>
                                        <span>Payroll</span>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-start">
                                <div class="d-flex align-items-center active-time">
                                    <span>09:10 AM</span>
                                    <span><i class="ti ti-point-filled text-success fs-20"></i></span>
                                </div>
                                <div class="flex-fill ps-3 timeline-flow">
                                    <div class="bg-light p-2 rounded">
                                        <p class="fw-medium text-gray-9 mb-1">Performance and learning check-in</p>
                                        <span>People Development</span>
                                    </div>
                                </div>
                            </div>
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

@endsection