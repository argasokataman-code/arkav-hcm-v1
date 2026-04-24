<?php $page = 'email-settings'; ?>
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
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
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
                    <a class="nav-link " href="{{url('profile-settings')}}"><i class="ti ti-settings me-2"></i>General Settings</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{url('business-settings')}}"><i class="ti ti-world-cog me-2"></i>Website Settings</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{url('salary-settings')}}"><i class="ti ti-device-ipad-horizontal-cog me-2"></i>App Settings</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="{{url('email-settings')}}"><i class="ti ti-server-cog me-2"></i>System Settings</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{url('payment-gateways')}}"><i class="ti ti-settings-dollar me-2"></i>Financial Settings</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{url('custom-css')}}"><i class="ti ti-settings-2 me-2"></i>Other Settings</a>
                </li>
            </ul>
            <div class="row">
                <div class="col-xl-3 theiaStickySidebar">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex flex-column list-group settings-list">
                                <a href="{{url('email-settings')}}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>Email Settings</a>
                                <a href="{{url('email-template')}}" class="d-inline-flex align-items-center rounded   py-2 px-3">Email Templates</a>
                                <a href="{{url('sms-settings')}}" class="d-inline-flex align-items-center rounded py-2 px-3">SMS Settings</a>
                                <a href="{{url('sms-template')}}" class="d-inline-flex align-items-center rounded py-2 px-3">SMS Templates</a>
                                <a href="{{url('otp-settings')}}" class="d-inline-flex align-items-center rounded py-2 px-3">OTP</a>
                                <a href="{{url('gdpr-cookies')}}" class="d-inline-flex align-items-center rounded py-2 px-3">GDPR Cookies</a>
                                <a href="{{url('maintenance-mode')}}" class="d-inline-flex align-items-center rounded py-2 px-3">Maintenance Mode</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-9">
                    <div class="card">
                        <div class="card-body">
                            <div class="border-bottom mb-3 pb-3 d-flex align-items-center justify-content-between">
                                <h4 class="mb-0">Email Settings</h4>
                                <small class="text-muted" data-email-settings-status>Loading…</small>
                            </div>

                            {{-- Mailtrap API Status card (global-admin only, wired via inline script below) --}}
                            <div class="card border mb-3" id="mailtrapStatusCard">
                                <div class="card-body py-3">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <div>
                                            <h6 class="mb-1">Mailtrap API Status</h6>
                                            <p class="text-muted mb-0" id="mailtrapStatusText">Checking configuration…</p>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge badge-secondary" id="mailtrapStatusBadge">Unknown</span>
                                            <button type="button" class="btn btn-sm btn-outline-light border" id="refreshMailtrapStatus">Refresh</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Feedback alert --}}
                            <div class="alert d-none mb-3" role="alert" data-email-settings-feedback></div>

                            {{-- Empty state --}}
                            <div class="text-center py-4 text-muted d-none" data-email-settings-empty>
                                <i class="ti ti-mail-off fs-2 d-block mb-2"></i>
                                <p class="mb-1">No email provider profile configured yet.</p>
                                <p class="small">Select a provider below and save your settings to get started.</p>
                            </div>

                            {{-- Main form --}}
                            <form data-email-settings-form>
                                <div data-email-settings-loaded>

                                    {{-- Sender identity --}}
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-3 mb-md-0">
                                            <label class="form-label">From Address</label>
                                            <input type="email" class="form-control" placeholder="noreply@example.com"
                                                data-field="fromAddress">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">From Name</label>
                                            <input type="text" class="form-control" placeholder="My App"
                                                data-field="fromName">
                                        </div>
                                    </div>

                                    {{-- Provider selector --}}
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Active Email Provider</label>
                                        <select class="form-select" data-email-settings-provider data-field="provider">
                                            <option value="smtp">SMTP</option>
                                            <option value="mailtrap">Mailtrap</option>
                                        </select>
                                    </div>

                                    {{-- SMTP section --}}
                                    <div data-email-settings-section="smtp" class="border rounded p-3 mb-3">
                                        <h6 class="mb-3"><i class="ti ti-server me-1"></i>SMTP Configuration</h6>
                                        <div class="row mb-3">
                                            <div class="col-md-8 mb-3 mb-md-0">
                                                <label class="form-label">SMTP Host</label>
                                                <input type="text" class="form-control" placeholder="smtp.example.com"
                                                    data-field="smtp.host">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Port</label>
                                                <input type="number" class="form-control" placeholder="587"
                                                    data-field="smtp.port">
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-4 mb-3 mb-md-0">
                                                <label class="form-label">Encryption</label>
                                                <select class="form-select" data-field="smtp.encryption">
                                                    <option value="tls">TLS</option>
                                                    <option value="ssl">SSL</option>
                                                    <option value="none">None</option>
                                                </select>
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label">Username</label>
                                                <input type="text" class="form-control" placeholder="SMTP username"
                                                    autocomplete="off" data-field="smtp.username">
                                            </div>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Password</label>
                                            <input type="password" class="form-control" placeholder="SMTP password"
                                                autocomplete="new-password" data-field="smtp.password">
                                            <div class="form-text">Leave blank to keep existing password.</div>
                                        </div>
                                    </div>

                                    {{-- Mailtrap section --}}
                                    <div data-email-settings-section="mailtrap" class="border rounded p-3 mb-3" style="display:none">
                                        <h6 class="mb-3"><i class="ti ti-brand-mailgun me-1"></i>Mailtrap Configuration</h6>
                                        <div class="row mb-3">
                                            <div class="col-md-4 mb-3 mb-md-0">
                                                <label class="form-label">Account ID</label>
                                                <input type="number" class="form-control" placeholder="123456"
                                                    data-field="mailtrap.accountId">
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label">API Token</label>
                                                <input type="password" class="form-control" placeholder="Mailtrap API token"
                                                    autocomplete="new-password" data-field="mailtrap.apiToken">
                                                <div class="form-text">Leave blank to keep existing token.</div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Test Connection result --}}
                                    <div class="mb-3 small d-none" data-email-settings-test-result></div>

                                    {{-- Actions --}}
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <button type="button" class="btn btn-outline-secondary"
                                            data-email-settings-test-conn>
                                            <i class="ti ti-plug-connected me-1"></i>Test Connection
                                        </button>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-outline-light border"
                                                data-email-settings-cancel>Cancel</button>
                                            <button type="submit" class="btn btn-primary"
                                                data-email-settings-submit>Save Settings</button>
                                        </div>
                                    </div>

                                </div>{{-- /data-email-settings-loaded --}}
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <!-- /Page Wrapper -->

    @component('components.modal-popup')
    @endcomponent

    {{-- Email settings runtime wiring --}}
    <script src="{{ URL::asset('build/js/email-settings-data.js') }}"></script>

    <script>
        $(document).ready(function() {
            function getAuthToken() {
                return (window.AuthApi && typeof window.AuthApi.getToken === 'function' && window.AuthApi.getToken()) ||
                    localStorage.getItem('arcav_access_token') ||
                    sessionStorage.getItem('arcav_access_token') ||
                    localStorage.getItem('token') ||
                    sessionStorage.getItem('token') ||
                    $('meta[name="api-token"]').attr('content') ||
                    null;
            }

            function applyStatus(connected, text) {
                var badge = $('#mailtrapStatusBadge');
                badge.removeClass('badge-success badge-danger badge-warning badge-secondary');
                if (connected === true) {
                    badge.addClass('badge-success').text('Connected');
                } else if (connected === false) {
                    badge.addClass('badge-warning').text('Not Connected');
                } else {
                    badge.addClass('badge-secondary').text('Unknown');
                }
                $('#mailtrapStatusText').text(text || 'No details.');
            }

            function loadMailtrapStatus() {
                applyStatus(null, 'Checking configuration…');
                var token = getAuthToken();
                if (!token) {
                    applyStatus(false, 'Auth token not found. Please login again.');
                    return;
                }

                $.ajax({
                    url: '/v1/hcm/email-settings/mailtrap-status',
                    type: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json',
                    }
                }).done(function(response) {
                    var data = response && response.data ? response.data : {};
                    if (!response || response.success !== true) {
                        applyStatus(false, 'Failed reading Mailtrap status.');
                        return;
                    }

                    if (!data.tokenConfigured || !data.accountId) {
                        applyStatus(false, 'MAILTRAP_API_TOKEN / MAILTRAP_ACCOUNT_ID not fully configured in env.');
                        return;
                    }

                    if (data.connected) {
                        applyStatus(true,
                            'Account #' + data.accountId + ' connected. Visible tokens: ' +
                            (data.visibleTokenCount || 0) + '. Token suffix: ' + (data.tokenLast4 || 'n/a')
                        );
                        return;
                    }

                    applyStatus(false, data.error || 'Unable to connect to Mailtrap API.');
                }).fail(function(xhr) {
                    if (xhr && xhr.status === 403) {
                        applyStatus(false, 'Forbidden: only global HCM admin can view Mailtrap status.');
                        return;
                    }
                    var msg = (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message)
                        ? xhr.responseJSON.error.message
                        : 'Request failed while checking Mailtrap status.';
                    applyStatus(false, msg);
                });
            }

            $('#refreshMailtrapStatus').on('click', function() {
                loadMailtrapStatus();
            });

            loadMailtrapStatus();
        });
    </script>
@endsection
