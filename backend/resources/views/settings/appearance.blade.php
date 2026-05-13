<?php $page = 'appearance'; ?>
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
                    <a class="nav-link" href="{{url('profile-settings')}}"><i class="ti ti-settings me-2"></i>General Settings</a>
                </li>
                @if ($isGlobalHcmAdmin)

                <li class="nav-item">
                    @if ($isGlobalHcmAdmin)
                    <a class="nav-link active" href="{{url('business-settings')}}"><i class="ti ti-world-cog me-2"></i>Website Settings</a>
                    @endif
                </li>

                @endif
                <li class="nav-item">
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{url('email-settings')}}"><i class="ti ti-server-cog me-2"></i>System Settings</a>
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
                                @if ($isGlobalHcmAdmin)
                                <a href="{{url('business-settings')}}" class="d-inline-flex align-items-center rounded py-2 px-3">Business Settings</a>
                                @endif
                                @if ($isGlobalHcmAdmin)
                                <a href="{{url('seo-settings')}}" class="d-inline-flex align-items-center rounded py-2 px-3">SEO Settings</a>
                                @endif
                                @if ($isGlobalHcmAdmin)
                                <a href="{{url('localization-settings')}}" class="d-inline-flex align-items-center rounded py-2 px-3">Localization</a>
                                @endif
                                <a href="{{url('appearance')}}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>Appearance</a>
                                @if ($isGlobalHcmAdmin)
                                <a href="{{url('language')}}" class="d-inline-flex align-items-center rounded py-2 px-3">Language</a>
                                @endif
                                @if ($isGlobalHcmAdmin)
                                <a href="{{url('authentication-settings')}}" class="d-inline-flex align-items-center rounded py-2 px-3">Authentication Settings</a>
                                @endif
                                @if ($isGlobalHcmAdmin)
                                <a href="{{url('ai-settings')}}" class="d-inline-flex align-items-center rounded py-2 px-3">AI Settings</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-9">
                    <div class="card">
                        <div class="card-body">
                            <div class="border-bottom mb-3 pb-3">
                                <h4>Appearance</h4>
                            </div>
                            <form id="appearance-settings-form">
                                <div class="border-bottom mb-3">
                                    <input type="hidden" id="appearance-theme" data-appearance="theme" value="light">
                                    <input type="hidden" id="appearance-accent" data-appearance="accent_color" value="primary">
                                    <div class="row align-items-center">
                                        <div class="col-xl-3 col-lg-12 col-md-3">
                                            <div class="setting-info mb-4">
                                                <h6 class="fs-14 fw-medium">Select Theme</h6>
                                            </div>
                                        </div>
                                        <div class="col-xl-9 col-lg-12 col-md-9">
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <div class="card shadow-none" id="theme-card-light">
                                                        <div class="card-body" style="cursor:pointer;" onclick="setTheme('light')">
                                                            <div class="border rounded border-gray mb-2">
                                                                <img src="{{ URL::asset('build/img/theme/light.svg') }}" class="img-fluid rounded" alt="theme">
                                                            </div>
                                                            <p class="text-dark text-center">Light</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="me-3">
                                                    <div class="card shadow-none" id="theme-card-dark">
                                                        <div class="card-body" style="cursor:pointer;" onclick="setTheme('dark')">
                                                            <div class="border rounded border-gray mb-2">
                                                                <img src="{{ URL::asset('build/img/theme/dark.svg') }}" class="img-fluid rounded" alt="theme">
                                                            </div>
                                                            <p class="text-dark text-center">Dark</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="card shadow-none" id="theme-card-auto">
                                                        <div class="card-body" style="cursor:pointer;" onclick="setTheme('auto')">
                                                            <div class="border rounded border-gray mb-2">
                                                                <img src="{{ URL::asset('build/img/theme/automatic.svg') }}" class="img-fluid rounded" alt="theme">
                                                            </div>
                                                            <p class="text-dark text-center">Automatic</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row align-items-center">
                                        <div class="col-xl-3 col-lg-12 col-md-3">
                                            <div class="setting-info mb-4">
                                                <h6 class="fs-14 fw-medium">Accent Color</h6>
                                            </div>
                                        </div>
                                        <div class="col-xl-4 col-lg-12 col-md-4">
                                            <div class="theme-colors mb-4">
                                                <ul class="d-flex align-items-center">
                                                    <li>
                                                        <span class="themecolorset">
                                                            <span class="primecolor bg-primary">
                                                                <span class="colorcheck text-white"><i class="ti ti-check text-primary fs-10"></i></span>
                                                            </span>
                                                        </span>
                                                    </li>
                                                    <li>
                                                        <span class="themecolorset">
                                                            <span class="primecolor bg-secondary">
                                                                <span class="colorcheck text-white"><i class="ti ti-check text-primary fs-10"></i></span>
                                                            </span>
                                                        </span>
                                                    </li>
                                                    <li>
                                                        <span class="themecolorset">
                                                            <span class="primecolor bg-info">
                                                                <span class="colorcheck text-white"><i class="ti ti-check text-primary fs-10"></i></span>
                                                            </span>
                                                        </span>
                                                    </li>
                                                    <li>
                                                        <span class="themecolorset">
                                                            <span class="primecolor bg-purple">
                                                                <span class="colorcheck text-white"><i class="ti ti-check text-primary fs-10"></i></span>
                                                            </span>
                                                        </span>
                                                    </li>
                                                    <li>
                                                        <span class="themecolorset">
                                                            <span class="primecolor bg-pink">
                                                                <span class="colorcheck text-white"><i class="ti ti-check text-primary fs-10"></i></span>
                                                            </span>
                                                        </span>
                                                    </li>
                                                    <li>
                                                        <span class="themecolorset">
                                                            <span class="primecolor bg-warning">
                                                                <span class="colorcheck text-white"><i class="ti ti-check text-primary fs-10"></i></span>
                                                            </span>
                                                        </span>
                                                    </li>
                                                    <li>
                                                        <span class="themecolorset active">
                                                            <span class="primecolor bg-danger">
                                                                <span class="colorcheck text-white"><i class="ti ti-check text-primary fs-10"></i></span>
                                                            </span>
                                                        </span>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row align-items-center mb-4">
                                        <div class="col-xl-3 col-lg-12 col-md-3">
                                            <div class="">
                                                <h6 class="fs-14 fw-medium">Sidebar Size</h6>
                                            </div>
                                        </div>
                                        <div class="col-xl-3 col-lg-12 col-md-3">
                                            <select class="select" id="appearance-sidebar-size" data-appearance="sidebar_size">
                                                <option value="">Select</option>
                                                <option value="small">Small - 85px</option>
                                                <option value="large">Large - 250px</option>
                                            </select>                                        
                                        </div>
                                    </div>
                                    <div class="row align-items-center mb-3">
                                        <div class="col-xl-3 col-lg-12 col-md-3">
                                            <div class="">
                                                <h6 class="fs-14 fw-medium">Font Family</h6>
                                            </div>
                                        </div>
                                        <div class="col-xl-3 col-lg-12 col-md-3">
                                            <select class="select" id="appearance-font-family" data-appearance="font_family">
                                                <option value="">Select</option>
                                                <option value="Nunito">Nunito</option>
                                                <option value="Poppins">Poppins</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div id="appearance-settings-feedback" class="alert mt-3" style="display:none;"></div>
                                <div class="d-flex align-items-center justify-content-end">
                                    <button type="button" class="btn btn-outline-light border me-3" id="appearance-cancel">Cancel</button>
                                    <button type="submit" class="btn btn-primary" id="appearance-save">Save</button>
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
function setTheme(val) {
    document.getElementById('appearance-theme').value = val;
    ['light','dark','auto'].forEach(function(t) {
        var card = document.getElementById('theme-card-' + t);
        if (card) card.classList.toggle('border-primary', t === val);
    });
}

