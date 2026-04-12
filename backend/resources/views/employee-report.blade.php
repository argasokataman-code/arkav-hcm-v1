<?php $page = 'employee-report'; ?>
@extends('layout.mainlayout')
@section('content')
<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content">

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Employee Report</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            HR
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Employee Report</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                <div class="mb-2">
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

                    <!-- Total Companies -->
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="overflow-hidden d-flex mb-2 align-items-center">
                                    <span class="me-2"><img src="{{ URL::asset('build/img/reports-img/employee-report-icon.svg') }}" alt="Img" class="img-fluid"></span>
                                   <div>
                                        <p class="fs-14 fw-normal mb-1 text-truncate">Total Employee</p>
                                        <h5 data-employee-report-total>—</h5>
                                   </div>
                                </div>
                                <div>
                                    <p class="fs-12 text-muted mb-0">From employee directory API</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /Total Companies -->

                    <!-- Total Companies -->
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="overflow-hidden d-flex mb-2 align-items-center">
                                    <span class="me-2"><img src="{{ URL::asset('build/img/reports-img/employee-report-success.svg') }}" alt="Img" class="img-fluid"></span>
                                   <div>
                                        <p class="fs-14 fw-normal mb-1 text-truncate">Active Employee</p>
                                        <h5 data-employee-report-active>—</h5>
                                   </div>
                                </div>
                                <div>
                                    <p class="fs-12 text-muted mb-0">Employment status: active</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /Total Companies -->

                    <!-- Inactive Companies -->
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="overflow-hidden d-flex mb-2 align-items-center">
                                    <span class="me-2"><img src="{{ URL::asset('build/img/reports-img/employee-report-info.svg') }}" alt="Img" class="img-fluid"></span>
                                   <div>
                                        <p class="fs-14 fw-normal mb-1 text-truncate">New (30 days)</p>
                                        <h5 data-employee-report-new>—</h5>
                                   </div>
                                </div>
                                <div>
                                    <p class="fs-12 text-muted mb-0">Accounts created in last 30 days</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /Inactive Companies -->

                    <!-- Company Location -->
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="overflow-hidden d-flex mb-2 align-items-center">
                                    <span class="me-2"><img src="{{ URL::asset('build/img/reports-img/employee-report-danger.svg') }}" alt="Img" class="img-fluid"></span>
                                   <div>
                                        <p class="fs-14 fw-normal mb-1 text-truncate">Inactive Employee</p>
                                            <h5 data-employee-report-inactive>—</h5>
                                   </div>
                                </div>
                                <div>
                                    <p class="fs-12 text-muted mb-0">Employment status: inactive</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /Company Location -->
                </div>
            </div>
            <div class="col-xl-6 d-flex">
               <div class="card flex-fill">
                    <div class="card-header border-0 pb-0">
                        <div class="d-flex flex-wrap justify-content-between align-items-center row-gap-2">
                            <div class="d-flex align-items-center ">
                                <span class="me-2"><i class="ti ti-chart-bar text-danger"></i></span>
                                <h5>Employee </h5>
                            </div>
                            <div class="d-flex align-items-center">
                                <p class="d-inline-flex align-items-center me-3 mb-0">
                                    <i class="ti ti-square-filled fs-12 text-success me-2"></i>
                                    Active Employees
                                </p>
                                <p class="d-inline-flex align-items-center">
                                    <i class="ti ti-square-filled fs-12 text-gray-1 me-2 mb-0"></i>
                                    Inactive Employees
                                </p>
                            </div>
                            <div class="dropdown">
                                <a href="javascript:void(0);" class="dropdown-toggle btn btn-sm fs-12 btn-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
                                    This Year
                                </a>
                                <ul class="dropdown-menu  dropdown-menu-end p-2">
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
                    <div class="card-body py-0">
                        <div id="employee-reports" class="py-4 text-center text-muted small">Chart menyusul — ringkasan di kartu kiri memakai data API.</div>
                    </div>
                </div>
            </div>					
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                <h5>Employees List</h5>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                    <div class="me-3">
                        <div class="input-icon-end position-relative">
                            <input type="text" class="form-control date-range bookingrange" placeholder="dd/mm/yyyy - dd/mm/yyyy">
                            <span class="input-icon-addon">
                                <i class="ti ti-chevron-down"></i>
                            </span>
                        </div>
                    </div>
                    <div class="dropdown me-3">
                        <a href="javascript:void(0);" class="dropdown-toggle btn btn-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
                            Designation
                        </a>
                        <ul class="dropdown-menu  dropdown-menu-end p-3">
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Present</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Absent</a>
                            </li>
                        </ul>
                    </div>
                    <div class="dropdown me-3">
                        <a href="javascript:void(0);" class="dropdown-toggle btn btn-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
                            Select Status
                        </a>
                        <ul class="dropdown-menu  dropdown-menu-end p-3">
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Active</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Inactive</a>
                            </li>
                        </ul>
                    </div>
                    <div class="dropdown">
                        <a href="javascript:void(0);" class="dropdown-toggle btn btn-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
                            Sort By : Last 7 Days
                        </a>
                        <ul class="dropdown-menu  dropdown-menu-end p-3">
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Recently Added</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Ascending</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Desending</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Last Month</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Last 7 Days</a>
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
                                <th>Emp ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Phone</th>
                                <th>Joining Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody data-employee-report-body>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Loading…</td>
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