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
                            <div class="border-bottom mb-3 pb-3">
                                <h4>Email Settings</h4>
                            </div>
                            <div class="card border mb-3" id="mailtrapStatusCard">
                                <div class="card-body py-3">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <div>
                                            <h6 class="mb-1">Mailtrap API Status</h6>
                                            <p class="text-muted mb-0" id="mailtrapStatusText">Checking configuration...</p>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge badge-secondary" id="mailtrapStatusBadge">Unknown</span>
                                            <button type="button" class="btn btn-sm btn-outline-light border" id="refreshMailtrapStatus">Refresh</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <form action="{{url("email-settings")}}">
                                <div class="border-bottom mb-3">
                                    <div class="row">
                                        
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 d-flex">
                                            <div class="card flex-fill">
                                                <div class="card-body">
                                                    <div class="border-bottom pb-3 mb-3">
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <div class="d-flex align-items-center">
                                                                <span class="avatar avatar-xl p-2 me-2 bg-light flex-shrink-0">
                                                                    <img src="{{ URL::asset('build/img/settings/phpmail.svg') }}" alt="Profile">
                                                                </span>
                                                                <h5>PHP Mailer</h5>
                                                            </div>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" role="switch" id="flexSwitchCheckDefault">
                                                            </div>
                                                        </div>
                                                        <p>Used to send emails safely and easily via PHP code from a web server.</p>
                                                    </div>
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <span class="btn btn-sm d-inline-flex align-items-center btn-dark">
                                                            <i class="ti ti-checks me-1"></i>Connected
                                                        </span>
                                                        <a href="#" data-bs-toggle="modal" data-bs-target="#phpmailersettings" class="btn btn-icon btn-sm text-gray-5 fs-20"><i class="ti ti-settings"></i></a>
                                                    </div>
                                                
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 d-flex">
                                            <div class="card flex-fill">
                                                <div class="card-body">
                                                    <div class="border-bottom pb-3 mb-3">
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <div class="d-flex align-items-center">
                                                                <span class="avatar avatar-xl me-2 p-2 bg-light flex-shrink-0">
                                                                    <img src="{{ URL::asset('build/img/settings/smtp.svg') }}" alt="Profile">
                                                                </span>
                                                                <h5>SMTP</h5>
                                                            </div>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" role="switch" id="flexSwitchCheckDefault2">
                                                            </div>
                                                        </div>
                                                        <p>SMTP is used to send, relay or forward messages from a mail client.</p>
                                                    </div>
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <span class="btn btn-sm d-inline-flex align-items-center btn-light">
                                                            <i class="ti ti-checks me-1"></i>Connected
                                                        </span>
                                                        <a href="#"  data-bs-toggle="modal" data-bs-target="#smtpsettings" class="btn btn-icon btn-sm text-gray-5 fs-20"><i class="ti ti-settings"></i></a>
                                                    </div>
                                                
                                                </div>
                                            </div>
                                        </div>
                                    
                                    </div>
                                </div>
                                
                                <div class="d-flex align-items-center justify-content-end">
                                    <button type="button" class="btn btn-outline-light border me-3">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save</button>
                                </div>
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

    <script>
        $(document).ready(function() {
            function getAuthToken() {
                return localStorage.getItem('arcav_access_token') ||
                    sessionStorage.getItem('arcav_access_token') ||
                    localStorage.getItem('token') ||
                    sessionStorage.getItem('token') ||
                    $('meta[name="api-token"]').attr('content') ||
                    $('meta[name="auth-token"]').attr('content') ||
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
                applyStatus(null, 'Checking configuration...');
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
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                }).done(function(response) {
                    var data = response && response.data ? response.data : {};
                    if (!response || response.success !== true) {
                        applyStatus(false, 'Failed reading Mailtrap status.');
                        return;
                    }

                    if (!data.tokenConfigured || !data.accountId) {
                        applyStatus(false, 'MAILTRAP_API_TOKEN / MAILTRAP_ACCOUNT_ID belum lengkap di env.');
                        return;
                    }

                    if (data.connected) {
                        applyStatus(true,
                            'Account #' + data.accountId + ' connected. Visible tokens: ' + (data.visibleTokenCount || 0) + '. Token suffix: ' + (data.tokenLast4 || 'n/a')
                        );
                        return;
                    }

                    applyStatus(false, data.error || 'Unable to connect to Mailtrap API.');
                }).fail(function(xhr) {
                    if (xhr && xhr.status === 403) {
                        applyStatus(false, 'Forbidden: hanya HCM Admin yang boleh lihat status Mailtrap.');
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
