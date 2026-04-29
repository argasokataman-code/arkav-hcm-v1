<?php $page = 'leaves-employee'; ?>
@extends('layout.mainlayout')
@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">My Leaves</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">Employee</li>
                            <li class="breadcrumb-item active" aria-current="page">Leaves</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center" data-hcm-leaves-export>
                        <i class="ti ti-file-export me-1"></i>Export CSV
                    </button>
                    <a href="javascript:void(0);" class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#arcav_add_leave">
                        <i class="ti ti-circle-plus me-2"></i>Add Leave
                    </a>
                    <div class="head-icons ms-1">
                        <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <!-- Leave Balance Cards -->
            <div class="row g-3 mb-3">
                <div class="col-xl-3 col-md-6">
                    <div class="card mb-0 h-100 bg-black-le">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="mb-1 small">Annual Leaves</p>
                                    <h4 class="mb-1" data-hcm-leaves-balance-card="annual">—</h4>
                                    <span class="badge bg-secondary-transparent">
                                        Sisa: <span data-hcm-leaves-balance-remaining="annual">—</span>
                                    </span>
                                </div>
                                <span class="avatar avatar-md d-flex opacity-75">
                                    <i class="ti ti-calendar-event fs-28"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card mb-0 h-100 bg-blue-le">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="mb-1 small">Medical Leaves</p>
                                    <h4 class="mb-1" data-hcm-leaves-balance-card="medical">—</h4>
                                    <span class="badge bg-info-transparent">
                                        Sisa: <span data-hcm-leaves-balance-remaining="medical">—</span>
                                    </span>
                                </div>
                                <span class="avatar avatar-md d-flex opacity-75">
                                    <i class="ti ti-vaccine fs-28"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card mb-0 h-100 bg-purple-le">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="mb-1 small">Casual Leaves</p>
                                    <h4 class="mb-1" data-hcm-leaves-balance-card="casual">—</h4>
                                    <span class="badge bg-transparent-purple">
                                        Sisa: <span data-hcm-leaves-balance-remaining="casual">—</span>
                                    </span>
                                </div>
                                <span class="avatar avatar-md d-flex opacity-75">
                                    <i class="ti ti-hexagon-letter-c fs-28"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card mb-0 h-100 bg-pink-le">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="mb-1 small">Other Leaves</p>
                                    <h4 class="mb-1" data-hcm-leaves-balance-card="other">—</h4>
                                    <span class="badge bg-pink-transparent">
                                        Sisa: <span data-hcm-leaves-balance-remaining="other">—</span>
                                    </span>
                                </div>
                                <span class="avatar avatar-md d-flex opacity-75">
                                    <i class="ti ti-hexagonal-prism-plus fs-28"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Leave Balance Cards -->

            <!-- Leave List Card -->
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <h5 class="mb-0">Leave History</h5>
                </div>

                <!-- Filters -->
                <div class="card-body p-3 border-bottom" data-hcm-leaves-filters>
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-sm-6 col-md-3 col-xl-2">
                            <label class="form-label form-label-sm mb-1 text-muted">Tipe Cuti</label>
                            <select class="form-select form-select-sm" data-hcm-leaves-filter="leaveType">
                                <option value="">Semua tipe</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-2 col-xl-2">
                            <label class="form-label form-label-sm mb-1 text-muted">Status</label>
                            <select class="form-select form-select-sm" data-hcm-leaves-filter="status">
                                <option value="">Semua status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="declined">Declined</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-2 col-xl-2">
                            <label class="form-label form-label-sm mb-1 text-muted">Dari</label>
                            <input type="date" class="form-control form-control-sm" data-hcm-leaves-filter="dateFrom">
                        </div>
                        <div class="col-12 col-sm-6 col-md-2 col-xl-2">
                            <label class="form-label form-label-sm mb-1 text-muted">Sampai</label>
                            <input type="date" class="form-control form-control-sm" data-hcm-leaves-filter="dateTo">
                        </div>
                        <div class="col-12 col-sm-auto">
                            <button type="button" class="btn btn-sm btn-light border" data-hcm-leaves-filter-reset>
                                <i class="ti ti-rotate me-1"></i>Reset
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Holiday strip (shown only when holiday data exists) -->
                <div class="px-3 py-2 border-bottom bg-light d-flex align-items-center flex-wrap gap-2"
                     data-hcm-leave-holiday-panel style="display:none;">
                    <i class="ti ti-calendar-event text-muted fs-14"></i>
                    <small class="text-muted fw-semibold">Libur Mendatang:</small>
                    <div class="d-flex flex-wrap gap-1" data-hcm-leave-holiday-list></div>
                </div>

                <!-- Table -->
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th class="no-sort ps-3">
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox" id="select-all">
                                        </div>
                                    </th>
                                    <th>Leave Type</th>
                                    <th>From</th>
                                    <th>To</th>
                                    <th>Days</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-hcm-leaves-me-body>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <span class="spinner-border spinner-border-sm me-2 align-middle"></span>Loading…
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2"
                     data-hcm-leaves-pagination style="display: none;">
                    <span class="text-muted small" data-hcm-leaves-page-info></span>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-light border" data-hcm-leaves-prev>
                            <i class="ti ti-chevron-left me-1"></i>Sebelumnya
                        </button>
                        <button type="button" class="btn btn-light border" data-hcm-leaves-next>
                            Berikutnya<i class="ti ti-chevron-right ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
            <!-- /Leave List Card -->

        </div>
    </div>
    <!-- /Page Wrapper -->

    @component('components.modal-popup')
    @endcomponent

    @include('hcm.partials.leave-modals', ['arcavLeaveAdmin' => false])

@endsection