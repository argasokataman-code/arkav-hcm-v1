<?php $page = 'seo-settings'; ?>
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
                            <a href="{{ url('seo-settings') }}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>SEO Settings</a>
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
                            <a href="{{ url('authentication-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Authentication Settings</a>
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
                            <h4>SEO Settings</h4>
                        </div>
                        <form id="seo-settings-form">
                            <div class="border-bottom mb-3">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-xxl-2 col-md-3">
                                                <label class="form-label mb-md-0">Meta Title</label>
                                            </div>
                                            <div class="col-xxl-10 col-md-9">
                                                <input type="text" class="form-control" data-seo="meta_title">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-xxl-2 col-md-3">
                                                <label class="form-label mb-md-0">Meta Keywords</label>
                                            </div>
                                            <div class="col-xxl-10 col-md-9">
                                                <input type="text" class="form-control" data-seo="meta_keywords">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="row align-items-start mb-3">
                                            <div class="col-xxl-2 col-md-3">
                                                <label class="form-label mb-md-0">Meta Description</label>
                                            </div>
                                            <div class="col-xxl-10 col-md-9">
                                                <textarea class="form-control" rows="3" data-seo="meta_description"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-xxl-2 col-md-3">
                                                <label class="form-label mb-md-0">Meta Robot</label>
                                            </div>
                                            <div class="col-xxl-10 col-md-9">
                                                <input type="text" class="form-control" data-seo="meta_robot">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-xxl-2 col-md-3">
                                                <label class="form-label mb-md-0">Canonical Url</label>
                                            </div>
                                            <div class="col-xxl-10 col-md-9">
                                                <input type="text" class="form-control" data-seo="canonical_url">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-xxl-2 col-md-3">
                                                <label class="form-label mb-md-0">Custom Url</label>
                                            </div>
                                            <div class="col-xxl-10 col-md-9">
                                                <input type="text" class="form-control" data-seo="custom_url">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-xxl-2 col-md-3">
                                                <label class="form-label mb-md-0">Og Title</label>
                                            </div>
                                            <div class="col-xxl-10 col-md-9">
                                                <input type="text" class="form-control" data-seo="og_title">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="row align-items-start mb-3">
                                            <div class="col-xxl-2 col-md-3">
                                                <label class="form-label mb-md-0">Og Description</label>
                                            </div>
                                            <div class="col-xxl-10 col-md-9">
                                                <textarea class="form-control" rows="3" data-seo="og_description"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12 d-flex align-items-start">
                                        <div class="col-xxl-2 col-md-3">
                                            <label class="form-label mb-md-0">Og Image</label>
                                        </div>
                                        <div class="col-xxl-10 col-md-9">
                                            <div class="d-flex align-items-center flex-wrap row-gap-3 bg-light w-100 rounded p-3 mb-4">                                                
                                                <div class="d-flex align-items-center justify-content-center avatar avatar-xxl rounded-circle border border-dashed me-2 flex-shrink-0 text-dark frames">
                                                    <i class="ti ti-photo text-gray-3 fs-16"></i>
                                                </div>                                              
                                                <div class="profile-upload">
                                                    <div class="mb-2">
                                                        <h6 class="mb-1">OG Image</h6>
                                                        <p class="fs-12">Recommended image size is 40px x 40px</p>
                                                    </div>
                                                    <div class="profile-uploader d-flex align-items-center">
                                                        <div class="drag-upload-btn btn btn-sm btn-primary me-2">
                                                            Upload
                                                            <input type="file" class="form-control image-sign" multiple="">
                                                        </div>
                                                        <a href="javascript:void(0);" class="btn btn-light btn-sm">Cancel</a>
                                                    </div>
                                                    
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="seo-settings-feedback" class="alert mt-3" style="display:none;"></div>
                            <div class="d-flex align-items-center justify-content-end">
                                <button type="button" class="btn btn-outline-light border me-3" id="seo-settings-cancel">Cancel</button>
                                <button type="submit" class="btn btn-primary" id="seo-settings-save">Save</button>
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
    var GROUP = 'seo';
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
        var el = document.getElementById('seo-settings-feedback');
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
                document.querySelectorAll('[data-seo]').forEach(function (el) {
                    var key = GROUP + '_' + el.dataset.seo;
                    if (s[key] !== undefined && s[key] !== null) el.value = s[key];
                });
            }).catch(function () {});
    }

    function saveSettings(e) {
        if (e) e.preventDefault();
        var settings = {};
        document.querySelectorAll('[data-seo]').forEach(function (el) {
            settings[el.dataset.seo] = el.value;
        });
        fetch(API_BASE + '/settings', {
            method: 'POST', headers: buildHeaders(),
            body: JSON.stringify({ group: GROUP, settings: settings })
        }).then(function (r) { return r.json(); })
          .then(function (data) {
              if (data.success) showFeedback('SEO settings saved.', 'success');
              else showFeedback((data.error && data.error.message) || 'Failed to save.', 'danger');
          }).catch(function () { showFeedback('Connection error.', 'danger'); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        loadSettings();
        var form = document.getElementById('seo-settings-form');
        if (form) form.addEventListener('submit', saveSettings);
        var saveBtn = document.getElementById('seo-settings-save');
        if (saveBtn) saveBtn.addEventListener('click', saveSettings);
        var cancelBtn = document.getElementById('seo-settings-cancel');
        if (cancelBtn) cancelBtn.addEventListener('click', function () { loadSettings(); });
    });
})();
</script>

@endsection