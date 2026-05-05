<?php $page = 'super-admin-employees-monitor'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Global Employee Monitor</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">Super Admin</li>
                            <li class="breadcrumb-item active" aria-current="page">Employee Monitor</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                    <button class="btn btn-outline-secondary me-2 mb-2" id="btn-refresh-monitor">
                        <i class="ti ti-refresh me-1"></i>Refresh
                    </button>
                    <button class="btn btn-white mb-2" id="btn-export-monitor">
                        <i class="ti ti-file-export me-1"></i>Export CSV
                    </button>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <!-- Summary KPI Cards -->
            <div class="row g-3 mb-4" id="em-kpi-row">
                <div class="col-xl-2 col-sm-4 col-6">
                    <div class="card h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-md bg-primary-transparent me-2 flex-shrink-0">
                                    <i class="ti ti-building fs-20 text-primary"></i>
                                </span>
                                <div>
                                    <p class="fs-11 text-muted mb-0">Active Tenants</p>
                                    <h5 class="mb-0" id="em-stat-active-companies">—</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-sm-4 col-6">
                    <div class="card h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-md bg-success-transparent me-2 flex-shrink-0">
                                    <i class="ti ti-users fs-20 text-success"></i>
                                </span>
                                <div>
                                    <p class="fs-11 text-muted mb-0">Total Employees</p>
                                    <h5 class="mb-0" id="em-stat-total">—</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-sm-4 col-6">
                    <div class="card h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-md bg-success-transparent me-2 flex-shrink-0">
                                    <i class="ti ti-user-check fs-20 text-success"></i>
                                </span>
                                <div>
                                    <p class="fs-11 text-muted mb-0">Active</p>
                                    <h5 class="mb-0" id="em-stat-active">—</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-sm-4 col-6">
                    <div class="card h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-md bg-warning-transparent me-2 flex-shrink-0">
                                    <i class="ti ti-user-exclamation fs-20 text-warning"></i>
                                </span>
                                <div>
                                    <p class="fs-11 text-muted mb-0">Probation</p>
                                    <h5 class="mb-0" id="em-stat-probation">—</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-sm-4 col-6">
                    <div class="card h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-md bg-info-transparent me-2 flex-shrink-0">
                                    <i class="ti ti-user-plus fs-20 text-info"></i>
                                </span>
                                <div>
                                    <p class="fs-11 text-muted mb-0">New Hires</p>
                                    <h6 class="mb-0 fs-11 text-muted" id="em-stat-month-label"></h6>
                                    <h5 class="mb-0" id="em-stat-new-hires">—</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-sm-4 col-6">
                    <div class="card h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <span class="avatar avatar-md bg-danger-transparent me-2 flex-shrink-0">
                                    <i class="ti ti-calendar-x fs-20 text-danger"></i>
                                </span>
                                <div>
                                    <p class="fs-11 text-muted mb-0">Expiring Contracts</p>
                                    <h6 class="mb-0 fs-11 text-muted">30 days</h6>
                                    <h5 class="mb-0" id="em-stat-expiring">—</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /KPI Cards -->

            <!-- Charts Row -->
            <div class="row g-3 mb-4">
                <div class="col-xl-4 col-md-5">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Employment Status</h5>
                        </div>
                        <div class="card-body d-flex align-items-center justify-content-center">
                            <div id="em-status-chart" style="min-height:220px;width:100%;"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-8 col-md-7">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">New Hires Trend (6 Months)</h5>
                        </div>
                        <div class="card-body">
                            <div id="em-hire-trend-chart" style="min-height:220px;width:100%;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Charts -->

            <!-- Company Table -->
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h5 class="card-title mb-0">Employee Distribution per Tenant</h5>
                    <div class="d-flex align-items-center gap-2">
                        <input type="text" class="form-control form-control-sm w-auto" id="em-search-input" placeholder="Search company...">
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0" id="em-company-table">
                            <thead class="thead-light">
                                <tr>
                                    <th class="ps-3">Company</th>
                                    <th>Code</th>
                                    <th>Status</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center text-success">Active</th>
                                    <th class="text-center text-warning">Probation</th>
                                    <th class="text-center text-danger">Inactive</th>
                                    <th class="text-center text-info">New Hires</th>
                                    <th class="text-center text-danger">Exp. Contracts</th>
                                </tr>
                            </thead>
                            <tbody id="em-company-tbody">
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        <span class="ms-2">Loading data...</span>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot id="em-company-tfoot" class="fw-semibold bg-light"></tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <!-- /Company Table -->

        </div>
    </div>
    <!-- /Page Wrapper -->

@endsection

@push('scripts')
<script src="{{ asset('build/js/super-admin/employees-monitor.js') }}"></script>
@endpush
