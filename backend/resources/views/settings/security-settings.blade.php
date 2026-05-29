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

            @endif
            @if ($isGlobalHcmAdmin)
            <li class="nav-item">
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
                            <div class="mb-3 pb-3">
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
 
</div>
<!-- /Page Wrapper -->

@endsection