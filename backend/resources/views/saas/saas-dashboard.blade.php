<?php $page = 'saas-dashboard'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">SaaS Analytics Dashboard</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Superadmin
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Analytics</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                    <div class="ms-2 head-icons">
                        <a href="javascript:location.reload();" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Refresh">
                            <i class="ti ti-refresh"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <!-- KPI Cards -->
            <div class="row mb-4" id="kpi_container">
                <!-- KPI cards will be loaded here by JS -->
            </div>
            <!-- /KPI Cards -->

            <!-- Tabs Navigation -->
            <div class="card mb-3">
                <div class="card-body">
                    <div class="btn-group d-flex" role="group">
                        <button type="button" class="btn btn-outline-primary active" data-dashboard-tab="overview">
                            <i class="ti ti-chart-bar me-2"></i>Overview
                        </button>
                        <button type="button" class="btn btn-outline-primary" data-dashboard-tab="companies">
                            <i class="ti ti-building me-2"></i>Companies
                        </button>
                        <button type="button" class="btn btn-outline-primary" data-dashboard-tab="revenue">
                            <i class="ti ti-chart-line me-2"></i>Revenue
                        </button>
                        <button type="button" class="btn btn-outline-primary" data-dashboard-tab="audit">
                            <i class="ti ti-clipboard-list me-2"></i>Audit Logs
                        </button>
                    </div>
                </div>
            </div>

            <!-- Overview Tab -->
            <div class="dashboard-tab" id="tab_overview">
                <div class="row mb-4">
                    <!-- Subscription Status -->
                    <div class="col-lg-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header">
                                <h5 class="card-title">Subscription Status</h5>
                            </div>
                            <div class="card-body">
                                <div id="subscription_status">
                                    <p class="text-muted">Loading...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /Subscription Status -->

                    <!-- Revenue by Plan -->
                    <div class="col-lg-6 d-flex">
                        <div class="card flex-fill">
                            <div class="card-header">
                                <h5 class="card-title">Revenue by Plan</h5>
                            </div>
                            <div class="card-body">
                                <div id="revenue_by_plan">
                                    <p class="text-muted">Loading...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /Revenue by Plan -->
                </div>
            </div>
            <!-- /Overview Tab -->

            <!-- Companies Tab -->
            <div class="dashboard-tab" id="tab_companies" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Companies Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="companies_table" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Company Name</th>
                                        <th>Users</th>
                                        <th>Subscriptions</th>
                                        <th>Revenue</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="5" class="text-center py-3">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="pagination_container"></div>
                    </div>
                </div>
            </div>
            <!-- /Companies Tab -->

            <!-- Revenue Tab -->
            <div class="dashboard-tab" id="tab_revenue" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Monthly Revenue Trend (12 Months)</h5>
                    </div>
                    <div class="card-body">
                        <div id="revenue_chart">
                            <p class="text-muted">Loading...</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Revenue Tab -->

            <!-- Audit Tab -->
            <div class="dashboard-tab" id="tab_audit" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Audit Logs</h5>
                        <div class="float-end">
                            <select id="audit_filter_select" class="form-select form-select-sm">
                                <option value="all">All Actions</option>
                                <option value="modify_subscription">Modify Subscription</option>
                                <option value="delete_company">Delete Company</option>
                                <option value="refund_transaction">Refund Transaction</option>
                                <option value="reset_user_password">Reset Password</option>
                                <option value="delete_user">Delete User</option>
                                <option value="modify_billing">Modify Billing</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="audit_logs_table" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Admin</th>
                                        <th>Action</th>
                                        <th>Target Type</th>
                                        <th>Date</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="5" class="text-center py-3">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Audit Tab -->

        </div>
    </div>

    <!-- Trend Modal -->
    <div class="modal fade" id="trend_modal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Metric Trend</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="trend_content"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('build/js/super-admin-dashboard-data.js') }}?v={{ filemtime(public_path('build/js/super-admin-dashboard-data.js')) }}"></script>

@endsection
