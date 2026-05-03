<?php $page = 'business-settings'; ?>
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
                                @if ($isGlobalHcmAdmin)
                                <a href="{{url('business-settings')}}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>Business Settings</a>
                                @endif
                                @if ($isGlobalHcmAdmin)
                                <a href="{{url('seo-settings')}}" class="d-inline-flex align-items-center rounded py-2 px-3">SEO Settings</a>
                                @endif
                                @if ($isGlobalHcmAdmin)
                                <a href="{{url('localization-settings')}}" class="d-inline-flex align-items-center rounded py-2 px-3">Localization</a>
                                @endif
                                @if ($isGlobalHcmAdmin)
                                <a href="{{url('prefixes')}}" class="d-inline-flex align-items-center rounded py-2 px-3">Prefixes</a>
                                @endif
                                <a href="{{url('appearance')}}" class="d-inline-flex align-items-center rounded py-2 px-3">Appearance</a>
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
                                <h4>Business Settings</h4>
                            </div>
                            <form id="businessForm">
                                <div class="border-bottom mb-3">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div>					
                                                <h6 class="mb-3">Basic Information</h6>													
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="row align-items-center mb-3">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">Company Name</label>
                                                </div>
                                                <div class="col-md-8">
                                                    <input type="text" class="form-control" data-business="company_name">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row align-items-center mb-3">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">Email Address</label>
                                                </div>
                                                <div class="col-md-8">
                                                    <input type="text" class="form-control" data-business="email">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row align-items-center mb-3">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">Phone</label>
                                                </div>
                                                <div class="col-md-8">
                                                    <input type="text" class="form-control" data-business="phone">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row align-items-center mb-3">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">Fax</label>
                                                </div>
                                                <div class="col-md-8">
                                                    <input type="text" class="form-control" data-business="fax">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="row align-items-center mb-3">
                                                <div class="col-md-2">
                                                    <label class="form-label mb-md-0">Web</label>
                                                </div>
                                                <div class="col-md-10">
                                                    <input type="text" class="form-control" data-business="website">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="border-bottom mb-3">
                                    <h6 class="mb-3">Company Images</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center flex-wrap row-gap-3 bg-light w-100 rounded p-3 mb-4">                                                
                                                <div class="d-flex align-items-center justify-content-center avatar avatar-xxl bg-white rounded border border-dashed me-2 flex-shrink-0 text-dark frames px-2">
                                                    <img id="preview-white_logo" src="{{ URL::asset('build/img/image111.png') }}" class="img-fluid" alt="logo">
                                                </div>                                              
                                                <div class="profile-upload upload-pic">
                                                    <div class="mb-2">
                                                        <h6 class="mb-1">White Logo</h6>
                                                        <p class="fs-12">Recommended image size is 160px x 50px</p>
                                                    </div>
                                                    <div class="profile-uploader d-flex align-items-center">
                                                        <div class="drag-upload-btn btn btn-sm btn-primary me-2" onclick="var input=this.querySelector('input[type=file]'); if (input) { input.click(); } return false;">
                                                            Change
                                                            <input type="file" class="form-control image-sign" accept="image/*" data-business-file="white_logo">
                                                        </div>
                                                        <a href="javascript:void(0);" class="btn btn-light btn-sm">Cancel</a>
                                                    </div>
                                                    
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center flex-wrap row-gap-3 bg-light w-100 rounded p-3 mb-4">                                                
                                                <div class="d-flex align-items-center justify-content-center avatar bg-dark avatar-xxl rounded border border-dashed me-2 px-2 flex-shrink-0 text-dark frames">
                                                    <img id="preview-dark_logo" src="{{ URL::asset('build/img/logo-white.svg') }}" class="img-fluid text-white" alt="logo">
                                                </div>                                              
                                                <div class="profile-upload upload-pic">
                                                    <div class="mb-2">
                                                        <h6 class="mb-1">Dark Logo</h6>
                                                        <p class="fs-12">Recommended image size is 160px x 50px</p>
                                                    </div>
                                                    <div class="profile-uploader d-flex align-items-center">
                                                        <div class="drag-upload-btn btn btn-sm btn-primary me-2" onclick="var input=this.querySelector('input[type=file]'); if (input) { input.click(); } return false;">
                                                            Change
                                                            <input type="file" class="form-control image-sign" accept="image/*" data-business-file="dark_logo">
                                                        </div>
                                                        <a href="javascript:void(0);" class="btn btn-light btn-sm">Cancel</a>
                                                    </div>
                                                    
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center flex-wrap row-gap-3 bg-light w-100 rounded p-3 mb-4">                                                
                                                <div class="d-flex align-items-center justify-content-center avatar avatar-xxl bg-white rounded border border-dashed me-2 p-3 flex-shrink-0 text-dark frames">
                                                    <img id="preview-white_mini_logo" src="{{ URL::asset('build/img/image111.png') }}" class="img-fluid" alt="logo">
                                                </div>                                              
                                                <div class="profile-upload upload-pic">
                                                    <div class="mb-2">
                                                        <h6 class="mb-1">White Mini Logo</h6>
                                                        <p class="fs-12">Recommended image size is 80px x 80px</p>
                                                    </div>
                                                    <div class="profile-uploader d-flex align-items-center">
                                                        <div class="drag-upload-btn btn btn-sm btn-primary me-2" onclick="var input=this.querySelector('input[type=file]'); if (input) { input.click(); } return false;">
                                                            Change
                                                            <input type="file" class="form-control image-sign" accept="image/*" data-business-file="white_mini_logo">
                                                        </div>
                                                        <a href="javascript:void(0);" class="btn btn-light btn-sm">Cancel</a>
                                                    </div>
                                                    
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center flex-wrap row-gap-3 bg-light w-100 rounded p-3 mb-4">                                                
                                                <div class="d-flex align-items-center justify-content-center avatar avatar-xxl bg-dark rounded border border-dashed me-2 flex-shrink-0 text-dark frames">
                                                    <img id="preview-dark_mini_logo" src="{{ URL::asset('build/img/logo-white.svg') }}" class="img-fluid" alt="logo">
                                                </div>                                              
                                                <div class="profile-upload upload-pic">
                                                    <div class="mb-2">
                                                        <h6 class="mb-1">Dark Mini Logo</h6>
                                                        <p class="fs-12">Recommended image size is 80px x 80px</p>
                                                    </div>
                                                    <div class="profile-uploader d-flex align-items-center">
                                                        <div class="drag-upload-btn btn btn-sm btn-primary me-2" onclick="var input=this.querySelector('input[type=file]'); if (input) { input.click(); } return false;">
                                                            Upload
                                                            <input type="file" class="form-control image-sign" accept="image/*" data-business-file="dark_mini_logo">
                                                        </div>
                                                        <a href="javascript:void(0);" class="btn btn-light btn-sm">Cancel</a>
                                                    </div>
                                                    
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center flex-wrap row-gap-3 bg-light w-100 rounded p-3 mb-4">                                                
                                                <div class="d-flex align-items-center justify-content-center avatar avatar-xxl rounded bg-white p-3 border border-dashed me-2 flex-shrink-0 text-dark frames">
                                                    <img id="preview-favicon" src="{{ URL::asset('build/img/image111.png') }}" class="img-fluid" alt="logo">
                                                </div>                                              
                                                <div class="profile-upload upload-pic">
                                                    <div class="mb-2">
                                                        <h6 class="mb-1">Favicon</h6>
                                                        <p class="fs-12">Recommended image size is 128px x 128px</p>
                                                    </div>
                                                    <div class="profile-uploader d-flex align-items-center">
                                                        <div class="drag-upload-btn btn btn-sm btn-primary me-2" onclick="var input=this.querySelector('input[type=file]'); if (input) { input.click(); } return false;">
                                                            Change
                                                            <input type="file" class="form-control image-sign" accept="image/*" data-business-file="favicon">
                                                        </div>
                                                        <a href="javascript:void(0);" class="btn btn-light btn-sm">Cancel</a>
                                                    </div>
                                                    
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center flex-wrap row-gap-3 bg-light w-100 rounded p-3 mb-4">                                                
                                                <div class="d-flex align-items-center justify-content-center avatar avatar-xxl rounded bg-white p-3 border border-dashed me-2 flex-shrink-0 text-dark frames">
                                                    <img id="preview-apple_icon" src="{{ URL::asset('build/img/image111.png') }}" class="img-fluid" alt="logo">
                                                </div>                                              
                                                <div class="profile-upload upload-pic">
                                                    <div class="mb-2">
                                                        <h6 class="mb-1">Apple Icon</h6>
                                                        <p class="fs-12">Recommended image size is 180px x 180px</p>
                                                    </div>
                                                    <div class="profile-uploader d-flex align-items-center">
                                                        <div class="drag-upload-btn btn btn-sm btn-primary me-2" onclick="var input=this.querySelector('input[type=file]'); if (input) { input.click(); } return false;">
                                                            Change
                                                            <input type="file" class="form-control image-sign" accept="image/*" data-business-file="apple_icon">
                                                        </div>
                                                        <a href="javascript:void(0);" class="btn btn-light btn-sm">Cancel</a>
                                                    </div>
                                                    
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="border-bottom mb-3">
                                    <h6 class="mb-3">Address Information</h6>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="row align-items-center mb-3">
                                                <div class="col-md-2">
                                                    <label class="form-label mb-md-0">Address</label>
                                                </div>
                                                <div class="col-md-10">
                                                    <input type="text" class="form-control" data-business="address" placeholder="Street address">
                                                </div>	
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row align-items-center mb-3">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">Country</label>
                                                </div>
                                                <div class="col-md-8">
                                                    <div>
                                                        <select class="select" data-business="country">
                                                            <option value="">Select</option>
                                                            <option value="Indonesia">Indonesia</option>
                                                            <option value="Singapore">Singapore</option>
                                                            <option value="Malaysia">Malaysia</option>
                                                            <option value="USA">USA</option>
                                                        </select>
                                                    </div>
                                                </div>		
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row align-items-center mb-3">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">State</label>
                                                </div>
                                                <div class="col-md-8">
                                                    <div>
                                                        <select class="select" data-business="state">
                                                            <option value="">Select</option>
                                                            <option value="DKI Jakarta">DKI Jakarta</option>
                                                            <option value="Jawa Barat">Jawa Barat</option>
                                                            <option value="Jawa Timur">Jawa Timur</option>
                                                            <option value="Banten">Banten</option>
                                                        </select>
                                                    </div>
                                                </div>	
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row align-items-center mb-3">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">City</label>
                                                </div>
                                                <div class="col-md-8">
                                                    <select class="select" data-business="city">
                                                        <option value="">Select</option>
                                                        <option value="Jakarta Selatan">Jakarta Selatan</option>
                                                        <option value="Jakarta Pusat">Jakarta Pusat</option>
                                                        <option value="Bandung">Bandung</option>
                                                        <option value="Surabaya">Surabaya</option>
                                                    </select>
                                                </div>	
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row align-items-center mb-3">
                                                <div class="col-md-4">
                                                    <label class="form-label mb-md-0">Postal Code</label>
                                                </div>
                                                <div class="col-md-8">
                                                    <input type="text" class="form-control" data-business="postal_code" placeholder="e.g., 12950">
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
function initBusinessSettingsPage() {
    const apiBaseUrl = '/v1/hcm';

    function getAuthToken() {
        if (window.AuthApi && typeof window.AuthApi.getToken === 'function') {
            const apiToken = window.AuthApi.getToken();
            if (apiToken) {
                return apiToken;
            }
        }

        return localStorage.getItem('arcav_access_token') ||
               sessionStorage.getItem('arcav_access_token') ||
               localStorage.getItem('token') ||
               sessionStorage.getItem('token') ||
               $('meta[name="api-token"]').attr('content') ||
               $('meta[name="auth-token"]').attr('content') ||
               null;
    }

    function getTenantHeaders() {
        let tenantContext = {};
        try {
            tenantContext = JSON.parse(localStorage.getItem('arcav_active_tenant') || '{}') || {};
        } catch (_err) {
            tenantContext = {};
        }

        const headers = {};
        if (tenantContext.companyCode) {
            headers['X-Company-Code'] = String(tenantContext.companyCode);
        }
        if (tenantContext.companyId !== undefined && tenantContext.companyId !== null && tenantContext.companyId !== '') {
            headers['X-Company-Id'] = String(tenantContext.companyId);
        }

        return headers;
    }

    function getHeaders() {
        const token = getAuthToken();
        return {
            'Authorization': token ? `Bearer ${token}` : '',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            ...getTenantHeaders()
        };
    }

    function applyBrandingPreviews(settings) {
        const brandingFields = ['white_logo', 'dark_logo', 'white_mini_logo', 'dark_mini_logo', 'favicon', 'apple_icon'];
        brandingFields.forEach((field) => {
            const pathKey = `business_${field}_path`;
            if (settings[pathKey]) {
                $(`#preview-${field}`).attr('src', `/storage/${settings[pathKey]}`);
            }
        });
    }
    
    // Load existing business settings on page load
    loadBusinessSettings();
    
    function loadBusinessSettings() {
        $.ajax({
            url: `${apiBaseUrl}/settings?group=business`,
            type: 'GET',
            headers: getHeaders(),
            success: function(response) {
                if (response.success && response.data) {
                    const settings = response.data;
                    console.log('✓ Loaded business settings:', settings);
                    // Populate form fields with existing values
                    $('[data-business]').each(function() {
                        const $field = $(this);
                        const key = $field.data('business');
                        const settingKey = `business_${key}`;
                        if (Object.prototype.hasOwnProperty.call(settings, settingKey)) {
                            const value = settings[settingKey] ?? '';
                            $field.val(value);
                            if ($field.is('select')) {
                                $field.trigger('change');
                            }
                        }
                    });

                    applyBrandingPreviews(settings);
                }
            },
            error: function(err) {
                console.warn('⚠ Could not load business settings, using empty defaults', err);
            }
        });
    }
    
    // Handle form submission
    $('#businessForm').on('submit', function(e) {
        e.preventDefault();

        const businessData = {};
        $('[data-business]').each(function() {
            const key = $(this).data('business');
            const rawValue = $(this).val();
            const value = typeof rawValue === 'string' ? rawValue.trim() : rawValue;

            businessData[key] = value === '' ? null : value;
        });
        
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Saving...');
        console.log('→ Sending business settings:', businessData);
        
        $.ajax({
            url: `${apiBaseUrl}/settings`,
            type: 'POST',
            headers: getHeaders(),
            xhrFields: {
                withCredentials: true
            },
            dataType: 'json',
            data: JSON.stringify({
                group: 'business',
                settings: businessData
            }),
            success: function(response) {
                if (response.success) {
                    alert(response.message || 'Business settings saved successfully.');
                    submitBtn.prop('disabled', false).text(originalText);
                } else {
                    throw new Error(response.message || 'Unknown save error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('✗ Error saving business settings:', {
                    status: jqXHR.status,
                    response: jqXHR.responseJSON || jqXHR.responseText,
                    textStatus,
                    errorThrown
                });
                let errorMsg = 'Error saving business settings.';
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

    function bindBrandingUploadHandlers() {
        document.querySelectorAll('[data-business-file]').forEach((input) => {
            if (input.dataset.businessBound === '1') {
                return;
            }

            input.dataset.businessBound = '1';

            input.addEventListener('change', async function() {
                const file = this.files && this.files[0] ? this.files[0] : null;
                const field = this.dataset.businessFile || '';

                if (!file || !field) {
                    return;
                }

                const formData = new FormData();
                formData.append('group', 'business');
                formData.append('field', field);
                formData.append('file', file);

                const token = getAuthToken();
                const triggerBtn = this.closest('.drag-upload-btn');
                const originalHtml = triggerBtn ? triggerBtn.innerHTML : '';

                if (triggerBtn) {
                    triggerBtn.classList.add('disabled');
                    triggerBtn.textContent = 'Uploading...';
                }

                try {
                    const response = await fetch(`${apiBaseUrl}/settings/upload`, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            ...getTenantHeaders(),
                        },
                        body: formData,
                    });

                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok || !payload.success) {
                        const errorMessage = payload?.message || payload?.error?.message || 'Upload failed';
                        throw new Error(errorMessage);
                    }

                    const preview = document.getElementById(`preview-${field}`);
                    if (preview && payload.data?.url) {
                        preview.src = payload.data.url;
                    }

                    alert('Branding image uploaded successfully.');
                } catch (error) {
                    const message = error && error.message ? error.message : 'Upload failed.';
                    alert(`ERROR: ${message}`);
                } finally {
                    if (triggerBtn) {
                        triggerBtn.classList.remove('disabled');
                        triggerBtn.innerHTML = originalHtml;
                    }
                    this.value = '';
                }
            });
        });
    }

    bindBrandingUploadHandlers();
}

(function waitForJQueryAndInit() {
    if (window.jQuery) {
        window.jQuery(function () {
            initBusinessSettingsPage();
        });
        return;
    }

    window.setTimeout(waitForJQueryAndInit, 50);
})();
</script>

@endsection