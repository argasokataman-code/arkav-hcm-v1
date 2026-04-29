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
$(document).ready(function() {
    const apiBaseUrl = '/v1/hcm';

    function getAuthToken() {
        return localStorage.getItem('token') ||
               sessionStorage.getItem('token') ||
               $('meta[name="api-token"]').attr('content') ||
               $('meta[name="auth-token"]').attr('content') ||
               null;
    }
    
    // Load existing prefixes on page load
    loadPrefixes();
    
    function loadPrefixes() {
        $.ajax({
            url: `${apiBaseUrl}/settings?group=prefix`,
            type: 'GET',
            headers: {
                'Authorization': `Bearer ${getAuthToken() || ''}`,
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success && response.data) {
                    const settings = response.data;
                    console.log('✓ Loaded prefix settings:', settings);
                    // Populate form fields with existing values
                    $('[data-prefix]').each(function() {
                        const key = $(this).data('prefix');
                        const settingKey = `prefix_${key}`;
                        if (settings[settingKey]) {
                            $(this).val(settings[settingKey]);
                        }
                    });
                }
            },
            error: function(err) {
                console.warn('⚠ Could not load prefixes, using empty defaults', err);
                // Forms start empty, which is fine
            }
        });
    }
    
    // Get authentication token from multiple sources
    // Handle form submission
    $('#prefixesForm').on('submit', function(e) {
        e.preventDefault();
        
        const token = getAuthToken();
        if (!token) {
            console.error('✗ No authentication token found');
            alert('ERROR: Authentication token not found. Please refresh the page and try again.');
            return;
        }
        
        const prefixData = {};
        let hasData = false;
        
        $('[data-prefix]').each(function() {
            const key = $(this).data('prefix');
            const value = $(this).val().trim();
            if (value) {
                prefixData[key] = value;
                hasData = true;
            }
        });
        
        if (!hasData) {
            alert('WARNING: No prefix values entered. Please fill in at least one field.');
            return;
        }
        
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Saving...');
        
        console.log('→ Sending prefix settings:', prefixData);
        
        $.ajax({
            url: `${apiBaseUrl}/settings`,
            type: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: 'json',
            data: JSON.stringify({
                group: 'prefix',
                settings: prefixData
            }),
            success: function(response) {
                console.log('✓ API Response:', response);
                if (response.success) {
                    console.log('✓ Prefixes saved successfully');
                    // Show success notification
                    const successMsg = `✓ ${response.message || 'Prefixes saved successfully!'}`;
                    alert(successMsg);
                    submitBtn.prop('disabled', false).text(originalText);
                } else {
                    throw new Error(response.message || 'Unknown error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('✗ Error saving prefixes:');
                console.error('Status:', jqXHR.status);
                console.error('Response:', jqXHR.responseJSON || jqXHR.responseText);
                console.error('Error:', textStatus, errorThrown);
                
                let errorMsg = 'Error saving prefixes.';
                if (jqXHR.status === 401 || jqXHR.status === 403) {
                    errorMsg = 'ERROR: Not authorized. Please check your permissions.';
                } else if (jqXHR.status === 422) {
                    errorMsg = 'ERROR: Invalid data. ' + (jqXHR.responseJSON?.message || 'Please check your input.');
                } else if (jqXHR.status === 0) {
                    errorMsg = 'ERROR: Network error. Please check your connection.';
                } else if (jqXHR.responseJSON?.message) {
                    errorMsg = 'ERROR: ' + jqXHR.responseJSON.message;
                }
                
                alert(errorMsg);
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script>

@endsection