function setAccent(val) {
    document.getElementById('appearance-accent').value = val;
    document.querySelectorAll('.themecolorset').forEach(function(el) {
        el.classList.remove('active');
    });
    var active = document.querySelector('.themecolorset[data-accent="' + val + '"]');
    if (active) active.classList.add('active');
}

(function () {
    var GROUP = 'appearance';
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
        var el = document.getElementById('appearance-settings-feedback');
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
                document.querySelectorAll('[data-appearance]').forEach(function (el) {
                    var key = GROUP + '_' + el.dataset.appearance;
                    if (s[key] !== undefined && s[key] !== null) {
                        el.value = s[key];
                        if (el.tagName === 'SELECT') {
                            Array.from(el.options).forEach(function(o) { o.selected = o.value === s[key]; });
                        }
                    }
                });
                if (s.appearance_theme) setTheme(s.appearance_theme);
                if (s.appearance_accent_color) setAccent(s.appearance_accent_color);
            }).catch(function () {});
    }

    function saveSettings(e) {
        if (e) e.preventDefault();
        var settings = {};
        document.querySelectorAll('[data-appearance]').forEach(function (el) {
            settings[el.dataset.appearance] = el.value;
        });
        fetch(API_BASE + '/settings', {
            method: 'POST', headers: buildHeaders(),
            body: JSON.stringify({ group: GROUP, settings: settings })
        }).then(function (r) { return r.json(); })
          .then(function (data) {
              if (data.success) showFeedback('Appearance settings saved.', 'success');
              else showFeedback((data.error && data.error.message) || 'Failed to save.', 'danger');
          }).catch(function () { showFeedback('Connection error.', 'danger'); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        loadSettings();
        var form = document.getElementById('appearance-settings-form');
        if (form) form.addEventListener('submit', saveSettings);
        var saveBtn = document.getElementById('appearance-save');
        if (saveBtn) saveBtn.addEventListener('click', saveSettings);
        var cancelBtn = document.getElementById('appearance-cancel');
        if (cancelBtn) cancelBtn.addEventListener('click', function () { loadSettings(); });

        // Wire accent color clicks
        var colors = ['primary','secondary','info','purple','pink','warning','danger'];
        document.querySelectorAll('.themecolorset').forEach(function(el, i) {
            var color = colors[i] || 'primary';
            el.setAttribute('data-accent', color);
            el.style.cursor = 'pointer';
            el.addEventListener('click', function() { setAccent(color); });
        });
    });
})();
</script>

@endsection