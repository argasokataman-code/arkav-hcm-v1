<?php $page = 'localization-settings'; ?>
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
                            <a href="{{ url('business-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Business Settings</a>
                            @endif
                            @if ($isGlobalHcmAdmin)
                            <a href="{{ url('seo-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">SEO Settings</a>
                            @endif
                            @if ($isGlobalHcmAdmin)
                            <a href="{{ url('localization-settings') }}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>Localization</a>
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
                            <h4>Localization</h4>
                        </div>
                        <form id="localizationForm">
                            <div class="border-bottom mb-3">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-5">
                                                <label class="form-label mb-md-0">Language</label>
                                            </div>
                                            <div class="col-md-7">
                                                <select class="select" data-locale="language">
                                                    <option value="">Select</option>
                                                    <option value="en">English</option>
                                                    <option value="fr">French</option>
                                                    <option value="es">Spanish</option>
                                                    <option value="id">Indonesian</option>
                                                </select>
                                                <p class="fs-13 fw-normal mt-2 form-check form-check-md form-switch me-2">
                                                    <input class="form-check-input me-2" type="checkbox" role="switch" data-locale="language_switcher">
                                                    <label class="form-check-label">Language Switcher</label></p>
                                            </div>													
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-5">
                                                <label class="form-label mb-md-0">Timezone</label>
                                            </div>
                                            <div class="col-md-7">
                                                <select class="select" data-locale="timezone">
                                                    <option value="">Select</option>
                                                    <option value="Asia/Jakarta">Asia/Jakarta (UTC+7)</option>
                                                    <option value="Asia/Bangkok">Asia/Bangkok (UTC+7)</option>
                                                    <option value="Asia/Singapore">Asia/Singapore (UTC+8)</option>
                                                    <option value="Asia/Kolkata">Asia/Kolkata (UTC+5:30)</option>
                                                    <option value="America/New_York">America/New_York (UTC-5)</option>
                                                    <option value="Europe/London">Europe/London (UTC+0)</option>
                                                </select>
                                            </div>													
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-5">
                                                <label class="form-label mb-md-0">Date Format</label>
                                            </div>
                                            <div class="col-md-7">
                                                <select class="select" data-locale="date_format">
                                                    <option value="">Select</option>
                                                    <option value="d M Y">15 Nov 2024</option>
                                                    <option value="M d Y">Nov 15 2024</option>
                                                    <option value="Y-m-d">2024-11-15</option>
                                                    <option value="d/m/Y">15/11/2024</option>
                                                </select>
                                            </div>													
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-5">
                                                <label class="form-label mb-md-0">Time Format</label>
                                            </div>
                                            <div class="col-md-7">
                                                <select class="select" data-locale="time_format">
                                                    <option value="">Select</option>
                                                    <option value="12">12 Hours</option>
                                                    <option value="24">24 Hours</option>
                                                </select>
                                            </div>													
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-5">
                                                <label class="form-label mb-md-0">Financial Year</label>
                                            </div>
                                            <div class="col-md-7">
                                                <select class="select" data-locale="financial_year">
                                                    <option value="">Select</option>
                                                    <option value="2026">2026</option>
                                                    <option value="2025">2025</option>
                                                    <option value="2024">2024</option>
                                                </select>
                                            </div>													
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-5">
                                                <label class="form-label mb-md-0">Starting Month</label>
                                            </div>
                                            <div class="col-md-7">
                                                <select class="select" data-locale="fy_start_month">
                                                    <option value="">Select</option>
                                                    <option value="1">January</option>
                                                    <option value="2">February</option>
                                                    <option value="3">March</option>
                                                </select>
                                            </div>													
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="border-bottom mb-3">
                                <h6 class="mb-3">Currency Information</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-5">
                                                <label class="form-label mb-md-0">Currency</label>
                                            </div>
                                            <div class="col-md-7">
                                                <select class="select" data-locale="currency_code">
                                                    <option value="">Select</option>
                                                    <option value="IDR">Indonesian Rupiah (IDR)</option>
                                                    <option value="USD">US Dollar (USD)</option>
                                                    <option value="SGD">Singapore Dollar (SGD)</option>
                                                </select>
                                            </div>													
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-5">
                                                <label class="form-label mb-md-0">Currency Symbol</label>
                                            </div>
                                            <div class="col-md-7">
                                                <select class="select" data-locale="currency_symbol">
                                                    <option value="">Select</option>
                                                    <option value="Rp">Rp</option>
                                                    <option value="$">$</option>
                                                    <option value="S$">S$</option>
                                                </select>
                                            </div>													
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-5">
                                                <label class="form-label mb-md-0">Currency Position</label>
                                            </div>
                                            <div class="col-md-7">
                                                <select class="select" data-locale="currency_position">
                                                    <option value="">Select</option>
                                                    <option value="suffix">100 $</option>
                                                    <option value="prefix">$ 100</option>
                                                </select>
                                            </div>													
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-5">
                                                <label class="form-label mb-md-0">Decimal Seperator</label>
                                            </div>
                                            <div class="col-md-7">
                                                <select class="select" data-locale="decimal_separator">
                                                    <option value="">Select</option>
                                                    <option value=".">.</option>
                                                    <option value=",">,</option>
                                                </select>
                                            </div>													
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-5">
                                                <label class="form-label mb-md-0">Thousand Seperator</label>
                                            </div>
                                            <div class="col-md-7">
                                                <select class="select" data-locale="thousand_separator">
                                                    <option value="">Select</option>
                                                    <option value=",">,</option>
                                                    <option value=".">.</option>
                                                </select>
                                            </div>													
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="border-bottom mb-3">
                                <h6 class="mb-3">Country Settings</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-5">
                                                <label class="form-label mb-md-0">Countries Restriction</label>
                                            </div>
                                            <div class="col-md-7">
                                                <select class="select" data-locale="countries_restriction">
                                                    <option value="">Select</option>
                                                    <option value="allow_all">Allow All Countries</option>
                                                    <option value="deny_all">Deny All Countries</option>
                                                </select>
                                            </div>													
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="border-bottom mb-3">
                                <h6 class="mb-3">File Settings</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-5">
                                                <label class="form-label mb-md-0">Allowed Files</label>
                                            </div>
                                            <div class="col-md-7">
                                                <select class="select" data-locale="allowed_files">
                                                    <option value="">Select</option>
                                                    <option value="jpg,jpeg,png">jpg, jpeg, png</option>
                                                    <option value="jpg,jpeg,png,gif">jpg, jpeg, png, gif</option>
                                                    <option value="pdf,doc,docx,xls,xlsx">pdf, doc, docx, xls, xlsx</option>
                                                </select>
                                            </div>													
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="row align-items-center mb-3">
                                            <div class="col-md-5">
                                                <label class="form-label mb-md-0">Max File Size</label>
                                            </div>
                                            <div class="col-md-7">
                                                <input type="number" class="form-control" data-locale="max_file_size_mb" placeholder="5000">
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

    function applySettings(settings) {
        document.querySelectorAll('[data-locale]').forEach(function (field) {
            const key = field.dataset.locale;
            const settingKey = `localization_${key}`;
            const legacyKey = `locale_${key}`;
            const storedValue = settings[settingKey] ?? settings[legacyKey];

            if (storedValue === undefined || storedValue === null || storedValue === '') {
                return;
            }

            if (field.type === 'checkbox') {
                field.checked = storedValue === true || storedValue === '1' || storedValue === 'true';
                return;
            }

            const nextValue = String(storedValue);

            if (field.tagName === 'SELECT') {
                const hasOption = Array.from(field.options).some(function (opt) {
                    return opt.value === nextValue;
                });

                if (!hasOption) {
                    const fallbackOption = document.createElement('option');
                    fallbackOption.value = nextValue;
                    fallbackOption.textContent = nextValue;
                    field.appendChild(fallbackOption);
                }
            }

            field.value = nextValue;
            field.dispatchEvent(new Event('change', { bubbles: true }));

            if (window.jQuery) {
                window.jQuery(field).trigger('change');
            }
        });
    }

    function collectSettings() {
        const settings = {};
        let hasData = false;

        document.querySelectorAll('[data-locale]').forEach(function (field) {
            const key = field.dataset.locale;
            if (field.type === 'checkbox') {
                settings[key] = field.checked;
                hasData = true;
                return;
            }

            const value = (field.value || '').toString().trim();
            if (value !== '') {
                settings[key] = value;
                hasData = true;
            }
        });

        return { settings, hasData };
    }

    async function loadLocalizationSettings() {
        try {
            const response = await fetch(`${apiBaseUrl}/settings?group=localization`, {
                method: 'GET',
                headers: getHeaders(),
            });
            const payload = await response.json();
            if (payload.success && payload.data) {
                applySettings(payload.data);
            }
        } catch (err) {
            console.warn('Could not load localization settings, using empty defaults', err);
        }
    }

    async function submitLocalizationSettings(event) {
        event.preventDefault();

        if (!getAuthToken()) {
            alert('ERROR: Authentication token not found. Please refresh and login again.');
            return;
        }

        const { settings, hasData } = collectSettings();
        if (!hasData) {
            alert('WARNING: Please fill at least one field before saving.');
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
                    group: 'localization',
                    settings: settings,
                }),
            });

            const payload = await response.json();
            if (!response.ok || !payload.success) {
                if (response.status === 401 || response.status === 403) {
                    throw new Error('ERROR: Not authorized to update settings.');
                }
                if (response.status === 422) {
                    throw new Error('ERROR: Validation failed. Please check your input.');
                }
                throw new Error(payload.message || payload.error?.message || 'Error saving localization settings.');
            }

            alert(payload.message || 'Localization settings saved successfully.');
        } catch (err) {
            alert(err.message || 'Error saving localization settings.');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText || 'Save';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        loadLocalizationSettings();
        setTimeout(loadLocalizationSettings, 200);
        const form = document.getElementById('localizationForm');
        if (form) {
            form.addEventListener('submit', submitLocalizationSettings);
        }
    });
})();
</script>

@endsection