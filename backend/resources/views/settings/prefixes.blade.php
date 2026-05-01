<?php $page = 'prefixes'; ?>
@extends('layout.mainlayout')
@section('content')
@php
    $prefixSettings = \App\Support\WebsiteSettings::allPrefixSettings();
@endphp

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
            <li class="nav-item">
                <a class="nav-link active" href="{{ url('business-settings') }}"><i class="ti ti-world-cog me-2"></i>Website Settings</a>
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
                            <a href="{{ url('business-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Business Settings</a>
                            <a href="{{ url('seo-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">SEO Settings</a>
                            <a href="{{ url('localization-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Localization</a>
                            <a href="{{ url('prefixes') }}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>Prefixes</a>
                            <a href="{{ url('preferences') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Preferences</a>
                            <a href="{{ url('appearance') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Appearance</a>
                            <a href="{{ url('language') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Language</a>
                            <a href="{{ url('authentication-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Authentication Settings</a>
                            <a href="{{ url('ai-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">AI Settings</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-9">
                <div class="card">
                    <div class="card-body">
                        <div class="border-bottom mb-3 pb-3">
                            <h4>Prefixes</h4>
                        </div>
                        <form id="prefixesForm">
                            <div class="border-bottom mb-3">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label mb-md-0">Employee</label>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" data-prefix="employee" value="{{ old('prefix_employee', $prefixSettings['prefix_employee'] ?? '') }}" placeholder="e.g., Emp-">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label mb-md-0">Clients</label>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" data-prefix="clients" value="{{ old('prefix_clients', $prefixSettings['prefix_clients'] ?? '') }}" placeholder="e.g., Cli-">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label mb-md-0">Invoice</label>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" data-prefix="invoice" value="{{ old('prefix_invoice', $prefixSettings['prefix_invoice'] ?? '') }}" placeholder="e.g., Inv-">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label mb-md-0">Tickets</label>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" data-prefix="tickets" value="{{ old('prefix_tickets', $prefixSettings['prefix_tickets'] ?? '') }}" placeholder="e.g., Tic-">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label mb-md-0">Candidate</label>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" data-prefix="candidate" value="{{ old('prefix_candidate', $prefixSettings['prefix_candidate'] ?? '') }}" placeholder="e.g., Cand-">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label mb-md-0">Job</label>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" data-prefix="job" value="{{ old('prefix_job', $prefixSettings['prefix_job'] ?? '') }}" placeholder="e.g., Job-">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label mb-md-0">Referral</label>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" data-prefix="referral" value="{{ old('prefix_referral', $prefixSettings['prefix_referral'] ?? '') }}" placeholder="e.g., Ref-">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label mb-md-0">Contract</label>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" data-prefix="contract" value="{{ old('prefix_contract', $prefixSettings['prefix_contract'] ?? '') }}" placeholder="e.g., Cont-">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label mb-md-0">Department</label>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" data-prefix="department" value="{{ old('prefix_department', $prefixSettings['prefix_department'] ?? '') }}" placeholder="e.g., Dept-">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label mb-md-0">Leave Type</label>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" data-prefix="leave" value="{{ old('prefix_leave', $prefixSettings['prefix_leave'] ?? '') }}" placeholder="e.g., Leave-">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label mb-md-0">Assets</label>
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" data-prefix="assets" value="{{ old('prefix_assets', $prefixSettings['prefix_assets'] ?? '') }}" placeholder="e.g., Ast-">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-end">
                                <button type="button" class="btn btn-outline-light border me-3" onclick="location.reload()">Cancel</button>
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

<script>
(function () {
    const apiBaseUrl = '/v1/hcm';

    function getAuthToken() {
        if (window.AuthApi && typeof window.AuthApi.getToken === 'function') {
            const tokenFromApi = window.AuthApi.getToken();
            if (tokenFromApi) {
                return tokenFromApi;
            }
        }

        return localStorage.getItem('arcav_access_token') ||
            sessionStorage.getItem('arcav_access_token') ||
            localStorage.getItem('token') ||
            sessionStorage.getItem('token') ||
            (document.querySelector('meta[name="api-token"]') || {}).content ||
            (document.querySelector('meta[name="auth-token"]') || {}).content ||
            null;
    }

    function getHeaders() {
        const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        };

        const token = getAuthToken();
        if (token) {
            headers.Authorization = `Bearer ${token}`;
        }

        const csrf = document.querySelector('meta[name="csrf-token"]');
        if (csrf && csrf.content) {
            headers['X-CSRF-TOKEN'] = csrf.content;
        }

        try {
            const tenant = JSON.parse(localStorage.getItem('arcav_active_tenant') || '{}');
            if (tenant.companyId) {
                headers['X-Company-Id'] = String(tenant.companyId);
            }
            if (tenant.companyCode) {
                headers['X-Company-Code'] = String(tenant.companyCode);
            }
        } catch (_) {
            // Ignore malformed tenant payload.
        }

        return headers;
    }

    function applyPrefixSettings(settings) {
        document.querySelectorAll('[data-prefix]').forEach(function (field) {
            const key = field.dataset.prefix;
            const settingKey = `prefix_${key}`;
            const storedValue = settings[settingKey];
            if (storedValue !== undefined && storedValue !== null && String(storedValue).trim() !== '') {
                field.value = String(storedValue);
            }
        });
    }

    function collectPrefixSettings() {
        const payload = {};
        let hasData = false;

        document.querySelectorAll('[data-prefix]').forEach(function (field) {
            const key = field.dataset.prefix;
            const value = (field.value || '').toString().trim();
            if (value !== '') {
                payload[key] = value;
                hasData = true;
            }
        });

        return { payload, hasData };
    }

    async function loadPrefixes() {
        try {
            const response = await fetch(`${apiBaseUrl}/settings?group=prefix`, {
                method: 'GET',
                headers: getHeaders(),
            });
            const body = await response.json();
            if (body.success && body.data) {
                applyPrefixSettings(body.data);
            }
        } catch (err) {
            console.warn('Could not load prefixes, using current defaults', err);
        }
    }

    async function submitPrefixes(event) {
        event.preventDefault();

        const token = getAuthToken();
        if (!token) {
            alert('ERROR: Authentication token not found. Please refresh the page and try again.');
            return;
        }

        const { payload, hasData } = collectPrefixSettings();
        if (!hasData) {
            alert('WARNING: No prefix values entered. Please fill in at least one field.');
            return;
        }

        const form = event.currentTarget;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : 'Save';

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
        }

        try {
            const response = await fetch(`${apiBaseUrl}/settings`, {
                method: 'POST',
                headers: getHeaders(),
                body: JSON.stringify({
                    group: 'prefix',
                    settings: payload,
                }),
            });
            const body = await response.json();

            if (!response.ok || !body.success) {
                if (response.status === 401 || response.status === 403) {
                    throw new Error('ERROR: Not authorized. Please check your permissions.');
                }
                if (response.status === 422) {
                    throw new Error('ERROR: Invalid data. Please check your input.');
                }
                throw new Error(body.message || body.error?.message || 'Error saving prefixes.');
            }

            alert(body.message || 'Prefixes saved successfully!');
            await loadPrefixes();
        } catch (err) {
            alert(err.message || 'Error saving prefixes.');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText || 'Save';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        loadPrefixes();
        const form = document.getElementById('prefixesForm');
        if (form) {
            form.addEventListener('submit', submitPrefixes);
        }
    });
})();
</script>

@endsection