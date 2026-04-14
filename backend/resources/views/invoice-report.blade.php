<?php $page = 'invoice-report'; ?>
@extends('layout.mainlayout')
@section('content')
<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content">

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Invoice Report</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            HR
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Invoice Report</li>
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
            <div class="col-xl-7 d-flex">
                <div class="row flex-fill">
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card invoice-report  flex-fill">
                            <span class="invoice-report-badge">
                            </span>
                            <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
                                <div class="d-flex align-items-center flex-column overflow-hidden">
                                    <div>
                                        <div>
                                            <span class="fs-14 fw-normal text-truncate mb-1">Total Invoice</span>
                                            <h5>600</h5>
                                        </div>		
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <span class="badge badge-sm badge-success me-3">+19.01%</span>
                                    <a href="#" class="avatar avatar-md br-10  bg-transparent-primary border border-primary">
                                        <span class="text-primary"><i class="ti ti-file-invoice"></i></span>
                                    </a>
                                    
                                </div>
                                
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card invoice-report  flex-fill">
                            <span class="invoice-report-badge-warning">
                            </span>
                            <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
                                <div class="d-flex align-items-center flex-column overflow-hidden">
                                    <div>
                                        <div>
                                            <span class="fs-14 fw-normal text-truncate mb-1">Partially Paid</span>
                                            <h5>80</h5>
                                        </div>		
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <span class="badge badge-sm badge-success me-3">+19.01%</span>
                                    <a href="#" class="avatar avatar-md br-10  bg-transparent-primary border border-primary">
                                        <span class="text-primary"><i class="ti ti-file-invoice"></i></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card invoice-report  flex-fill">
                            <span class="invoice-report-badge-success">
                            </span>
                            <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
                                <div class="d-flex align-items-center flex-column overflow-hidden">
                                    <div>
                                        <div>
                                            <span class="fs-14 fw-normal text-truncate mb-1">Paid Invoices</span>
                                            <h5>450</h5>
                                        </div>		
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <span class="badge badge-sm badge-success me-3">+19.01%</span>
                                    <a href="#" class="avatar avatar-md br-10  bg-transparent-primary border border-primary">
                                        <span class="text-primary"><i class="ti ti-file-invoice"></i></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card invoice-report  flex-fill">
                            <span class="invoice-report-badge-purple">
                            </span>
                            <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
                                <div class="d-flex align-items-center flex-column overflow-hidden">
                                    <div>
                                        <div>
                                            <span class="fs-14 fw-normal text-truncate mb-1">Overdue Invoices</span>
                                            <h5>40</h5>
                                        </div>		
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <span class="badge badge-sm badge-success me-3">+19.01%</span>
                                    <a href="#" class="avatar avatar-md br-10  bg-transparent-primary border border-primary">
                                        <span class="text-primary"><i class="ti ti-file-invoice"></i></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card invoice-report  flex-fill">
                            <span class="invoice-report-badge-danger">
                            </span>
                            <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
                                <div class="d-flex align-items-center flex-column overflow-hidden">
                                    <div>
                                        <div>
                                            <span class="fs-14 fw-normal text-truncate mb-1">Unpaid Invoices</span>
                                            <h5>150</h5>
                                        </div>		
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <span class="badge badge-sm badge-success me-3">+19.01%</span>
                                    <a href="#" class="avatar avatar-md br-10  bg-transparent-primary border border-primary">
                                        <span class="text-primary"><i class="ti ti-file-invoice"></i></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card invoice-report  flex-fill">
                            <span class="invoice-report-badge-skyblue">
                            </span>
                            <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
                                <div class="d-flex align-items-center flex-column overflow-hidden">
                                    <div>
                                        <div>
                                            <span class="fs-14 fw-normal text-truncate mb-1">Revenue</span>
                                            <h5>$25,340</h5>
                                        </div>		
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <span class="badge badge-sm badge-success me-3">+19.01%</span>
                                    <a href="#" class="avatar avatar-md br-10  bg-transparent-primary border border-primary">
                                        <span class="text-primary"><i class="ti ti-file-invoice"></i></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
            <!-- /Total Exponses -->

            <!-- Total Exponses -->
            <div class="col-xl-5 d-flex">
                <div class="card flex-fill">
                    <div class="card-header border-0 pb-0">
                        <div class="d-flex flex-wrap justify-content-between align-items-center">
                            <div class="d-flex align-items-center ">
                                <span class="me-2"><i class="ti ti-chart-area-line text-danger"></i></span>
                                <h5>Expense </h5>
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
                        <div id="invoice-report"></div>
                    </div>
                </div>
            </div>
            <!-- /Total Exponses -->
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                <h5>Invoice List</h5>
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
                            $0.00 - $00
                        </a>
                        <ul class="dropdown-menu  dropdown-menu-end p-3">
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">$10 - $20</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">$20 - $30</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">$30 - $40</a>
                            </li>
                        </ul>
                    </div>
                    <div class="dropdown me-3">
                        <a href="javascript:void(0);" class="dropdown-toggle btn btn-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
                            Select Status
                        </a>
                        <ul class="dropdown-menu  dropdown-menu-end p-3">
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Paid</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Sent</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Partially Paid</a>
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
                                <th>Invoice ID</th>
                                <th>Client Name</th>
                                <th>Company Name</th>
                                <th>Created Date</th>
                                <th>Due Date</th>
                                <th>Amount</th>
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