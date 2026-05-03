<?php $page = 'email-settings'; ?>
@php
    $isGlobalHcmAdmin = (bool) ((request()->user() ?: auth()->user())?->isGlobalHcmAdmin());
@endphp

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
                @if ($isGlobalHcmAdmin)

                <li class="nav-item">
                    <a class="nav-link" href="{{url('business-settings')}}"><i class="ti ti-world-cog me-2"></i>Website Settings</a>
                </li>

                @endif
                <li class="nav-item">
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="{{url('email-settings')}}"><i class="ti ti-server-cog me-2"></i>System Settings</a>
                </li>
                @if ($isGlobalHcmAdmin)

                <li class="nav-item">
                    <a class="nav-link" href="{{url('payment-gateways')}}"><i class="ti ti-settings-dollar me-2"></i>Financial Settings</a>
                </li>

                @endif
                @if ($isGlobalHcmAdmin)

                <li class="nav-item">
                    <a class="nav-link" href="{{url('custom-css')}}"><i class="ti ti-settings-2 me-2"></i>Other Settings</a>
                </li>

                @endif
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
                            <div class="alert d-none" data-email-settings-feedback></div>
                            <div class="card border mb-3" id="mailtrapStatusCard" data-mailtrap-status-card>
                                <div class="card-body py-3">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                        <div>
                                            <h6 class="mb-1">Mailtrap API Status</h6>
                                            <p class="text-muted mb-0" id="mailtrapStatusText" data-mailtrap-status-text>Checking configuration...</p>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge badge-secondary" id="mailtrapStatusBadge" data-mailtrap-status-badge>Unknown</span>
                                            <button type="button" class="btn btn-sm btn-outline-light border" id="refreshMailtrapStatus" data-mailtrap-status-refresh>Refresh</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <form action="javascript:void(0);" data-email-settings-shell>
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
                                                                <input class="form-check-input" type="checkbox" role="switch" id="flexSwitchCheckDefault" data-provider-switch="mailtrap">
                                                            </div>
                                                        </div>
                                                        <p>Used to send emails safely and easily via PHP code from a web server.</p>
                                                    </div>
                                                        <div class="d-flex align-items-center justify-content-between">
                                                            <span class="btn btn-sm d-inline-flex align-items-center btn-dark" data-provider-status="mailtrap">
                                                            <i class="ti ti-checks me-1"></i>Connected
                                                        </span>
                                                            <a href="#" data-bs-toggle="modal" data-bs-target="#phpmailersettings" class="btn btn-icon btn-sm text-gray-5 fs-20" data-provider-modal-trigger="mailtrap"><i class="ti ti-settings"></i></a>
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
                                                                <input class="form-check-input" type="checkbox" role="switch" id="flexSwitchCheckDefault2" data-provider-switch="smtp">
                                                            </div>
                                                        </div>
                                                        <p>SMTP is used to send, relay or forward messages from a mail client.</p>
                                                    </div>
                                                        <div class="d-flex align-items-center justify-content-between">
                                                            <span class="btn btn-sm d-inline-flex align-items-center btn-light" data-provider-status="smtp">
                                                            <i class="ti ti-checks me-1"></i>Connected
                                                        </span>
                                                            <a href="#"  data-bs-toggle="modal" data-bs-target="#smtpsettings" class="btn btn-icon btn-sm text-gray-5 fs-20" data-provider-modal-trigger="smtp"><i class="ti ti-settings"></i></a>
                                                    </div>
                                                
                                                </div>
                                            </div>
                                        </div>
                                    
                                    </div>
                                </div>
                                
                                <div class="d-flex align-items-center justify-content-end">
                                    <button type="button" class="btn btn-outline-light border me-3" data-email-settings-cancel>Cancel</button>
                                    <button type="submit" class="btn btn-primary" data-email-settings-save disabled>Save</button>
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

    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1080;">
        <div class="toast align-items-center border-0" role="status" aria-live="polite" aria-atomic="true" data-email-settings-toast>
            <div class="d-flex">
                <div class="toast-body" data-email-settings-toast-message></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
@endsection
