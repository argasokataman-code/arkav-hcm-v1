<?php $page = 'authentication-settings'; ?>
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
                <a class="nav-link" href="{{ url('profile-settings') }}"><i class="ti ti-settings me-2"></i>General Settings</a>
            </li>
            @if ($isGlobalHcmAdmin)

            <li class="nav-item">
                @if ($isGlobalHcmAdmin)
                <a class="nav-link active" href="{{ url('business-settings') }}"><i class="ti ti-world-cog me-2"></i>Website Settings</a>
                @endif
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
                            @if ($isGlobalHcmAdmin)
                            <a href="{{ url('business-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Business Settings</a>
                            @endif
                            @if ($isGlobalHcmAdmin)
                            <a href="{{ url('seo-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">SEO Settings</a>
                            @endif
                            @if ($isGlobalHcmAdmin)
                            <a href="{{ url('localization-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Localization</a>
                            @endif
                            @if ($isGlobalHcmAdmin)
                            @endif
                            <a href="{{ url('appearance') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Appearance</a>
                            @if ($isGlobalHcmAdmin)
                            <a href="{{ url('language') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Language</a>
                            @endif
                            @if ($isGlobalHcmAdmin)
                            <a href="{{ url('authentication-settings') }}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>Authentication Settings</a>
                            @endif
                            @if ($isGlobalHcmAdmin)
                            <a href="{{ url('ai-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">AI Settings</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-9">
                <div class="card">
                    <div class="card-body">
                        <div class="border-bottom mb-3 pb-3">
                            <h4>Authentication Settings</h4>
                        </div>
                        <form id="auth-settings-form">
                            <div class="border-bottom mb-3">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="row row-gap-2 mb-3">
                                            <div class="col-md-6">
                                                <h6 class="fw-medium">Allow Registration</h6>
                                            </div>
                                            <div class="col-md-6 d-flex align-items-center">
                                                <div class="form-check form-switch me-2">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="auth-allow-registration" data-auth="allow_registration">
                                                </div>  
                                                <div class="form-check form-check-md">
                                                    <input class="form-check-input" type="checkbox" id="checkebox-md" data-auth="invite_only">
                                                    <label class="form-check-label" for="checkebox-md">
                                                        Invite Only
                                                    </label>
                                                </div>                                              
                                            </div>
                                        </div>
                                        <div class="row row-gap-2 mb-3">
                                            <div class="col-md-6">
                                                <h6 class="fw-medium">Verification Required</h6>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="auth-verification-required" data-auth="verification_required">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row row-gap-2 mb-3">
                                            <div class="col-md-6 d-flex">
                                                <div class="d-flex align-items-center">
                                                    <h6 class="fw-medium">Verification Expired</h6>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" id="auth-verification-expiry" data-auth="verification_expiry" placeholder="e.g. 24h">
                                            </div>
                                        </div>
                                        <div class="row row-gap-2 mb-3">
                                            <div class="col-md-6">
                                                <h6 class="fw-medium">Referral System</h6>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="auth-referral-system" data-auth="referral_system">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row row-gap-2 mb-3">
                                            <div class="col-md-6 d-flex">
                                                <div class="d-flex align-items-center">
                                                    <h6 class="fw-medium">Login Type</h6>
                                                </div>
                                            </div>
                                            <div class="col-md-6 d-flex align-items-center">
                                                <div class="form-check me-2">
                                                    <input class="form-check-input" type="radio" name="auth_login_type" id="Radio-sm" value="mobile" data-auth-radio="login_type">
                                                    <label class="form-check-label" for="Radio-sm">
                                                        Mobile
                                                    </label>
                                                </div>
                                                <div class="form-check me-2">
                                                    <input class="form-check-input" type="radio" name="auth_login_type" id="Radio-smone" value="email" data-auth-radio="login_type">
                                                    <label class="form-check-label" for="Radio-smone">
                                                        Email
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row row-gap-2 mb-3">
                                            <div class="col-md-6">
                                                <h6 class="fw-medium">Password Strength Check</h6>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="auth-password-strength" data-auth="password_strength">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row row-gap-2 mb-3">
                                            <div class="col-md-6">
                                                <h6 class="fw-medium">OTP System</h6>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" role="switch" id="auth-otp-system" data-auth="otp_system">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row row-gap-2 mb-3">
                                            <div class="col-md-6 d-flex">
                                                <div class="d-flex align-items-center">
                                                    <h6 class="fw-medium">OTP Type</h6>
                                                </div>
                                            </div>
                                            <div class="col-md-6 d-flex align-items-center">
                                                <div class="form-check me-2">
                                                    <input class="form-check-input" type="radio" name="auth_otp_type" id="Radio-smtwo" value="sms" data-auth-radio="otp_type">
                                                    <label class="form-check-label" for="Radio-smtwo">
                                                        SMS OTP
                                                    </label>
                                                </div>
                                                <div class="form-check me-2">
                                                    <input class="form-check-input" type="radio" name="auth_otp_type" id="Radio-smthree" value="email" data-auth-radio="otp_type">
                                                    <label class="form-check-label" for="Radio-smthree">
                                                        Email OTP
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>                                    
                                </div>
                            </div>
                            <div id="auth-settings-feedback" class="alert mt-3" style="display:none;"></div>
                            <div class="d-flex align-items-center justify-content-end">
                                <button type="button" class="btn btn-outline-light border me-3" id="auth-settings-cancel">Cancel</button>
                                <button type="submit" class="btn btn-primary" id="auth-settings-save">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
