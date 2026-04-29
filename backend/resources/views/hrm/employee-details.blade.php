<?php $page = 'employee-details'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h6 class="fw-medium d-inline-flex align-items-center mb-3 mb-sm-0">
                        <a href="{{ url('employees') }}" data-employee-back-link><i class="ti ti-arrow-left me-2"></i>Employee Details</a>
                    </h6>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-4 theiaStickySidebar">
                    <div class="card card-bg-1">
                        <div class="card-body p-0">
                            <div class="text-center px-3 pb-3 border-bottom pt-3">
                                <div class="avatar avatar-xxl avatar-rounded border border-2 border-white m-auto d-flex mb-2 align-items-center justify-content-center bg-white text-primary fw-bold overflow-hidden" data-employee-avatar-wrap>
                                    <img src="" alt="Employee profile photo" class="w-100 h-100 d-none" data-employee-photo-preview>
                                    <span data-employee-initial>-</span>
                                </div>
                                <div class="d-flex justify-content-center gap-2 mb-2">
                                    <label for="employee_profile_photo_input" class="btn btn-sm btn-outline-primary" data-employee-photo-upload-btn>Upload Photo</label>
                                    <label for="employee_profile_photo_input" class="btn btn-sm btn-outline-secondary d-none" data-employee-photo-edit-btn>Edit Photo</label>
                                    <button type="button" class="btn btn-sm btn-outline-dark d-none" data-employee-photo-view-btn data-bs-toggle="modal" data-bs-target="#employee_photo_view_modal">View Photo</button>
                                </div>
                                <h5 class="d-flex align-items-center justify-content-center mb-1 text-dark"><span data-employee-name>-</span></h5>
                                <span class="badge badge-soft-dark fw-medium me-2">
                                    <i class="ti ti-point-filled me-1"></i><span data-employee-designation>-</span>
                                </span>
                            </div>
                            <div class="p-3 border-bottom">
                                <div class="d-flex align-items-center justify-content-between mb-2"><span><i class="ti ti-id me-2"></i>Employee ID</span><p class="text-dark" data-employee-no>-</p></div>
                                <div class="d-flex align-items-center justify-content-between mb-2"><span><i class="ti ti-star me-2"></i>Team</span><p class="text-dark" data-employee-team>-</p></div>
                                <div class="d-flex align-items-center justify-content-between mb-2"><span><i class="ti ti-building me-2"></i>Department</span><p class="text-dark" data-employee-department>-</p></div>
                                <div class="d-flex align-items-center justify-content-between mb-2"><span><i class="ti ti-user-shield me-2"></i>Team Leader</span><p class="text-dark text-end" data-employee-team-leader>-</p></div>
                                <div class="d-flex align-items-center justify-content-between mb-2"><span><i class="ti ti-calendar-check me-2"></i>Date Of Join</span><p class="text-dark" data-employee-join-date>-</p></div>
                                <div class="d-flex align-items-center justify-content-between"><span><i class="ti ti-calendar-check me-2"></i>Report Office</span><p class="text-dark" data-employee-report-office>-</p></div>
                            </div>
                            <div class="p-3 border-bottom">
                                <h6 class="mb-2">Basic information</h6>
                                <div class="d-flex align-items-center justify-content-between mb-2"><span><i class="ti ti-phone me-2"></i>Phone</span><p class="text-dark" data-employee-phone>-</p></div>
                                <div class="d-flex align-items-center justify-content-between mb-2"><span><i class="ti ti-mail-check me-2"></i>Email</span><a href="javascript:void(0);" class="text-info d-inline-flex align-items-center"><span data-employee-email>-</span></a></div>
                                <div class="d-flex align-items-center justify-content-between"><span><i class="ti ti-map-pin-check me-2"></i>Address</span><p class="text-dark text-end" data-employee-address>-</p></div>
                            </div>
                            <div class="p-3 border-bottom">
                                <h6 class="mb-2">Compensation</h6>
                                <div class="d-flex align-items-center justify-content-between mb-2"><span><i class="ti ti-cash me-2"></i>Base Salary</span><p class="text-dark" data-employee-base-salary>-</p></div>
                                <div class="d-flex align-items-center justify-content-between"><span><i class="ti ti-wallet me-2"></i>Fixed Allowance</span><p class="text-dark" data-employee-fixed-allowance>-</p></div>
                            </div>
                            <div class="p-3 border-bottom">
                                <h6 class="mb-2">Shift & Schedule</h6>
                                <div class="d-flex align-items-center justify-content-between mb-2"><span><i class="ti ti-clock-hour-10 me-2"></i>Current Schedule</span><p class="text-dark text-end" data-employee-schedule-display>-</p></div>
                                <div class="d-flex align-items-center justify-content-between mb-2"><span><i class="ti ti-calendar-time me-2"></i>Source</span><p class="text-dark text-end" data-employee-schedule-source>-</p></div>
                                <div class="d-flex align-items-center justify-content-between"><span><i class="ti ti-building-factory-2 me-2"></i>Assigned Shift</span><p class="text-dark text-end" data-employee-schedule-shift>-</p></div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6>Emergency Contact Number</h6>
                    </div>
                    <div class="card" data-employee-emergency-card>
                        <div class="card-body p-0">
                            <div class="p-3 border-bottom text-muted">Loading emergency contact...</div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-8" data-employee-details-sections>
                    <div class="card">
                        <div class="card-body">
                            <h5 class="mb-3">Loading employee details...</h5>
                            <p class="mb-0 text-muted">Please wait while we load profile details from API.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @component('components.modal-popup')
    @endcomponent

    @include('hcm.partials.promotion-detail-modal')
    @include('hcm.partials.resignation-detail-modal')
    @include('hcm.partials.termination-detail-modal')

    <input id="employee_profile_photo_input" type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="visually-hidden" data-employee-photo-input>

    <div class="modal fade" id="employee_photo_view_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Employee Profile Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" alt="Employee profile photo" class="img-fluid rounded border" data-employee-photo-modal-image>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            function setText(selector, value) {
                var el = document.querySelector(selector);
                if (el) {
                    el.textContent = value || '-';
                }
            }

            function formatRupiah(value) {
                var n = Number(value || 0);
                if (!isFinite(n)) {
                    n = 0;
                }
                return 'Rp' + n.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            }

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function renderFallback(item) {
                setText('[data-employee-name]', item.fullName);
                setText('[data-employee-no]', item.employeeNo);
                setText('[data-employee-team]', item.team);
                setText('[data-employee-department]', item.departmentName);
                setText('[data-employee-team-leader]', (item.assignment && item.assignment.managerName) || item.managerName || 'Belum ditentukan');
                setText('[data-employee-join-date]', item.joinDate);
                setText('[data-employee-designation]', item.designation);
                setText('[data-employee-phone]', item.phone);
                setText('[data-employee-email]', item.email);
                setText('[data-employee-address]', item.address);
                setText('[data-employee-base-salary]', formatRupiah(item.baseSalary));
                setText('[data-employee-fixed-allowance]', formatRupiah(item.fixedAllowance));
                setText('[data-employee-report-office]', item.reportOffice || '-');
                setText('[data-employee-initial]', ((item.fullName || '?').charAt(0) || '?').toUpperCase());

                var schedule = item.schedule || {};
                setText('[data-employee-schedule-display]', schedule.display || '-');
                setText('[data-employee-schedule-source]', schedule.sourceLabel || '-');
                setText('[data-employee-schedule-shift]', schedule.shiftName || 'Not assigned');

                var sections = document.querySelector('[data-employee-details-sections]');
                if (sections) {
                    sections.innerHTML = '<div class="card"><div class="card-body">' +
                        '<h5 class="mb-3">Employee Overview</h5>' +
                        '<div class="row g-3 mb-4">' +
                        '<div class="col-md-4"><small class="text-muted d-block">NIK</small><strong>' + escapeHtml(item.nik || (item.personal && item.personal.nik) || '-') + '</strong></div>' +
                        '<div class="col-md-4"><small class="text-muted d-block">Employment Status</small><strong>' + escapeHtml(item.employmentStatus || '-') + '</strong></div>' +
                        '<div class="col-md-4"><small class="text-muted d-block">Contract</small><strong>' + escapeHtml((item.contractType || (item.contract && item.contract.contractType) || '-').toUpperCase()) + '</strong></div>' +
                        '<div class="col-md-4"><small class="text-muted d-block">Department</small><strong>' + escapeHtml(item.departmentName || '-') + '</strong></div>' +
                        '<div class="col-md-4"><small class="text-muted d-block">Designation</small><strong>' + escapeHtml(item.designation || '-') + '</strong></div>' +
                        '<div class="col-md-4"><small class="text-muted d-block">Tax Status</small><strong>' + escapeHtml(item.taxProfile && item.taxProfile.taxStatus ? item.taxProfile.taxStatus : '-') + '</strong></div>' +
                        '</div>' +
                        '<h5 class="mb-3">Latest Background Data</h5>' +
                        '<div class="row g-3">' +
                        '<div class="col-md-6"><small class="text-muted d-block">Bank</small><strong>' + escapeHtml(item.bank && item.bank.name ? item.bank.name : '-') + '</strong></div>' +
                        '<div class="col-md-6"><small class="text-muted d-block">BPJS Kesehatan</small><strong>' + escapeHtml(item.benefits && item.benefits.bpjsKesehatanNo ? item.benefits.bpjsKesehatanNo : '-') + '</strong></div>' +
                        '<div class="col-12"><small class="text-muted d-block">Bio</small><p class="mb-0">' + escapeHtml(item.bio || '-') + '</p></div>' +
                        '</div>' +
                        '</div></div>';
                }

                var emergencyCard = document.querySelector('[data-employee-emergency-card]');
                if (emergencyCard) {
                    var contacts = Array.isArray(item.emergencyContacts) ? item.emergencyContacts : [];
                    emergencyCard.innerHTML = '<div class="card-body p-0">' +
                        (contacts.length ? contacts.map(function (contact) {
                            return '<div class="p-3 border-bottom"><div class="d-flex align-items-center justify-content-between"><div><span class="d-inline-flex align-items-center">Contact</span><h6 class="d-flex align-items-center fw-medium mt-1">' + escapeHtml(contact.name || '-') + '<span class="d-inline-flex mx-1"><i class="ti ti-point-filled text-danger"></i></span>' + escapeHtml(contact.relationship || contact.relation || '-') + '</h6></div><p class="text-dark">' + escapeHtml(contact.phone || '-') + '</p></div></div>';
                        }).join('') : '<div class="p-3 border-bottom text-muted">No emergency contact data.</div>') +
                        '</div>';
                }
            }

            function bootEmployeeDetailFallback(force) {
                var container = document.querySelector('[data-employee-details-sections]');
                if (!container) {
                    return;
                }
                if (!force && container.textContent.indexOf('Loading employee details') === -1) {
                    return;
                }
                var id = new URL(window.location.href).searchParams.get('id');
                if (!id) {
                    var meId = window.__arcav_me_id || (window.AuthUser && window.AuthUser.id) || null;
                    if (meId !== null && meId !== undefined && String(meId).trim() !== '') {
                        id = String(meId).trim();
                    }
                }
                if (!id) {
                    return;
                }
                fetch('/v1/hcm/employees/' + encodeURIComponent(id), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin'
                }).then(function (response) {
                    return response.json().catch(function () { return {}; }).then(function (payload) {
                        if (!response.ok || !payload || payload.success !== true) {
                            throw payload || {};
                        }
                        return payload;
                    });
                }).then(function (payload) {
                    renderFallback(payload.data || {});
                }).catch(function () {
                    var sections = document.querySelector('[data-employee-details-sections]');
                    if (sections && sections.textContent.indexOf('Loading employee details') !== -1) {
                        sections.innerHTML = '<div class="card"><div class="card-body"><h5 class="mb-2 text-danger">Failed to load employee details</h5><p class="mb-0 text-muted">Please refresh the page or reopen the employee from the directory.</p></div></div>';
                    }
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    window.setTimeout(function () { bootEmployeeDetailFallback(false); }, 150);
                });
            } else {
                window.setTimeout(function () { bootEmployeeDetailFallback(false); }, 150);
            }

            window.addEventListener('pageshow', function () {
                window.setTimeout(function () { bootEmployeeDetailFallback(true); }, 0);
            });
        })();
    </script>
@endsection
