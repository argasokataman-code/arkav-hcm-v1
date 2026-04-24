<?php $page = 'notification-settings'; ?>
@extends('layout.mainlayout')
@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content">

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Settings</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            Administration
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Settings</li>
                    </ol>
                </nav>
            </div>
            <div class="head-icons ms-2">
                <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                    <i class="ti ti-chevrons-up"></i>
                </a>
            </div>
        </div>
        <!-- /Breadcrumb -->

        <ul class="nav nav-tabs nav-tabs-solid bg-transparent border-bottom mb-3">
            <li class="nav-item">
                <a class="nav-link active" href="{{ url('profile-settings') }}"><i class="ti ti-settings me-2"></i>General Settings</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ url('business-settings') }}"><i class="ti ti-world-cog me-2"></i>Website Settings</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ url('salary-settings') }}"><i class="ti ti-device-ipad-horizontal-cog me-2"></i>App Settings</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ url('email-settings') }}"><i class="ti ti-server-cog me-2"></i>System Settings</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ url('payment-gateways') }}"><i class="ti ti-settings-dollar me-2"></i>Financial Settings</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ url('custom-css') }}"><i class="ti ti-settings-2 me-2"></i>Other Settings</a>
            </li>
        </ul>
        <div class="row">
            <div class="col-xl-3 theiaStickySidebar">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-column list-group settings-list">
                            <a href="{{ url('profile-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Profile Settings</a>
                            <a href="{{ url('security-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Security Settings</a>
                            <a href="{{ url('notification-settings') }}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>Notifications</a>
                            <a href="{{ url('connected-apps') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Connected Apps</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-9">
                <div class="card">
                    <div class="card-body">
                        <div class="border-bottom mb-3 pb-3">
                            <h4>Notifications</h4>
                        </div>
                        <div class="alert d-none" data-notification-settings-feedback></div>
                        <form action="javascript:void(0);" data-notification-settings-form>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th class="w-75 ps-2 border-0">Modules</th>
                                            <th class="border-0">Push</th>
                                            <th class="border-0">SMS</th>
                                            <th class="pe-0 border-0">Email</th>
                                        </tr>
                                    </thead>
                                    <tbody data-notification-settings-rows></tbody>
                                </table>
                            </div>
                            <div class="d-flex align-items-center justify-content-between gap-2 pt-2">
                                <small class="text-muted" data-notification-settings-status>Loading notification preferences...</small>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-light" data-notification-settings-reset>Reset</button>
                                    <button type="submit" class="btn btn-primary" data-notification-settings-submit>Save Preferences</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                @if ((request()->user() ?: auth()->user())?->isGlobalHcmAdmin())
                <div class="card">
                    <div class="card-body" data-notification-observability-panel>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom mb-3 pb-3">
                            <div>
                                <h4 class="mb-1">Delivery Observability</h4>
                                <p class="text-muted mb-0">Ringkasan status delivery notifikasi untuk monitoring operasional.</p>
                                <a href="{{ url('notification-observability') }}" class="btn btn-sm btn-outline-primary mt-2">Open Full Dashboard</a>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <select class="form-select" data-notification-observability-hours>
                                    <option value="24" selected>Last 24h</option>
                                    <option value="72">Last 72h</option>
                                    <option value="168">Last 7d</option>
                                </select>
                                <select class="form-select" data-notification-observability-channel>
                                    <option value="">All channels</option>
                                    <option value="database">Database</option>
                                    <option value="mail">Mail</option>
                                    <option value="sms">SMS</option>
                                    <option value="webhook">Webhook</option>
                                </select>
                                <button type="button" class="btn btn-light" data-notification-observability-refresh>Refresh</button>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <div class="border rounded p-3 h-100">
                                    <small class="text-muted d-block mb-1">Total Events</small>
                                    <h4 class="mb-0" data-observability-total-all>0</h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 h-100">
                                    <small class="text-muted d-block mb-1">Sent</small>
                                    <h4 class="mb-0 text-success" data-observability-total-sent>0</h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 h-100">
                                    <small class="text-muted d-block mb-1">Failed</small>
                                    <h4 class="mb-0 text-danger" data-observability-total-failed>0</h4>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 h-100">
                                    <small class="text-muted d-block mb-1">Dropped</small>
                                    <h4 class="mb-0 text-warning" data-observability-total-dropped>0</h4>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-2">Breakdown by Status</h6>
                                    <div data-observability-status-breakdown class="small text-muted">No data</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-2">Top Failed Events</h6>
                                    <div data-observability-top-failed class="small text-muted">No data</div>
                                </div>
                            </div>
                        </div>

                        <small class="text-muted d-block mt-3" data-observability-last-updated>Last updated: -</small>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

</div>
<!-- /Page Wrapper -->

@endsection