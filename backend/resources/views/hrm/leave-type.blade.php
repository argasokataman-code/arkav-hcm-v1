<?php $page = 'leave-type'; ?>
@php
    $isGlobalHcmAdmin = (bool) ((request()->user() ?: auth()->user())?->isGlobalHcmAdmin());
@endphp

@extends('layout.mainlayout')
@section('content')
@php
    $leaveTypes = $leaveTypes ?? collect();
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
                        <li class="breadcrumb-item">Administration</li>
                        <li class="breadcrumb-item active" aria-current="page">Settings</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="head-icons ms-2">
                    <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                        <i class="ti ti-chevrons-up"></i>
                    </a>
                </div>
            </div>
        </div>
        <!-- /Breadcrumb -->

        <ul class="nav nav-tabs nav-tabs-solid bg-transparent border-bottom mb-3">
            <li class="nav-item">
                <a class="nav-link" href="{{ url('profile-settings') }}"><i class="ti ti-settings me-2"></i>General Settings</a>
            </li>
            @if ($isGlobalHcmAdmin)

            <li class="nav-item">
                <a class="nav-link" href="{{ url('business-settings') }}"><i class="ti ti-world-cog me-2"></i>Website Settings</a>
            </li>

            @endif
            <li class="nav-item">
                <a class="nav-link active" href="{{ url('approval-settings') }}"><i class="ti ti-device-ipad-horizontal-cog me-2"></i>App Settings</a>
            </li>
            @if ($isGlobalHcmAdmin)
            <li class="nav-item">
                <a class="nav-link" href="{{ url('email-settings') }}"><i class="ti ti-server-cog me-2"></i>System Settings</a>
            </li>
            @endif
            @if ($isGlobalHcmAdmin)
            <li class="nav-item">
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
                            <a href="{{ url('approval-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Approval Settings</a>
                            @if ($isGlobalHcmAdmin)<a href="{{ url('invoice-settings') }}" class="d-inline-flex align-items-center rounded py-2 px-3">Invoice Settings</a>@endif
                            <a href="{{ url('leave-type') }}" class="d-inline-flex align-items-center rounded active py-2 px-3"><i class="ti ti-arrow-badge-right me-2"></i>Leave Type</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-9">
                <div class="card">
                    <div class="card-body">
                        <div class="border-bottom d-flex align-items-center justify-content-between pb-3 mb-3">
                            <div>
                                <h4 class="mb-1">Leave Type</h4>
                                <p class="text-muted mb-0">Kelola katalog jenis cuti yang dipakai oleh request form dan perhitungan saldo cuti.</p>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-outline-light border" id="refreshLeaveTypesBtn">
                                    <i class="ti ti-refresh me-2"></i>Refresh
                                </button>
                                <button type="button" class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#leaveTypeModal" data-leave-type-mode="create">
                                    <i class="ti ti-circle-plus me-2"></i>Add Leave Type
                                </button>
                            </div>
                        </div>
                        <div class="alert alert-danger d-none" id="leaveTypePageError"></div>

                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="no-sort">#</th>
                                        <th>Code</th>
                                        <th>Leave Type</th>
                                        <th>Days</th>
                                        <th>Carry Forward</th>
                                        <th>Max Carry</th>
                                        <th>Earned Leave</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($leaveTypes as $leaveType)
                                        <tr data-leave-type-row="{{ $leaveType->id }}">
                                            <td>{{ $leaveType->sort_order }}</td>
                                            <td><code>{{ $leaveType->code }}</code></td>
                                            <td class="text-dark">{{ $leaveType->name }}</td>
                                            <td>{{ $leaveType->days !== null ? rtrim(rtrim(number_format((float) $leaveType->days, 2, '.', ''), '0'), '.') : '—' }}</td>
                                            <td>
                                                <span class="badge {{ $leaveType->carry_forward ? 'badge-success' : 'badge-secondary' }}">
                                                    {{ $leaveType->carry_forward ? 'Yes' : 'No' }}
                                                </span>
                                            </td>
                                            <td>{{ $leaveType->max_carry_days ?? '—' }}</td>
                                            <td>
                                                <span class="badge {{ $leaveType->earned_leave ? 'badge-success' : 'badge-secondary' }}">
                                                    {{ $leaveType->earned_leave ? 'Yes' : 'No' }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge {{ $leaveType->is_enabled ? 'badge-success' : 'badge-danger' }}">
                                                    <i class="ti ti-point-filled"></i>{{ $leaveType->is_enabled ? 'Active' : 'Disabled' }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <div class="action-icon d-inline-flex gap-2">
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-primary d-inline-flex align-items-center"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#leaveTypeModal"
                                                        data-leave-type-mode="edit"
                                                        data-leave-type-id="{{ $leaveType->id }}"
                                                        data-leave-type-code="{{ $leaveType->code }}"
                                                        data-leave-type-name="{{ e($leaveType->name) }}"
                                                        data-leave-type-days="{{ $leaveType->days ?? '' }}"
                                                        data-leave-type-carry-forward="{{ (int) $leaveType->carry_forward }}"
                                                        data-leave-type-max-carry-days="{{ $leaveType->max_carry_days ?? '' }}"
                                                        data-leave-type-earned-leave="{{ (int) $leaveType->earned_leave }}"
                                                        data-leave-type-is-enabled="{{ (int) $leaveType->is_enabled }}"
                                                        data-leave-type-sort-order="{{ $leaveType->sort_order }}"
                                                    >
                                                        <i class="ti ti-edit"></i>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm {{ $leaveType->is_enabled ? 'btn-outline-danger' : 'btn-outline-success' }} d-inline-flex align-items-center"
                                                        data-leave-type-toggle
                                                        data-leave-type-id="{{ $leaveType->id }}"
                                                        data-leave-type-code="{{ $leaveType->code }}"
                                                        data-leave-type-name="{{ e($leaveType->name) }}"
                                                        data-leave-type-days="{{ $leaveType->days ?? '' }}"
                                                        data-leave-type-carry-forward="{{ (int) $leaveType->carry_forward }}"
                                                        data-leave-type-max-carry-days="{{ $leaveType->max_carry_days ?? '' }}"
                                                        data-leave-type-earned-leave="{{ (int) $leaveType->earned_leave }}"
                                                        data-leave-type-is-enabled="{{ (int) $leaveType->is_enabled }}"
                                                        data-leave-type-sort-order="{{ $leaveType->sort_order }}"
                                                        title="{{ $leaveType->is_enabled ? 'Disable' : 'Enable' }}"
                                                    >
                                                        <i class="ti {{ $leaveType->is_enabled ? 'ti-toggle-right' : 'ti-toggle-left' }}"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9">
                                                <div class="py-4 text-center text-muted">No leave types found.</div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /Page Wrapper -->

<div class="modal fade" id="leaveTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="leaveTypeForm">
                <div class="modal-header">
                    <h4 class="modal-title" id="leaveTypeModalTitle">Add Leave Type</h4>
                    <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <div class="modal-body pb-0">
                    <input type="hidden" id="leaveTypeId" name="leaveTypeId">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="leaveTypeCode" placeholder="annual_leave" maxlength="64" required>
                                <div class="form-text">Gunakan huruf kecil dan underscore, contoh: <code>annual_leave</code>.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Leave Type <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="leaveTypeName" maxlength="150" placeholder="Annual Leave" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Days</label>
                                <input type="number" class="form-control" id="leaveTypeDays" min="0" max="366" step="0.5" placeholder="12">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Max Carry Days</label>
                                <input type="number" class="form-control" id="leaveTypeMaxCarryDays" min="0" max="366" step="1" placeholder="5">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Sort Order</label>
                                <input type="number" class="form-control" id="leaveTypeSortOrder" min="0" max="255" step="1" placeholder="1">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-check-md form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="leaveTypeIsEnabled" checked>
                                <label class="form-check-label" for="leaveTypeIsEnabled">Enabled</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-check-md form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="leaveTypeCarryForward">
                                <label class="form-check-label" for="leaveTypeCarryForward">Carry Forward</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-check-md form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="leaveTypeEarnedLeave">
                                <label class="form-check-label" for="leaveTypeEarnedLeave">Earned Leave</label>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-danger d-none" id="leaveTypeFormError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="leaveTypeSubmitBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const apiBaseUrl = '/v1/hcm';
    const leaveTypeModalEl = document.getElementById('leaveTypeModal');
    const leaveTypeForm = document.getElementById('leaveTypeForm');
    const refreshLeaveTypesBtn = document.getElementById('refreshLeaveTypesBtn');
    const leaveTypeFormError = document.getElementById('leaveTypeFormError');
    const leaveTypePageError = document.getElementById('leaveTypePageError');
    const leaveTypeId = document.getElementById('leaveTypeId');
    const leaveTypeCode = document.getElementById('leaveTypeCode');
    const leaveTypeName = document.getElementById('leaveTypeName');
    const leaveTypeDays = document.getElementById('leaveTypeDays');
    const leaveTypeMaxCarryDays = document.getElementById('leaveTypeMaxCarryDays');
    const leaveTypeSortOrder = document.getElementById('leaveTypeSortOrder');
    const leaveTypeIsEnabled = document.getElementById('leaveTypeIsEnabled');
    const leaveTypeCarryForward = document.getElementById('leaveTypeCarryForward');
    const leaveTypeEarnedLeave = document.getElementById('leaveTypeEarnedLeave');
    const leaveTypeModalTitle = document.getElementById('leaveTypeModalTitle');
    const leaveTypeSubmitBtn = document.getElementById('leaveTypeSubmitBtn');

    function metaContent(name) {
        return document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') || null;
    }

    function readValue(element) {
        return (element?.value || '').trim();
    }

    function setAlertState(element, message) {
        if (!element) {
            return;
        }

        element.textContent = message || '';
        element.classList.toggle('d-none', !message);
    }

    function toBoolean(value, fallback = false) {
        if (value === undefined || value === null || value === '') {
            return fallback;
        }

        return String(value) === '1' || String(value).toLowerCase() === 'true';
    }

    function rowToData(button) {
        const dataset = button?.dataset || {};

        return {
            id: dataset.leaveTypeId || '',
            code: dataset.leaveTypeCode || '',
            name: dataset.leaveTypeName || '',
            days: dataset.leaveTypeDays || '',
            maxCarryDays: dataset.leaveTypeMaxCarryDays || '',
            sortOrder: dataset.leaveTypeSortOrder || '',
            isEnabled: dataset.leaveTypeIsEnabled || '1',
            carryForward: dataset.leaveTypeCarryForward || '0',
            earnedLeave: dataset.leaveTypeEarnedLeave || '0',
        };
    }

    function getAuthToken() {
        return localStorage.getItem('arcav_access_token') ||
            sessionStorage.getItem('arcav_access_token') ||
            localStorage.getItem('token') ||
            sessionStorage.getItem('token') ||
            metaContent('api-token') ||
            metaContent('auth-token') ||
            null;
    }

    function authHeaders(extraHeaders = {}) {
        return Object.assign({
            'Authorization': `Bearer ${getAuthToken() || ''}`,
            'X-CSRF-TOKEN': metaContent('csrf-token') || ''
        }, extraHeaders);
    }

    function showFormError(message) {
        setAlertState(leaveTypeFormError, message);
    }

    function clearFormError() {
        setAlertState(leaveTypeFormError, '');
    }

    function showPageError(message) {
        setAlertState(leaveTypePageError, message);
    }

    function clearPageError() {
        setAlertState(leaveTypePageError, '');
    }

    function resetForm() {
        clearFormError();
        clearPageError();
        leaveTypeForm?.reset();
        leaveTypeId.value = '';
        leaveTypeCode.readOnly = false;
        leaveTypeModalTitle.textContent = 'Add Leave Type';
        leaveTypeSubmitBtn.textContent = 'Save';
        leaveTypeIsEnabled.checked = true;
        leaveTypeCarryForward.checked = false;
        leaveTypeEarnedLeave.checked = false;
    }

    function fillForm(data) {
        leaveTypeId.value = data.id || '';
        leaveTypeCode.value = data.code || '';
        leaveTypeCode.readOnly = true;
        leaveTypeName.value = data.name || '';
        leaveTypeDays.value = data.days ?? '';
        leaveTypeMaxCarryDays.value = data.maxCarryDays ?? '';
        leaveTypeSortOrder.value = data.sortOrder ?? '';
        leaveTypeIsEnabled.checked = toBoolean(data.isEnabled, true);
        leaveTypeCarryForward.checked = toBoolean(data.carryForward, false);
        leaveTypeEarnedLeave.checked = toBoolean(data.earnedLeave, false);
    }

    function payloadFromForm() {
        const code = readValue(leaveTypeCode);
        const name = readValue(leaveTypeName);
        const daysValue = readValue(leaveTypeDays);
        const maxCarryValue = readValue(leaveTypeMaxCarryDays);
        const sortOrderValue = readValue(leaveTypeSortOrder);

        return {
            code,
            name,
            days: daysValue === '' ? null : Number(daysValue),
            maxCarryDays: maxCarryValue === '' ? null : Number(maxCarryValue),
            sortOrder: sortOrderValue === '' ? null : Number(sortOrderValue),
            isEnabled: leaveTypeIsEnabled.checked,
            carryForward: leaveTypeCarryForward.checked,
            earnedLeave: leaveTypeEarnedLeave.checked,
        };
    }

    async function request(method, url, data) {
        const response = await fetch(url, {
            method,
            headers: authHeaders({ 'Content-Type': 'application/json' }),
            body: data ? JSON.stringify(data) : undefined,
        });

        let payload = null;

        try {
            payload = await response.json();
        } catch (error) {
            payload = null;
        }

        if (!response.ok) {
            const requestError = new Error(payload?.error?.message || payload?.message || 'Request failed.');
            requestError.payload = payload;
            throw requestError;
        }

        return payload;
    }

    refreshLeaveTypesBtn?.addEventListener('click', function() {
        window.location.reload();
    });

    document.addEventListener('click', function(event) {
        const createButton = event.target.closest('[data-leave-type-mode="create"]');
        if (createButton) {
            resetForm();
            leaveTypeModalTitle.textContent = 'Add Leave Type';
            leaveTypeSubmitBtn.textContent = 'Create';
            leaveTypeCode.readOnly = false;
            return;
        }

        const editButton = event.target.closest('[data-leave-type-mode="edit"]');
        if (editButton) {
            resetForm();
            fillForm(rowToData(editButton));
            leaveTypeModalTitle.textContent = 'Edit Leave Type';
            leaveTypeSubmitBtn.textContent = 'Save Changes';
            return;
        }

        const toggleButton = event.target.closest('[data-leave-type-toggle]');
        if (!toggleButton) {
            return;
        }

        clearPageError();
        const data = rowToData(toggleButton);
        const nextEnabled = !toBoolean(data.isEnabled, true);
        const payload = {
            name: String(data.name || '').trim(),
            days: data.days === '' || data.days === null ? null : Number(data.days),
            maxCarryDays: data.maxCarryDays === '' || data.maxCarryDays === null ? null : Number(data.maxCarryDays),
            sortOrder: data.sortOrder === '' || data.sortOrder === null ? null : Number(data.sortOrder),
            isEnabled: nextEnabled,
            carryForward: toBoolean(data.carryForward, false),
            earnedLeave: toBoolean(data.earnedLeave, false),
        };

        toggleButton.disabled = true;
        request('PUT', `${apiBaseUrl}/leave-types/${data.id}`, payload)
            .then(function(response) {
                if (!response?.success) {
                    throw new Error(response?.error?.message || response?.message || 'Failed to update leave type.');
                }

                window.location.reload();
            })
            .catch(function(error) {
                showPageError(error?.payload?.error?.message || error?.payload?.message || error?.message || 'Failed to update leave type.');
            })
            .finally(function() {
                toggleButton.disabled = false;
            });
    });

    leaveTypeForm?.addEventListener('submit', function(event) {
        event.preventDefault();
        clearFormError();
        clearPageError();

        const id = readValue(leaveTypeId);
        const payload = payloadFromForm();
        const method = id ? 'PUT' : 'POST';
        const endpoint = id ? `${apiBaseUrl}/leave-types/${id}` : `${apiBaseUrl}/leave-types`;

        leaveTypeSubmitBtn.disabled = true;

        request(method, endpoint, payload)
            .then(function(response) {
                if (!response?.success) {
                    throw new Error(response?.error?.message || response?.message || 'Failed to save leave type.');
                }

                window.location.reload();
            })
            .catch(function(error) {
                showFormError(error?.payload?.error?.message || error?.payload?.message || error?.message || 'Failed to save leave type.');
            })
            .finally(function() {
                leaveTypeSubmitBtn.disabled = false;
            });
    });

    leaveTypeModalEl.addEventListener('show.bs.modal', function(event) {
        const trigger = event.relatedTarget;
        const mode = trigger?.getAttribute('data-leave-type-mode') || 'create';

        if (mode === 'create') {
            resetForm();
            leaveTypeModalTitle.textContent = 'Add Leave Type';
            leaveTypeSubmitBtn.textContent = 'Create';
            return;
        }

        resetForm();
        const data = rowToData(trigger);
        fillForm(data);
        leaveTypeModalTitle.textContent = 'Edit Leave Type';
        leaveTypeSubmitBtn.textContent = 'Save Changes';
    });
});
</script>
@endsection
