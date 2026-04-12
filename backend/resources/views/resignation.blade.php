<?php $page = 'resignation'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Resignation</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Performance
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Resignation</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                    <div class="mb-2">
                        <a href="#" class="btn btn-primary d-flex align-items-center" data-arcav-resignation-add><i class="ti ti-circle-plus me-2"></i>Add Resignation</a>
                    </div>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <!-- Resignation List -->
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                            <h5 class="d-flex align-items-center">Resignation List</h5>
                            <div class="d-flex align-items-center flex-wrap row-gap-3">
                                <div class="input-icon position-relative me-2">
                                    <span class="input-icon-addon">
                                        <i class="ti ti-calendar"></i>
                                    </span>
                                    <input type="text" class="form-control date-range bookingrange" placeholder="dd/mm/yyyy - dd/mm/yyyy ">
                                </div>
                                <div class="dropdown">
                                    <a href="javascript:void(0);" class="dropdown-toggle btn btn-white d-inline-flex align-items-center fs-12" data-bs-toggle="dropdown">
                                        <p class="fs-12 d-inline-flex me-1">Sort By : </p>
                                        Last 7 Days
                                    </a>
                                    <ul class="dropdown-menu  dropdown-menu-end p-3">
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Last 7 Days</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Created Date</a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Due Date</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">

                            <div class="custom-datatable-filter table-responsive">
                                {{-- Tanpa class "datatable": hindari init global DataTables (tbody vs thead mismatch). --}}
                                <table class="table" data-arcav-resignations-table>
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Resigning Employee</th>
                                            <th>Department</th>
                                            <th>Reason</th>
                                            <th>Notice Date</th>
                                            <th>Resignation Date</th>
                                            <th>Status</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody data-arcav-resignations-tbody>
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
            <!-- /Resignation List  -->
        </div>



    </div>
    <!-- /Page Wrapper -->

    @component('components.modal-popup')
    @endcomponent

    @include('hcm.partials.resignation-modals')
    @include('hcm.partials.resignation-detail-modal')

@endsection
