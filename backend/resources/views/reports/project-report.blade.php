<?php $page = 'project-report'; ?>
@extends('layout.mainlayout')
@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content">

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Project Report</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            HR
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Project Report</li>
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
                                <div>
                                        <div class="mb-2">
                                            <span class="fs-14 fw-normal text-truncate mb-1">Total Projects</span>
                                            <h5>300</h5>
                                        </div>
                                        <div class="progress" role="progressbar" aria-label="Basic example" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 100%;height: 5px;">
                                            <div class="progress-bar bg-pink" style="width: 70%"></div>
                                        </div>
                                </div>
                                <div class="d-flex mt-2">
                                    <p class="fs-12 fw-normal d-flex align-items-center text-truncate"><span class="text-success fs-12 d-flex align-items-center me-1"><i class="ti ti-arrow-wave-right-up me-1"></i>+10.54%</span>from last month</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body ">
                                <div>
                                        <div class="mb-2">
                                            <span class="fs-14 fw-normal text-truncate mb-1">Completed Projects</span>
                                            <h5>250</h5>
                                        </div>
                                        <div class="progress" role="progressbar" aria-label="Basic example" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 100%;height: 5px;">
                                            <div class="progress-bar bg-success" style="width: 80%"></div>
                                        </div>
                                </div>
                                <div class="d-flex mt-2">
                                    <p class="fs-12 fw-normal d-flex align-items-center text-truncate"><span class="text-success fs-12 d-flex align-items-center me-1"><i class="ti ti-arrow-wave-right-up me-1"></i>+12.84%</span>from last month</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body ">
                                <div>
                                        <div class="mb-2">
                                            <span class="fs-14 fw-normal text-truncate mb-1">Pending Projects</span>
                                            <h5>50</h5>
                                        </div>
                                        <div class="progress" role="progressbar" aria-label="Basic example" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 100%;height: 5px;">
                                            <div class="progress-bar bg-danger" style="width: 20%"></div>
                                        </div>
                                </div>
                                <div class="d-flex mt-2">
                                    <p class="fs-12 fw-normal d-flex align-items-center text-truncate"><span class="text-danger fs-12 d-flex align-items-center me-1"><i class="ti ti-arrow-wave-right-up me-1"></i>-10.75%</span>from last month</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body ">
                                <div>
                                        <div class="mb-2">
                                            <span class="fs-14 fw-normal text-truncate mb-1">New Projects</span>
                                            <h5>30</h5>
                                        </div>
                                        <div class="progress" role="progressbar" aria-label="Basic example" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 100%;height: 5px;">
                                            <div class="progress-bar bg-purple" style="width: 60%"></div>
                                        </div>
                                </div>
                                <div class="d-flex mt-2">
                                    <p class="fs-12 fw-normal d-flex align-items-center text-truncate"><span class="text-success fs-12 d-flex align-items-center me-1"><i class="ti ti-arrow-wave-right-up me-1"></i>+15.74%</span>from last month</p>
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
                                <h5>Projects By Tasks</h5>
                            </div>
                            <div class="dropdown">
                                <a href="javascript:void(0);"
                                    class="dropdown-toggle btn btn-sm fs-12 btn-white d-inline-flex align-items-center"
                                    data-bs-toggle="dropdown">
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
                            <div class="col-md-6 d-flex justify-content-center">
                                <div id="project-report">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="row gy-4">
                                    <div class="col-md-6">
                                        <p class="fs-16 project-report-badge-blue fw-normal mb-0 text-gray-5">Pending </p>
                                        <p class="fs-20 fw-bold text-dark ">30%</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="fs-16 project-report-badge-purple mb-0  fw-normal text-gray-5">On Hold</p>
                                        <p class="fs-20 fw-bold text-dark ">10%</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="fs-16 project-report-badge-warning  mb-0 fw-normal text-gray-5">Inprogress </p>
                                        <p class="fs-20 fw-bold text-dark ">20%</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="fs-16 project-report-badge-success  mb-0 fw-normal text-gray-5">Completed</p>
                                        <p class="fs-20 fw-bold text-dark ">40%</p>
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
                <h5>Project List</h5>
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
                    <div class="dropdown me-3">
                        <a href="javascript:void(0);"
                            class="dropdown-toggle btn btn-white d-inline-flex align-items-center"
                            data-bs-toggle="dropdown">
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
                        <a href="javascript:void(0);"
                            class="dropdown-toggle btn btn-white d-inline-flex align-items-center"
                            data-bs-toggle="dropdown">
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
                        <a href="javascript:void(0);"
                            class="dropdown-toggle btn btn-white d-inline-flex align-items-center"
                            data-bs-toggle="dropdown">
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
                                <th>Project ID</th>
                                <th>Project Name</th>
                                <th>Leader</th>
                                <th>Team</th>
                                <th>Deadline</th>
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