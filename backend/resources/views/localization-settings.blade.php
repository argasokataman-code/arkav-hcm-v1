<?php $page = 'localization-settings'; ?>
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
                            <a href="{{ url('localization-settings') }}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>Localization</a>
                            <a href="{{ url('prefixes') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Prefixes</a>
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
$(document).ready(function() {
    const apiBaseUrl = '/api/v1/hcm';

    function getAuthToken() {
        return localStorage.getItem('token') ||
               sessionStorage.getItem('token') ||
               $('meta[name="api-token"]').attr('content') ||
               $('meta[name="auth-token"]').attr('content') ||
               null;
    }

    function getHeaders() {
        const token = getAuthToken();
        return {
            'Authorization': token ? `Bearer ${token}` : '',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        };
    }
    
    // Load existing localization settings on page load
    loadLocalizationSettings();
    
    function loadLocalizationSettings() {
        $.ajax({
            url: `${apiBaseUrl}/settings?group=localization`,
            type: 'GET',
            headers: getHeaders(),
            success: function(response) {
                if (response.success && response.data) {
                    const settings = response.data;
                    console.log('✓ Loaded localization settings:', settings);
                    // Populate form fields with existing values  
                    $('[data-locale]').each(function() {
                        const $field = $(this);
                        const key = $field.data('locale');
                        const settingKey = `localization_${key}`;
                        const legacyKey = `locale_${key}`;
                        const storedValue = settings[settingKey] ?? settings[legacyKey];
                        if (storedValue !== undefined && storedValue !== null && storedValue !== '') {
                            if ($field.is(':checkbox')) {
                                $field.prop('checked', storedValue === true || storedValue === '1' || storedValue === 'true');
                            } else {
                                $field.val(storedValue);
                            }
                        }
                    });
                }
            },
            error: function(err) {
                console.warn('⚠ Could not load localization settings, using empty defaults', err);
            }
        });
    }
    
    // Handle form submission
    $('#localizationForm').on('submit', function(e) {
        e.preventDefault();

        const token = getAuthToken();
        if (!token) {
            alert('ERROR: Authentication token not found. Please refresh and login again.');
            return;
        }
        
        const localeData = {};
        let hasData = false;
        $('[data-locale]').each(function() {
            const $field = $(this);
            const key = $field.data('locale');
            if ($field.is(':checkbox')) {
                localeData[key] = $field.is(':checked');
                hasData = true;
            } else {
                const value = ($field.val() || '').toString().trim();
                if (value !== '') {
                    localeData[key] = value;
                    hasData = true;
                }
            }
        });

        if (!hasData) {
            alert('WARNING: Please fill at least one field before saving.');
            return;
        }
        
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Saving...');
        console.log('→ Sending localization settings:', localeData);
        
        $.ajax({
            url: `${apiBaseUrl}/settings`,
            type: 'POST',
            headers: getHeaders(),
            dataType: 'json',
            data: JSON.stringify({
                group: 'localization',
                settings: localeData
            }),
            success: function(response) {
                if (response.success) {
                    alert(response.message || 'Localization settings saved successfully.');
                    submitBtn.prop('disabled', false).text(originalText);
                } else {
                    throw new Error(response.message || 'Unknown save error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('✗ Error saving localization settings:', {
                    status: jqXHR.status,
                    response: jqXHR.responseJSON || jqXHR.responseText,
                    textStatus,
                    errorThrown
                });
                let errorMsg = 'Error saving localization settings.';
                if (jqXHR.status === 401 || jqXHR.status === 403) {
                    errorMsg = 'ERROR: Not authorized to update settings.';
                } else if (jqXHR.status === 422) {
                    errorMsg = 'ERROR: Validation failed. Please check your input.';
                } else if (jqXHR.responseJSON?.message) {
                    errorMsg = `ERROR: ${jqXHR.responseJSON.message}`;
                }
                alert(errorMsg);
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script>

@endsection