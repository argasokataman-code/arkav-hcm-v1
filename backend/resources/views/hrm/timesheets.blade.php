<?php $page = 'timesheets'; ?>
@extends('layout.mainlayout')
@section('content')

    <style>
        [data-timesheets-body]:not([data-hydrated="1"]) {
            display: none;
        }
    </style>

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Timesheets</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Employee
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Timesheets</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                    <div class="me-2 mb-2">
                        <a href="javascript:void(0);" class="btn btn-white d-inline-flex align-items-center" data-timesheets-export="csv">
                            <i class="ti ti-file-export me-1"></i>Export CSV
                        </a>
                    </div>
                    <div class="mb-2">
                        <a href="{{ url('attendance-admin') }}" class="btn btn-primary d-flex align-items-center">
                            <i class="ti ti-calendar-event me-2"></i>Open Attendance Admin
                        </a>
                    </div>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <!-- Performance Indicator list -->
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <h5>Timesheet</h5>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                        <div class="me-3">
                            <input type="date" class="form-control" data-timesheets-date-from title="Date from">
                        </div>
                        <div class="me-3">
                            <input type="date" class="form-control" data-timesheets-date-to title="Date to">
                        </div>
                        <div class="me-3">
                            <select class="form-select" data-timesheets-filter-project title="Project filter">
                                <option value="">All projects</option>
                            </select>
                        </div>
                        <div>
                            <select class="form-select" data-timesheets-sort title="Sort rows">
                                <option value="date_desc">Sort: Latest date</option>
                                <option value="date_asc">Sort: Oldest date</option>
                                <option value="employee_asc">Sort: Employee A-Z</option>
                                <option value="employee_desc">Sort: Employee Z-A</option>
                                <option value="worked_desc">Sort: Worked high-low</option>
                                <option value="worked_asc">Sort: Worked low-high</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="custom-datatable-filter table-responsive">
                        <table class="table">
                            <thead class="thead-light">
                                <tr>
                                    <th>Employee</th>
                                    <th>Date </th>
                                    <th>Project</th>
                                    <th>Assigned Hours</th>
                                    <th>Worked Hours</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-timesheets-body>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Loading timesheets...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2" data-timesheets-pagination style="display: none;">
                    <span class="text-muted small" data-timesheets-page-info></span>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-light border" data-timesheets-prev>Sebelumnya</button>
                        <button type="button" class="btn btn-sm btn-light border" data-timesheets-next>Berikutnya</button>
                    </div>
                </div>
            </div>
            <!-- /Performance Indicator list -->

        </div>

  

    </div>
    <!-- /Page Wrapper -->

    @component('components.modal-popup')
    @endcomponent

@endsection