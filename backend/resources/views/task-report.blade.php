<?php $page = 'task-report'; ?>
@extends('layout.mainlayout')
@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content">

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Task Report</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            HR
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Task Report</li>
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

            <!-- Total Exponses -->
            <div class="col-lg-6 col-md-6 d-flex">
                <div class="row flex-fill">
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body ">
                                <div class="row align-items-center">
                                    <div class="col-8">
                                        <div>
                                            <span class="fs-14 fw-normal text-truncate mb-1">Completed Tasks</span>
                                            <h5>800</h5>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <p class="data-attributes">
                                            <span data-peity='{ "fill": ["#F26522", "rgba(67, 87, 133, .09)"], "innerRadius": 16, "radius": 32 }'>6/7</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body ">
                                <div class="row align-items-center">
                                    <div class="col-8">
                                        <div>
                                            <span class="fs-14 fw-normal text-truncate mb-1">In Progress Tasks</span>
                                            <h5>800</h5>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <p class="data-attributes">
                                            <span data-peity='{ "fill": ["#03C95A", "rgba(67, 87, 133, .09)"], "innerRadius": 16, "radius": 32 }'>4/7</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-8">
                                        <div>
                                            <span class="fs-14 fw-normal text-truncate mb-1">Pending Tasks</span>
                                            <h5>800</h5>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <p class="data-attributes">
                                            <span data-peity='{ "fill": ["#FD3995", "rgba(67, 87, 133, .09)"], "innerRadius": 16, "radius": 32 }'>2/7</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body ">
                                <div class="row align-items-center">
                                    <div class="col-8">
                                        <div>
                                            <span class="fs-14 fw-normal text-truncate mb-1">Total Tasks</span>
                                            <h5>800</h5>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <p class="data-attributes">
                                            <span data-peity='{ "fill": ["#0DCAF0", "rgba(67, 87, 133, .09)"], "innerRadius": 16, "radius": 32 }'>1/7</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Total Exponses -->

            <!-- Total Exponses -->
            <div class="col-lg-6 col-md-6 d-flex">
                <div class="card flex-fill">
                    <div class="card-header border-0">
                        <div class="d-flex flex-wrap justify-content-between align-items-center">
                            <div class="d-flex align-items-center ">
                                <span class="me-2"><i class="ti ti-chart-pie text-danger"></i></span>
                                <h5>Tasks</h5>
                            </div>
                            <div class="dropdown">
                                <a href="javascript:void(0);" class="dropdown-toggle btn btn-sm fs-12 btn-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
                                    Office Management App
                                </a>
                                <ul class="dropdown-menu  dropdown-menu-end p-2">
                                    <li>
                                        <a href="javascript:void(0);" class="dropdown-item rounded-1">PRO-001</a>
                                    </li>
                                    <li>
                                        <a href="javascript:void(0);" class="dropdown-item rounded-1">PRO-002</a>
                                    </li>
                                    <li>
                                        <a href="javascript:void(0);" class="dropdown-item rounded-1">PRO-004</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row align-items-center">
                            <div class="col-md-6 d-flex align-items-center justify-content-center">
                                <div class="position-relative payment-total">
                                    <div id="task-reports"></div>
                                    <div class="task-total-content ">
                                        <p class="fs-16 fw-normal mb-0">Pending</p>
                                        <span class="display-3 fs-24 fw-bold text-skyblue">30%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="row gy-4">
                                    <div class="col-md-6">
                                        <div class="d-flex task-report-icons">
                                            <span class="me-2"><i class="ti ti-arrow-badge-right-filled text-success"></i></span>
                                            <h6 class="fs-16">Completed <span class="fs-14 fw-normal">40%</span></h6>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex task-report-icons">
                                            <span class="me-2"><i class="ti ti-arrow-badge-right-filled text-skyblue"></i></span>
                                            <h6 class="fs-16">Pending <span class="fs-14 fw-normal">30 %</span></h6>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex task-report-icons">
                                            <span class="me-2"><i class="ti ti-arrow-badge-right-filled text-warning"></i></span>
                                            <h6 class="fs-16">Inprogress  <span class="fs-14 fw-normal">20 %</span></h6>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex task-report-icons">
                                            <span class="me-2"><i class="ti ti-arrow-badge-right-filled text-purple"></i></span>
                                            <h6 class="fs-16">On Hold <span class="fs-14 fw-normal">10 %</span></h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Total Exponses -->
        </div>
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                <h5>Tasks List</h5>
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
                            Select Priority
                        </a>
                        <ul class="dropdown-menu  dropdown-menu-end p-3">
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Low</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Medium</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">High</a>
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
                    <table class="table datatable" data-api-report-table="1">
                        <thead class="thead-light">
                            <tr>
                                <th class="no-sort">
                                    <div class="form-check form-check-md">
                                        <input class="form-check-input" type="checkbox" id="select-all">
                                    </div>
                                </th>
                                <th>Task Name</th>
                                <th>Project Name</th>
                                <th>Created Date</th>
                                <th>Due Date</th>
                                <th>Priority</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

  

</div>
<!-- /Page Wrapper -->

@endsection