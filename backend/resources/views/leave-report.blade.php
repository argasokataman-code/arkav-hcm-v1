<?php $page = 'leave-report'; ?>
@extends('layout.mainlayout')
@section('content')
<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content">

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Leave Report</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            HR
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Leave Report</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap ">
                <div class="mb-2 me-2 d-flex align-items-center gap-2">
                    <label class="mb-0 small text-muted">Source</label>
                    <select class="form-select form-select-sm" style="min-width: 170px;" data-leave-report-source>
                        <option value="live" selected>Live Data</option>
                        <option value="archive">Archive Snapshot</option>
                    </select>
                </div>
                <div class="mb-2 me-2 d-none" data-leave-report-snapshot-wrap>
                    <input type="number" class="form-control form-control-sm" min="1" placeholder="Snapshot ID" data-leave-report-snapshot-id>
                </div>
                <div class="mb-2 me-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" data-leave-report-load>
                        <i class="ti ti-refresh me-1"></i>Load
                    </button>
                </div>
                <div class="mb-2 me-2">
                    <span class="badge bg-light text-dark" data-leave-report-source-badge>Source: Live</span>
                </div>
                <div class="mb-2 me-2 w-100">
                    <span class="text-muted small">Live memakai leave request API; Archive memakai Snapshot ID dari Reports Hub.</span>
                </div>
                <div class="mb-2">
                    <span class="text-muted small d-inline-flex align-items-center"><i class="ti ti-file-export me-1"></i>Export via Reports Hub.</span>
                </div>
                <div class="head-icons ms-2">
                    <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                        <i class="ti ti-chevrons-up"></i>
                    </a>
                </div>
            </div>
        </div>
        <!-- /Breadcrumb -->

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <p class="text-muted small mb-1">Total Requests</p>
                        <h5 class="mb-0" data-leave-report-total-requests>0</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <p class="text-muted small mb-1">Total Days</p>
                        <h5 class="mb-0" data-leave-report-total-days>0</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <p class="text-muted small mb-1">Approved</p>
                        <h5 class="mb-0" data-leave-report-approved>0</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <p class="text-muted small mb-1">Pending</p>
                        <h5 class="mb-0" data-leave-report-pending>0</h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body py-3">
                <div id="leave-report-chart" style="min-height: 240px;"></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                <h5>Daftar cuti</h5>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                    <span class="text-muted small">Gunakan Source dan Snapshot ID untuk memuat data yang aktif.</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="custom-datatable-filter table-responsive">
                    <table class="table">
                        <thead class="thead-light">
                            <tr>
                                <th>Employee</th>
                                <th>Leave type</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Days</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody data-leave-report-body>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Loading leave report…</td>
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