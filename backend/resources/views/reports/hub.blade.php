<?php $page = 'reports'; ?>
@extends('layout.mainlayout')
@section('content')
<style>
    .report-card {
        border: 1px solid #e9ecef;
        border-radius: 0.5rem;
        transition: all 0.3s ease;
    }
    .report-card:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        transform: translateY(-2px);
    }
    .report-icon {
        width: 60px;
        height: 60px;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
</style>

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content">

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Reports Center</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Reports</li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- /Breadcrumb -->

        <!-- Alert Messages -->
        <div id="alerts_container"></div>

        <div class="alert alert-info border-0 shadow-sm mb-4">
            <div class="d-flex align-items-start gap-2">
                <i class="ti ti-database fs-4 mt-1"></i>
                <div>
                    <div class="fw-medium">Report data flow</div>
                    <div class="small text-muted">View membuka halaman report yang bisa switch ke Live API atau Archive Snapshot. Generate membuat snapshot untuk dipakai ulang dari menu Reports Hub.</div>
                </div>
            </div>
        </div>

        <!-- HR/HCM Reports -->
        <div class="mb-4">
            <h5 class="mb-3"><i class="ti ti-briefcase me-2"></i>HR & Human Resources</h5>
            <div class="row">
                <!-- Attendance Report -->
                <div class="col-lg-6 col-xl-4 mb-3">
                    <div class="report-card card">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="report-icon bg-primary-transparent text-primary">
                                    <i class="ti ti-calendar-check"></i>
                                </div>
                                <div class="ms-3">
                                    <h5 class="mb-0">Attendance Report</h5>
                                    <small class="text-muted">Daily attendance records</small>
                                </div>
                            </div>
                            <p class="text-muted fs-12 mb-3">Generate snapshots of attendance records aggregated by date, user, and status.</p>
                            <div class="d-flex gap-2">
                                <a href="{{ url('attendance-report') }}" class="btn btn-sm btn-light">
                                    <i class="ti ti-eye me-1"></i>View
                                </a>
                                <button class="btn btn-sm btn-primary" data-report-generate="attendance">
                                    <i class="ti ti-download me-1"></i>Generate
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Report -->
                <div class="col-lg-6 col-xl-4 mb-3">
                    <div class="report-card card">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="report-icon bg-success-transparent text-success">
                                    <i class="ti ti-coin"></i>
                                </div>
                                <div class="ms-3">
                                    <h5 class="mb-0">Monthly Report</h5>
                                    <small class="text-muted">Monthly + THR + PKWT detail</small>
                                </div>
                            </div>
                            <p class="text-muted fs-12 mb-3">Laporan payroll bulanan detail per karyawan dengan breakdown monthly, THR, dan kompensasi PKWT plus ekspor Excel.</p>
                            <div class="d-flex gap-2">
                                <a href="{{ url('monthly-report') }}" class="btn btn-sm btn-light">
                                    <i class="ti ti-eye me-1"></i>View
                                </a>
                                <button class="btn btn-sm btn-primary" data-report-generate="payroll">
                                    <i class="ti ti-download me-1"></i>Generate
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employee Report -->
                <div class="col-lg-6 col-xl-4 mb-3">
                    <div class="report-card card">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="report-icon bg-info-transparent text-info">
                                    <i class="ti ti-users"></i>
                                </div>
                                <div class="ms-3">
                                    <h5 class="mb-0">Employee Report</h5>
                                    <small class="text-muted">Workforce overview</small>
                                </div>
                            </div>
                            <p class="text-muted fs-12 mb-3">Workforce statistics including headcount, status distribution, and demographics.</p>
                            <div class="d-flex gap-2">
                                <a href="{{ url('employee-report') }}" class="btn btn-sm btn-light">
                                    <i class="ti ti-eye me-1"></i>View
                                </a>
                                <button class="btn btn-sm btn-primary" data-report-generate="employee">
                                    <i class="ti ti-download me-1"></i>Generate
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Leave Report -->
                <div class="col-lg-6 col-xl-4 mb-3">
                    <div class="report-card card">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="report-icon bg-warning-transparent text-warning">
                                    <i class="ti ti-calendar-off"></i>
                                </div>
                                <div class="ms-3">
                                    <h5 class="mb-0">Leave Report</h5>
                                    <small class="text-muted">Leave requests & approvals</small>
                                </div>
                            </div>
                            <p class="text-muted fs-12 mb-3">Leave request snapshots grouped by type, user, period, and approval status.</p>
                            <div class="d-flex gap-2">
                                <a href="{{ url('leave-report') }}" class="btn btn-sm btn-light">
                                    <i class="ti ti-eye me-1"></i>View
                                </a>
                                <button class="btn btn-sm btn-primary" data-report-generate="leave">
                                    <i class="ti ti-download me-1"></i>Generate
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Finance Reports -->
        <div class="mb-4">
            <h5 class="mb-3"><i class="ti ti-chart-line me-2"></i>Finance & Accounting</h5>
            <div class="row">
                <!-- Finance Report -->
                <div class="col-lg-6 col-xl-4 mb-3">
                    <div class="report-card card">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="report-icon bg-danger-transparent text-danger">
                                    <i class="ti ti-receipt"></i>
                                </div>
                                <div class="ms-3">
                                    <h5 class="mb-0">Finance Report</h5>
                                    <small class="text-muted">Invoices, payments & transactions</small>
                                </div>
                            </div>
                            <p class="text-muted fs-12 mb-3">Financial snapshots with invoice aging, payment status, and transaction summaries.</p>
                            <div class="d-flex gap-2">
                                <a href="{{ url('invoice-report') }}" class="btn btn-sm btn-light">
                                    <i class="ti ti-eye me-1"></i>View
                                </a>
                                <button class="btn btn-sm btn-primary" data-report-generate="finance">
                                    <i class="ti ti-download me-1"></i>Generate
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Snapshot History -->
        <div class="card border-top border-4 border-primary">
            <div class="card-header">
                <div class="d-flex align-items-center">
                    <h5 class="card-title flex-grow-1"><i class="ti ti-history me-2"></i>Recent Snapshots</h5>
                    <button class="btn btn-sm btn-light" id="refresh_snapshots">
                        <i class="ti ti-refresh"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="snapshots_loading" class="text-center p-4">
                    <div class="spinner-border spinner-border-sm" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <table id="snapshots_table" class="table table-hover mb-0" style="display:none;">
                    <thead class="table-light">
                        <tr>
                            <th>Report Type</th>
                            <th>Period</th>
                            <th>Status</th>
                            <th>Generated</th>
                            <th>Rows</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="snapshots_tbody">
                    </tbody>
                </table>
                <div id="snapshots_empty" class="alert alert-info m-3 mb-0">
                    <i class="ti ti-info-circle me-2"></i>No snapshots generated yet. Click "Generate" on any report card above to create your first snapshot.
                </div>
            </div>
        </div>

    </div>
</div>
<!-- /Page Wrapper -->

<!-- Generation Modal -->
<div class="modal fade" id="generate_modal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Report Snapshot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="generate_form">
                    <input type="hidden" id="gen_report_type" name="reportType">

                    <div class="mb-3">
                        <label class="form-label">Period Start</label>
                        <input type="date" class="form-control" id="gen_period_start" name="periodStart" required>

    <div class="invalid-feedback">Please select a date.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Period End</label>
                        <input type="date" class="form-control" id="gen_period_end" name="periodEnd" required>

    <div class="invalid-feedback">Please select a date.</div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="gen_async" name="async" value="1">
                        <label class="form-check-label" for="gen_async">
                            Generate in background (async)
                        </label>
                        <small class="form-text text-muted d-block mt-1">
                            Uncheck to generate immediately and wait for results.
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="gen_submit">Generate</button>
            </div>
        </div>
    </div>
</div>

@endsection