<!-- /Page Wrapper -->

<script>
(function () {
    var GROUP = 'authentication';
    var API_BASE = '/v1/hcm';

    function getToken() {
        if (window.AuthApi && typeof window.AuthApi.getToken === 'function') {
            var t = window.AuthApi.getToken(); if (t) return t;
        }
        return localStorage.getItem('arcav_access_token') || sessionStorage.getItem('arcav_access_token') ||
               localStorage.getItem('token') || sessionStorage.getItem('token') ||
               ((document.querySelector('meta[name="api-token"]') || {}).content) || null;
    }

    function buildHeaders() {
        var h = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
        var token = getToken(); if (token) h['Authorization'] = 'Bearer ' + token;
        var csrf = document.querySelector('meta[name="csrf-token"]'); if (csrf) h['X-CSRF-TOKEN'] = csrf.content;
        try {
            var ctx = JSON.parse(localStorage.getItem('arcav_active_tenant') || '{}');
            if (ctx.companyId) h['X-Company-Id'] = String(ctx.companyId);
            if (ctx.companyCode) h['X-Company-Code'] = String(ctx.companyCode);
        } catch(_) {}
        return h;
    }

    function showFeedback(msg, type) {
        var el = document.getElementById('auth-settings-feedback');
        if (!el) return;
        el.textContent = msg;
        el.className = 'alert alert-' + (type || 'success');
        el.style.display = 'block';
        setTimeout(function () { el.style.display = 'none'; }, 4000);
    }

    function applySettings(s) {
        document.querySelectorAll('[data-auth]').forEach(function (el) {
            var key = GROUP + '_' + el.dataset.auth;
            if (s[key] === undefined || s[key] === null) return;
            if (el.type === 'checkbox') { el.checked = s[key] === '1' || s[key] === true || s[key] === 'true'; }
            else { el.value = s[key]; }
        });
        document.querySelectorAll('[data-auth-radio]').forEach(function (el) {
            var key = GROUP + '_' + el.dataset.authRadio;
            if (s[key] !== undefined) el.checked = (el.value === s[key]);
        });
    }

    function loadSettings() {
        fetch(API_BASE + '/settings?group=' + GROUP, { headers: buildHeaders() })
            .then(function (r) { return r.json(); })
            .then(function (data) { if (data.success) applySettings(data.data || {}); })
            .catch(function () {});
    }

    function saveSettings(e) {
        if (e) e.preventDefault();
        var settings = {};
        document.querySelectorAll('[data-auth]').forEach(function (el) {
            settings[el.dataset.auth] = el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value;
        });
        document.querySelectorAll('[data-auth-radio]').forEach(function (el) {
            if (el.checked) settings[el.dataset.authRadio] = el.value;
        });
        fetch(API_BASE + '/settings', {
            method: 'POST', headers: buildHeaders(),
            body: JSON.stringify({ group: GROUP, settings: settings })
        }).then(function (r) { return r.json(); })
          .then(function (data) {
              if (data.success) showFeedback('Authentication settings saved.', 'success');
              else showFeedback((data.error && data.error.message) || 'Failed to save.', 'danger');
          }).catch(function () { showFeedback('Connection error.', 'danger'); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        loadSettings();
        var form = document.getElementById('auth-settings-form');
        if (form) form.addEventListener('submit', saveSettings);
        var saveBtn = document.getElementById('auth-settings-save');
        if (saveBtn) saveBtn.addEventListener('click', saveSettings);
        var cancelBtn = document.getElementById('auth-settings-cancel');
        if (cancelBtn) cancelBtn.addEventListener('click', function () { loadSettings(); });
    });
})();
</script>

@endsection