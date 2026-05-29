<?php $page = 'approval-settings'; ?>
@php
    $isGlobalHcmAdmin = (bool) ((request()->user() ?: auth()->user())?->isGlobalHcmAdmin());
    $activeModules = $activeModules ?? ['leave'];

    $moduleLabels = [
        'leave'    => ['title' => 'Leave Approval',    'h5' => 'Default Leave Approval',    'approverLabel' => 'Leave Approvers'],
        'expense'  => ['title' => 'Expense Approval',  'h5' => 'Default Expense Approval',  'approverLabel' => 'Expense Approvers'],
        'offer'    => ['title' => 'Offer Approval',    'h5' => 'Default Offer Approval',    'approverLabel' => 'Offer Approvers'],
        'overtime'     => ['title' => 'Overtime Approval',     'h5' => 'Default Overtime Approval',     'approverLabel' => 'Overtime Approvers'],
        'resignation'  => ['title' => 'Resignation Approval',  'h5' => 'Default Resignation Approval',  'approverLabel' => 'Resignation Approvers'],
        'termination'  => ['title' => 'Termination Approval',  'h5' => 'Default Termination Approval',  'approverLabel' => 'Termination Approvers'],
    ];
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
                <a class="nav-link " href="{{url('profile-settings')}}"><i class="ti ti-settings me-2"></i>General Settings</a>
            </li>
            @if ($isGlobalHcmAdmin)
            <li class="nav-item">
                <a class="nav-link" href="{{url('business-settings')}}"><i class="ti ti-world-cog me-2"></i>Website Settings</a>
            </li>
            @endif
            <li class="nav-item">
                <a class="nav-link active" href="{{url('approval-settings')}}"><i class="ti ti-device-ipad-horizontal-cog me-2"></i>App Settings</a>
            </li>
            @if ($isGlobalHcmAdmin)
            <li class="nav-item">
                <a class="nav-link" href="{{url('email-settings')}}"><i class="ti ti-server-cog me-2"></i>System Settings</a>
            </li>
            @endif
        </ul>
        <div class="row">
            <div class="col-xl-3 theiaStickySidebar">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-column list-group settings-list">
                            <a href="{{url('approval-settings')}}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>Approval Settings</a>
                            @if ($isGlobalHcmAdmin)<a href="{{url('invoice-settings')}}" class="d-inline-flex align-items-center rounded py-2 px-3">Invoice Settings</a>@endif
                            <a href="{{url('leave-type')}}" class="d-inline-flex align-items-center rounded py-2 px-3">Leave Type</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-9">
                <div class="card">
                    <div class="card-body">
                        <div class="border-bottom mb-3 pb-3">
                            <h4>Approval Settings</h4>
                        </div>

                        <!-- Alert feedback -->
                        <div id="approval-settings-alert" class="alert d-none mb-3" role="alert"></div>

                        @foreach ($activeModules as $mod)
                        @php $label = $moduleLabels[$mod] ?? ['title' => ucfirst($mod).' Approval', 'h5' => 'Default '.ucfirst($mod).' Approval', 'approverLabel' => ucfirst($mod).' Approvers']; @endphp
                        <div class="border-bottom mb-4 pb-3 approval-module-section" data-module="{{ $mod }}">
                            <div class="row">
                                <div class="col-md-12">
                                    <h6 class="mb-3">{{ $label['title'] }}</h6>
                                    <div class="d-flex align-items-center flex-wrap row-gap-3 pb-2 mb-2">
                                        <h5 class="mb-0 me-3">{{ $label['h5'] }}</h5>
                                        <div class="form-check ms-3">
                                            <input class="form-check-input approval-mode-radio" type="radio"
                                                name="approvalMode_{{ $mod }}" id="sequence_{{ $mod }}"
                                                value="sequence" data-module="{{ $mod }}">
                                            <label class="form-check-label" for="sequence_{{ $mod }}">
                                                Sequence Approval (Chain)
                                            </label>
                                        </div>
                                        <div class="form-check ms-3">
                                            <input class="form-check-input approval-mode-radio" type="radio"
                                                name="approvalMode_{{ $mod }}" id="simultaneous_{{ $mod }}"
                                                value="simultaneous" data-module="{{ $mod }}" checked>
                                            <label class="form-check-label" for="simultaneous_{{ $mod }}">
                                                Simultaneous Approval
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-8 mt-2">
                                        <div class="d-flex align-items-center flex-wrap gap-3 mb-3">
                                            <label class="form-label mb-0">{{ $label['approverLabel'] }}</label>
                                            <div class="flex-fill">
                                                <select class="approval-approvers-select" multiple
                                                    data-module="{{ $mod }}"
                                                    data-placeholder="Select approvers..."
                                                    id="approvers_{{ $mod }}">
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <button type="button"
                                            class="btn btn-primary btn-sm approval-save-btn"
                                            data-module="{{ $mod }}">
                                            Save {{ $label['title'] }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /Page Wrapper -->

@push('scripts')
<script>
(function () {
    'use strict';

    const API_BASE = '/v1/hcm/approval-settings';

    function getAuthHeaders() {
        var token = (window.AuthApi && typeof window.AuthApi.getToken === 'function' && window.AuthApi.getToken())
            || localStorage.getItem('arcav_access_token')
            || sessionStorage.getItem('arcav_access_token')
            || '';
        var companyId = '';
        try {
            var ctx = JSON.parse(localStorage.getItem('arcav_active_tenant') || '{}');
            companyId = ctx.companyId ? String(ctx.companyId) : '';
        } catch (e) {}
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': token ? 'Bearer ' + token : '',
            'X-Company-Id': companyId,
        };
    }

    function showAlert(message, type) {
        const el = document.getElementById('approval-settings-alert');
        if (!el) return;
        el.textContent = message;
        el.className = 'alert alert-' + type + ' mb-3';
    }

    function hideAlert() {
        const el = document.getElementById('approval-settings-alert');
        if (el) el.className = 'alert d-none mb-3';
    }

    function buildApproverLabel(u) {
        var label = u.name;
        if (u.designation) label += ' — ' + u.designation;
        label += ' (' + u.email + ')';
        return label;
    }

    function initApproverSearch() {
        if (!window.$ || !$.fn.select2) return;
        document.querySelectorAll('.approval-approvers-select').forEach(function (sel) {
            $(sel).select2({
                placeholder: 'Ketik nama, jabatan, atau email...',
                allowClear: true,
                minimumInputLength: 2,
                language: {
                    inputTooShort: function () { return 'Ketik minimal 2 karakter untuk mencari...'; },
                    searching: function () { return 'Mencari...'; },
                    noResults: function () { return 'Karyawan tidak ditemukan.'; },
                },
                ajax: {
                    url: API_BASE + '/eligible-approvers',
                    delay: 300,
                    transport: function (params, success, failure) {
                        var qs = (params.data && params.data.q) ? '?q=' + encodeURIComponent(params.data.q) : '';
                        fetch(params.url + qs, { headers: getAuthHeaders() })
                            .then(function (r) { return r.json(); })
                            .then(success)
                            .catch(failure);
                    },
                    data: function (params) {
                        return { q: params.term || '' };
                    },
                    processResults: function (json) {
                        if (!json.success) return { results: [] };
                        return {
                            results: (json.data || []).map(function (u) {
                                return { id: u.id, text: buildApproverLabel(u) };
                            }),
                        };
                    },
                    cache: false,
                },
            });
        });
    }

    async function loadCurrentConfig() {
        try {
            const res = await fetch(API_BASE + '/', { headers: getAuthHeaders() });
            if (!res.ok) return;
            const json = await res.json();
            if (!json.success) return;
            Object.entries(json.data || {}).forEach(function ([mod, config]) {
                const radio = document.querySelector('input[name="approvalMode_' + mod + '"][value="' + config.approvalMode + '"]');
                if (radio) radio.checked = true;
                const sel = document.getElementById('approvers_' + mod);
                if (sel && config.approvers && config.approvers.length > 0) {
                    if (window.$ && $(sel).select2) {
                        // Pre-populate selected approvers as fixed options (AJAX mode requires this)
                        config.approvers.forEach(function (a) {
                            var label = a.name + ' (' + a.email + ')';
                            var option = new Option(label, String(a.userId), true, true);
                            $(sel).append(option);
                        });
                        $(sel).trigger('change');
                    }
                }
            });
        } catch (e) {
            console.warn('[ApprovalSettings] Failed to load config:', e);
        }
    }

    async function saveModuleConfig(module) {
        const modeRadio = document.querySelector('input[name="approvalMode_' + module + '"]:checked');
        const approvalMode = modeRadio ? modeRadio.value : 'simultaneous';
        const sel = document.getElementById('approvers_' + module);
        let approverUserIds = [];
        if (sel) {
            if (window.$ && $(sel).select2) {
                approverUserIds = $(sel).val() || [];
            } else {
                approverUserIds = Array.from(sel.selectedOptions).map(function (o) { return o.value; });
            }
        }
        approverUserIds = approverUserIds.map(Number).filter(Boolean);
        if (approverUserIds.length === 0) {
            showAlert('Please select at least one approver.', 'warning');
            return;
        }
        hideAlert();
        try {
            const res = await fetch(API_BASE + '/' + module, {
                method: 'PUT',
                headers: getAuthHeaders(),
                body: JSON.stringify({ approvalMode: approvalMode, approverUserIds: approverUserIds }),
            });
            const json = await res.json();
            if (json.success) {
                showAlert('Approval settings saved successfully.', 'success');
            } else {
                showAlert(json.error?.message || 'Failed to save settings.', 'danger');
            }
        } catch (e) {
            showAlert('Network error. Please try again.', 'danger');
        }
    }

    document.querySelectorAll('.approval-save-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            saveModuleConfig(btn.dataset.module);
        });
    });

    // Delay init until after core/script.js runs ($('.select2').select2() would corrupt our AJAX instance)
    setTimeout(function () {
        document.querySelectorAll('.approval-approvers-select').forEach(function (sel) {
            if (window.$ && $(sel).data('select2')) {
                $(sel).select2('destroy');
            }
        });
        initApproverSearch();
        loadCurrentConfig();
    }, 0);
})();
</script>
@endpush

@endsection