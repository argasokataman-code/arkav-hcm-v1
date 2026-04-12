@php
    $arcavOvertimeEmployeeOnly = $arcavOvertimeEmployeeOnly ?? false;
@endphp
<?php $page = $arcavOvertimeEmployeeOnly ? 'overtime-employee' : 'overtime'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Overtime</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Employee
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">
                                @if ($arcavOvertimeEmployeeOnly)
                                    Overtime (Employee)
                                @else
                                    Overtime (Admin)
                                @endif
                            </li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                    <div class="me-2 mb-2">
                        <span class="text-muted small d-inline-flex align-items-center"><i class="ti ti-file-export me-1"></i>Export menyusul.</span>
                    </div>
                    <div class="mb-2">
                        <a href="javascript:void(0);" class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#arcav_add_overtime"><i class="ti ti-circle-plus me-2"></i>{{ $arcavOvertimeEmployeeOnly ? 'Request Overtime' : 'Add Overtime' }}</a>
                    </div>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <!-- Overtime Counts -->
                <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center flex-wrap justify-content-between">
                                <div>
                                    <p class="fs-12 fw-medium mb-0 text-gray-5">{{ $arcavOvertimeEmployeeOnly ? 'You (employee)' : 'Overtime Employee' }}</p>
                                    <h4 data-hcm-ot-stat="distinctUsers">—</h4>
                                </div>
                                <div>
                                    <span class="p-2 br-10 bg-transparent-primary border border-primary d-flex align-items-center justify-content-center"><i class="ti ti-user-check text-primary fs-18"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center flex-wrap justify-content-between">
                                <div>
                                    <p class="fs-12 fw-medium mb-0 text-gray-5">Overtime Hours</p>
                                    <h4 data-hcm-ot-stat="approvedHours">—</h4>
                                </div>
                                <div>
                                    <span class="p-2 br-10 bg-pink-transparent border border-pink d-flex align-items-center justify-content-center"><i class="ti ti-user-edit text-pink fs-18"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center flex-wrap justify-content-between">
                                <div>
                                    <p class="fs-12 fw-medium mb-0 text-gray-5">Pending Request</p>
                                    <h4 data-hcm-ot-stat="pending">—</h4>
                                </div>
                                <div>
                                    <span class="p-2 br-10 bg-transparent-purple border border-purple d-flex align-items-center justify-content-center"><i class="ti ti-user-exclamation text-purple fs-18"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center flex-wrap justify-content-between">
                                <div>
                                    <p class="fs-12 fw-medium mb-0 text-gray-5">Rejected</p>
                                    <h4 data-hcm-ot-stat="declined">—</h4>
                                </div>
                                <div>
                                    <span class="p-2 br-10 bg-skyblue-transparent border border-skyblue d-flex align-items-center justify-content-center"><i class="ti ti-user-exclamation text-skyblue fs-18"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
                <!-- /Overtime Counts -->

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-2">
                    <h5>Kalkulator Overtime (PP 35/2021)</h5>
                    <span class="text-muted small">Upah sejam = (Gaji Pokok + Tunjangan Tetap) / 173</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @unless ($arcavOvertimeEmployeeOnly)
                        <div class="col-lg-2">
                            <label class="form-label">Karyawan (opsional)</label>
                            <select class="form-select" data-hcm-ot-calc="employeeId">
                                <option value="">Manual input</option>
                            </select>
                        </div>
                        @endunless
                        <div class="col-lg-2">
                            <label class="form-label">Gaji Pokok</label>
                            <input type="number" class="form-control" data-hcm-ot-calc="baseSalary" min="0" step="1000" placeholder="5000000">
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label">Tunjangan Tetap</label>
                            <input type="number" class="form-control" data-hcm-ot-calc="fixedAllowance" min="0" step="1000" placeholder="500000">
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label">Menit Lembur</label>
                            <input type="number" class="form-control" data-hcm-ot-calc="minutes" min="1" max="1440" placeholder="120">
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label">Tipe Hari</label>
                            <select class="form-select" data-hcm-ot-calc="dayType">
                                <option value="workday">Hari kerja</option>
                                <option value="holiday">Hari libur</option>
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label">Minggu kerja</label>
                            <select class="form-select" data-hcm-ot-calc="weeklyWorkDays">
                                <option value="5">5 hari</option>
                                <option value="6">6 hari</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3 d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-outline-primary" data-hcm-ot-calc="run">Hitung lembur</button>
                        <div class="small text-muted">Acuan payroll internal; hasil lembur ditaut ke komponen slip lewat <a href="{{ url('payroll') }}">payroll items</a> (Upah lembur). Final mengikuti kebijakan HR.</div>
                    </div>
                    <div class="mt-3 alert alert-light mb-0" data-hcm-ot-calc="result">Belum ada perhitungan.</div>
                </div>
            </div>

            <!-- Performance Indicator list -->
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <h5>{{ $arcavOvertimeEmployeeOnly ? 'My overtime requests' : 'Overtime requests' }}</h5>
                    <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                        <span class="text-muted small">Filter menyusul.</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="custom-datatable-filter table-responsive">
                        <table class="table">
                            <thead class="thead-light">
                                <tr>
                                    @unless ($arcavOvertimeEmployeeOnly)
                                    <th data-hcm-ot-th-employee>Employee</th>
                                    @endunless
                                    <th>Date </th>
                                    <th>Overtime Hours</th>
                                    <th>Project</th>
                                    <th>Type</th>
                                    <th>Pay component</th>
                                    <th>Notes</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-hcm-overtime-body>
                                <tr>
                                    <td colspan="{{ $arcavOvertimeEmployeeOnly ? 8 : 9 }}" class="text-center text-muted py-4">Loading…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2" data-hcm-overtime-pagination style="display: none;">
                    <span class="text-muted small" data-hcm-overtime-page-info></span>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-light border" data-hcm-overtime-prev>Sebelumnya</button>
                        <button type="button" class="btn btn-sm btn-light border" data-hcm-overtime-next>Berikutnya</button>
                    </div>
                </div>
            </div>
            <!-- /Performance Indicator list -->

        </div>



    </div>
    <!-- /Page Wrapper -->

    @component('components.modal-popup')
    @endcomponent

    @include('hcm.partials.overtime-modals')

@endsection