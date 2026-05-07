<?php $page = 'security-settings'; ?>
@php
    $isGlobalHcmAdmin = (bool) ((request()->user() ?: auth()->user())?->isGlobalHcmAdmin());
    $profileSettingsUrl = strtolower(trim((string) request()->attributes->get('activeCompanyRole', ''))) === 'owner'
        ? url('company-profile')
        : url('profile-settings');
    $profileSettingsLabel = strtolower(trim((string) request()->attributes->get('activeCompanyRole', ''))) === 'owner'
        ? 'Company Profile'
        : 'Profile Settings';
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
            @if ($isGlobalHcmAdmin)

            <li class="nav-item">
                <a class="nav-link" href="{{ url('business-settings') }}"><i class="ti ti-world-cog me-2"></i>Website Settings</a>
            </li>

            @endif
            <li class="nav-item">
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ url('email-settings') }}"><i class="ti ti-server-cog me-2"></i>System Settings</a>
            </li>
            @if ($isGlobalHcmAdmin)

            <li class="nav-item">
                <a class="nav-link" href="{{ url('payment-gateways') }}"><i class="ti ti-settings-dollar me-2"></i>Financial Settings</a>
            </li>

            @endif
            @if ($isGlobalHcmAdmin)
            <li class="nav-item">
                <a class="nav-link" href="{{ url('custom-css') }}"><i class="ti ti-settings-2 me-2"></i>Other Settings</a>
            </li>
            @endif
        </ul>
        <div class="row">
            <div class="col-xl-3 theiaStickySidebar">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-column list-group settings-list">
                            <a href="{{ $profileSettingsUrl }}" class="d-inline-flex align-items-center rounded py-2 px-3">{{ $profileSettingsLabel }}</a>
                            <a href="{{ url('security-settings') }}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>Security Settings</a>
                            <a href="{{ url('notification-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Notifications</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-9">
                <div class="card">
                    <div class="card-body">
                        <div class="border-bottom mb-3 pb-3">
                            <h4>Security Settings</h4>
                        </div>
                        <div>
                            <!-- Change Password -->
                            <div class="border-bottom mb-3 pb-3">
                                <div class="d-flex justify-content-between align-items-start flex-wrap row-gap-2">
                                    <div>
                                        <h5 class="fw-medium mb-1">Change Password</h5>
                                        <p class="text-muted mb-0">Set a unique password to protect your account</p>
                                    </div>
                                    <div>
                                        <a href="#changePasswordForm" class="btn btn-dark" data-bs-toggle="collapse" aria-expanded="false" data-security-change-password-toggle>
                                            Change Password
                                        </a>
                                    </div>
                                </div>
                                <div class="collapse mt-3" id="changePasswordForm">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Current Password</label>
                                            <div class="pass-group">
                                                <input type="password" class="pass-input form-control" data-security-current-password autocomplete="current-password" placeholder="Enter current password">
                                                <span class="ti toggle-password ti-eye-off"></span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">New Password</label>
                                            <div class="pass-group">
                                                <input type="password" class="pass-inputs form-control" data-security-new-password autocomplete="new-password" placeholder="Enter new password">
                                                <span class="ti toggle-passwords ti-eye-off"></span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Confirm New Password</label>
                                            <div class="pass-group">
                                                <input type="password" class="pass-inputa form-control" data-security-confirm-password autocomplete="new-password" placeholder="Confirm new password">
                                                <span class="ti toggle-passworda ti-eye-off"></span>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-primary" data-security-save-password>Save Password</button>
                                                <button type="button" class="btn btn-outline-light border" data-bs-toggle="collapse" data-bs-target="#changePasswordForm">Cancel</button>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="alert d-none" role="alert" data-security-password-feedback></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Two Factor Authentication -->
                            <div class="border-bottom mb-3 pb-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap row-gap-2">
                                    <div>
                                        <h5 class="fw-medium mb-1">Two Factor Authentication</h5>
                                        <p class="text-muted mb-0">Receive codes via SMS or email every time you log in</p>
                                    </div>
                                    <div>
                                        <div class="form-check form-check-md form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="twoFactorSwitch" data-security-2fa>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Google Authentication -->
                            <div class="border-bottom mb-3 pb-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap row-gap-2">
                                    <div>
                                        <h5 class="fw-medium d-flex align-items-center mb-1">
                                            Google Authentication
                                            <span class="badge badge-xs ms-2 bg-outline-success rounded-pill d-flex align-items-center">
                                                <i class="ti ti-point-filled me-1"></i>Connected
                                            </span>
                                        </h5>
                                        <p class="text-muted mb-0">Connect your Google account for faster sign-in</p>
                                    </div>
                                    <div>
                                        <div class="form-check form-check-md form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="googleAuthSwitch" data-security-google-auth>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Phone Number Verification -->
                            <div class="border-bottom mb-3 pb-3">
                                <div class="d-flex justify-content-between align-items-start flex-wrap row-gap-2">
                                    <div>
                                        <h5 class="fw-medium d-flex align-items-center mb-1">
                                            Phone Number Verification
                                            <i class="ti ti-discount-check-filled text-success ms-2"></i>
                                        </h5>
                                        <p class="text-muted mb-0">The phone number associated with your account</p>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="#" class="btn btn-outline-light border" data-security-remove-phone>Remove</a>
                                        <a href="#" class="btn btn-dark" data-security-change-phone>Change</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Email Verification -->
                            <div class="border-bottom mb-3 pb-3">
                                <div class="d-flex justify-content-between align-items-start flex-wrap row-gap-2">
                                    <div>
                                        <h5 class="fw-medium d-flex align-items-center mb-1">
                                            Email Verification
                                            <i class="ti ti-discount-check-filled text-success ms-2"></i>
                                        </h5>
                                        <p class="text-muted mb-0">Verified email: <strong>{{ auth()->user()?->email }}</strong></p>
                                    </div>
                                    <div>
                                        <a href="#" class="btn btn-dark" data-security-change-email>Change Email</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Deactivate Account -->
                            <div class="border-bottom mb-3 pb-3">
                                <div class="d-flex justify-content-between align-items-start flex-wrap row-gap-2">
                                    <div>
                                        <h5 class="fw-medium mb-1">Deactivate Account</h5>
                                        <p class="text-muted mb-0">Your account will be temporarily disabled. You can reactivate it by signing in again.</p>
                                    </div>
                                    <div>
                                        <a href="#" class="btn btn-danger" data-security-deactivate>Deactivate</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
 
</div>
<!-- /Page Wrapper -->

@endsection