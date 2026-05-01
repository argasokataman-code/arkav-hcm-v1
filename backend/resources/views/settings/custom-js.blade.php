<?php $page = 'custom-js'; ?>
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
                <a class="nav-link" href="{{ url('profile-settings') }}"><i class="ti ti-settings me-2"></i>General Settings</a>
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
                <a class="nav-link active" href="{{ url('custom-css') }}"><i class="ti ti-settings-2 me-2"></i>Other Settings</a>
            </li>
        </ul>
        <div class="row">
            <div class="col-xl-3 theiaStickySidebar">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-column list-group settings-list">
                            <a href="{{ url('custom-css') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Custom CSS</a>
                            <a href="{{ url('custom-js') }}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>Custom JS</a>
                            <a href="{{ url('cronjob') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Cronjob</a>
                            <a href="{{ url('storage-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Storage</a>
                            <a href="{{ url('ban-ip-address') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Ban IP Address</a>
                            <a href="{{ url('backup') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Backup</a>
                            <a href="{{ url('clear-cache') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Clear Cache</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-9">
                <div class="card">
                    <div class="card-header px-0 mx-3">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-6 col-sm-4">
                                <h4>Custom JS</h4>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="mb-3">Write Custom JS</h5>
                        <textarea id="custom-js-input" data-custom-js="custom_js" class="form-control font-monospace" rows="14" placeholder="// Write your custom JavaScript here"></textarea>
                        <div id="custom-js-feedback" class="alert mt-3" style="display:none;"></div>
                        <div class="d-flex align-items-center justify-content-end border-top mt-3 pt-3">
                            <button type="button" class="btn btn-outline-light border me-3" id="custom-js-cancel">Cancel</button>
                            <button type="button" class="btn btn-primary" id="custom-js-save">Save</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
<!-- /Page Wrapper -->

<script>
(function () {
    var GROUP = 'custom_code';
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
        var el = document.getElementById('custom-js-feedback');
        if (!el) return;
        el.textContent = msg;
        el.className = 'alert alert-' + (type || 'success');
        el.style.display = 'block';
        setTimeout(function () { el.style.display = 'none'; }, 4000);
    }

    function loadSettings() {
        fetch(API_BASE + '/settings?group=' + GROUP, { headers: buildHeaders() })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;
                var s = data.data || {};
                var ta = document.getElementById('custom-js-input');
                if (ta && s.custom_code_custom_js !== undefined) ta.value = s.custom_code_custom_js || '';
            }).catch(function () {});
    }

    function saveSettings() {
        var ta = document.getElementById('custom-js-input');
        fetch(API_BASE + '/settings', {
            method: 'POST', headers: buildHeaders(),
            body: JSON.stringify({ group: GROUP, settings: { custom_js: ta ? ta.value : '' } })
        }).then(function (r) { return r.json(); })
          .then(function (data) {
              if (data.success) showFeedback('Custom JS saved.', 'success');
              else showFeedback((data.error && data.error.message) || 'Failed to save.', 'danger');
          }).catch(function () { showFeedback('Connection error.', 'danger'); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        loadSettings();
        var saveBtn = document.getElementById('custom-js-save');
        if (saveBtn) saveBtn.addEventListener('click', saveSettings);
        var cancelBtn = document.getElementById('custom-js-cancel');
        if (cancelBtn) cancelBtn.addEventListener('click', function () { loadSettings(); });
    });
})();
</script>

@endsection