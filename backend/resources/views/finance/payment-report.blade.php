<?php $page = 'payment-report'; ?>
@extends('layout.mainlayout')
@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content">

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Payment Report</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            HR
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Payment Report</li>
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
            <div class="col-xl-6 d-flex">
                <div class="row flex-fill">
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body ">
                                <div
                                    class="d-flex flex-wrap align-items-center justify-content-between border-bottom pb-2">
                                    <div class="d-flex align-items-center flex-column overflow-hidden">
                                        <div>
                                            <div>
                                                <span class="fs-14 fw-normal text-truncate mb-1">Total
                                                    Payments</span>
                                                <h5>-</h5>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                                        <a href="#"
                                            class="avatar avatar-md br-5 payment-report-icon  bg-transparent-primary border border-primary">
                                            <span class="text-primary"><i
                                                    class="ti ti-currency-dollar"></i></span>
                                        </a>

                                    </div>
                                </div>
                                <div class="d-flex justify-content-center mt-2">
                                    <p class="fs-12 fw-normal d-flex align-items-center text-truncate text-muted">Awaiting latest data</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body ">
                                <div
                                    class="d-flex flex-wrap align-items-center justify-content-between border-bottom pb-2">
                                    <div class="d-flex align-items-center flex-column overflow-hidden">
                                        <div>
                                            <div>
                                                <span class="fs-14 fw-normal text-truncate mb-1">Pending
                                                    Payments</span>
                                                <h5>-</h5>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                                        <a href="#"
                                            class="avatar avatar-md br-5 payment-report-icon  bg-transparent-skyblue border border-skyblue">
                                            <span class="text-skyblue"><i
                                                    class="ti ti-currency-dollar"></i></span>
                                        </a>

                                    </div>
                                </div>
                                <div class="d-flex justify-content-center mt-2">
                                    <p class="fs-12 fw-normal d-flex align-items-center text-truncate text-muted">Awaiting latest data</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body ">
                                <div
                                    class="d-flex flex-wrap align-items-center justify-content-between border-bottom pb-2">
                                    <div class="d-flex align-items-center flex-column overflow-hidden">
                                        <div>
                                            <div>
                                                <span class="fs-14 fw-normal text-truncate mb-1">Failed
                                                    Payments</span>
                                                <h5>-</h5>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                                        <a href="#"
                                            class="avatar avatar-md br-5 payment-report-icon  bg-transparent-danger border border-danger">
                                            <span class="text-danger"><i
                                                    class="ti ti-currency-dollar"></i></span>
                                        </a>

                                    </div>
                                </div>
                                <div class="d-flex justify-content-center mt-2">
                                    <p class="fs-12 fw-normal d-flex align-items-center text-truncate text-muted">Awaiting latest data</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-body ">
                                <div
                                    class="d-flex flex-wrap align-items-center justify-content-between border-bottom pb-2">
                                    <div class="d-flex align-items-center flex-column overflow-hidden">
                                        <div>
                                            <div>
                                                <span class="fs-14 fw-normal text-truncate mb-1">Payment Success
                                                    Rate</span>
                                                <h5>-</h5>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                                        <a href="#"
                                            class="avatar avatar-md br-5 payment-report-icon  bg-pink-transparent border border-pink">
                                            <span class="text-pink"><i class="ti ti-currency-dollar"></i></span>
                                        </a>

                                    </div>
                                </div>
                                <div class="d-flex justify-content-center mt-2">
                                    <p class="fs-12 fw-normal d-flex align-items-center text-truncate text-muted">Awaiting latest data</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <!-- /Total Exponses -->

            <!-- Total Exponses -->
            <div class="col-xl-6 d-flex">
                <div class="card flex-fill">
                    <div class="card-header border-0">
                        <div class="d-flex flex-wrap row-gap-2 justify-content-between align-items-center">
                            <div class="d-flex align-items-center ">
                                <span class="me-2"><i class="ti ti-chart-donut text-danger"></i></span>
                                <h5>Payments By Payment Methods </h5>
                            </div>
                            <div class="dropdown">
                                <a href="javascript:void(0);"
                                    class="dropdown-toggle btn btn-sm fs-12 btn-white d-inline-flex align-items-center"
                                    data-bs-toggle="dropdown">
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
                    <div class="card-body d-flex align-items-center justify-content-between pt-0">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="position-relative payment-total">
                                    <div id="payment-report" ></div>
                                <div class="payment-total-content ">
                                    <span class="display-3 fs-24 fw-bold text-skyblue">-</span>
                                    <p class="fs-16 fw-normal text-muted">Live summary</p>
                                </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="row gy-4">
                                    <div class="col-md-6">
                                        <h6 class="fs-16 text-gray-5 fw-normal side-badge mb-1">Paypal</h6>
                                        <h5 class="fs-20 fw-bold">-</h5>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fs-16 text-gray-5 fw-normal side-badge-pink mb-1"> Debit Card</h6>
                                        <h5 class="fs-20 fw-bold">-</h5>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fs-16 text-gray-5 fw-normal side-badge-purple mb-1"> Bank Transfer</h6>
                                        <h5 class="fs-20 fw-bold">-</h5>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fs-16 text-gray-5 fw-normal side-badge-warning mb-1"> Credit Card</h6>
                                        <h5 class="fs-20 fw-bold">-</h5>
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
                <h5>Payment List</h5>
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
                        <a href="javascript:void(0);"
                            class="dropdown-toggle btn btn-white d-inline-flex align-items-center"
                            data-bs-toggle="dropdown">
                            Payment Type
                        </a>
                        <ul class="dropdown-menu  dropdown-menu-end p-3">
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Paypal</a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">Cash</a>
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
                                <th>Invoice ID</th>
                                <th>Client Name</th>
                                <th>Company Name</th>
                                <th>Payment Type</th>
                                <th>Paid Date</th>
                                <th>Paid Amount</th>
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

