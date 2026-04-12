<?php $page = 'holidays'; ?>
@extends('layout.mainlayout')
@section('content')
    <style>
        .arcav-holidays-toolbar .btn[data-hcm-holiday-sync] {
            white-space: nowrap;
            min-width: 118px;
            justify-content: center;
        }
        .arcav-holidays-toolbar [data-hcm-holiday-sync-year] {
            width: 120px;
        }
        .arcav-holiday-signal {
            border: 1px solid var(--bs-border-color);
            border-radius: 10px;
            padding: 10px 12px;
            background: var(--bs-light);
        }
    </style>

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Holidays</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                                Employee
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Holidays</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap arcav-holidays-toolbar">
                    <div class="me-2 mb-2">
                        <button type="button" class="btn btn-white d-inline-flex align-items-center" data-hcm-holiday-export>
                            <i class="ti ti-file-export me-1"></i>Export CSV
                        </button>
                    </div>
                    <div class="me-2 mb-2 d-flex align-items-center gap-2">
                        <input type="number" class="form-control" min="2000" max="2100" data-hcm-holiday-sync-year placeholder="Year">
                        <button type="button" class="btn btn-outline-secondary d-flex align-items-center" data-hcm-holiday-sync>
                            <i class="ti ti-refresh me-1"></i><span>Sync ID</span>
                        </button>
                    </div>
                    <div class="mb-2">
                        <a href="javascript:void(0);" class="btn btn-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#arcav_add_holiday"><i class="ti ti-circle-plus me-2"></i>Add Holiday</a>
                    </div>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->


            <div class="row mb-3 g-3">
                <div class="col-12">
                    <div class="card border shadow-none">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center justify-content-between flex-wrap row-gap-2">
                                <h6 class="mb-0">Monitoring Linkage Holiday -> Leave Calendar</h6>
                                <small class="text-muted">Source API update otomatis ke leave calendar, manual company tetap dipertahankan.</small>
                            </div>
                            <div class="row g-2 mt-2">
                                <div class="col-6 col-md-2">
                                    <div class="border rounded p-2 text-center">
                                        <div class="text-muted small">Holiday Rows</div>
                                        <div class="fw-semibold" data-hcm-holiday-linkage="holidayRows">0</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-2">
                                    <div class="border rounded p-2 text-center">
                                        <div class="text-muted small">Calendar Rows</div>
                                        <div class="fw-semibold" data-hcm-holiday-linkage="calendarRows">0</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-2">
                                    <div class="border rounded p-2 text-center">
                                        <div class="text-muted small">Linked</div>
                                        <div class="fw-semibold text-success" data-hcm-holiday-linkage="linkedRows">0</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-2">
                                    <div class="border rounded p-2 text-center">
                                        <div class="text-muted small">Unlinked</div>
                                        <div class="fw-semibold text-danger" data-hcm-holiday-linkage="unlinkedRows">0</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-2">
                                    <div class="border rounded p-2 text-center">
                                        <div class="text-muted small">Manual</div>
                                        <div class="fw-semibold" data-hcm-holiday-linkage="manualRows">0</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-2">
                                    <div class="border rounded p-2 text-center">
                                        <div class="text-muted small">API</div>
                                        <div class="fw-semibold" data-hcm-holiday-linkage="apiRows">0</div>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-2 mt-2">
                                <div class="col-12 col-md-6">
                                    <div class="arcav-holiday-signal h-100">
                                        <div class="text-muted small mb-1">Libur Sebelumnya</div>
                                        <div class="fw-semibold" data-hcm-holiday-prev-name>Belum ada data</div>
                                        <div class="small text-muted" data-hcm-holiday-prev-date>—</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="arcav-holiday-signal h-100">
                                        <div class="text-muted small mb-1">Libur Terdekat</div>
                                        <div class="fw-semibold" data-hcm-holiday-next-name>Belum ada data</div>
                                        <div class="small text-muted" data-hcm-holiday-next-date>—</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-2">
                    <h5 class="mb-0">Holidays List</h5>
                    <span class="text-muted small">
                        Menampilkan <span data-hcm-holidays-filtered-count>0</span> dari <span data-hcm-holidays-total-count>0</span> data
                    </span>
                </div>
                <div class="card-body border-bottom py-3" data-hcm-holidays-filters-wrap>
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-5">
                            <label class="form-label form-label-sm mb-1 text-muted">Cari Holiday</label>
                            <input type="search" class="form-control form-control-sm" placeholder="Cari judul / tanggal..." data-hcm-holidays-filter="search">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label form-label-sm mb-1 text-muted">Sumber</label>
                            <select class="form-select form-select-sm" data-hcm-holidays-filter="source">
                                <option value="">Semua sumber</option>
                                <option value="manual">Manual</option>
                                <option value="api">API</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label form-label-sm mb-1 text-muted">Status</label>
                            <select class="form-select form-select-sm" data-hcm-holidays-filter="status">
                                <option value="">Semua status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-1">
                            <button type="button" class="btn btn-sm btn-light border w-100" data-hcm-holidays-filter-reset>
                                <i class="ti ti-rotate me-1"></i>Reset
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="custom-datatable-filter table-responsive">
                        <table class="table">
                            <thead class="thead-light">
                                <tr>
                                    <th class="no-sort">
                                        <div class="form-check form-check-md">
                                            <input class="form-check-input" type="checkbox" id="select-all">
                                        </div>
                                    </th>
                                    <th>Title</th>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Source</th>
                                    <th>Status</th>
                                    <th>Synced At</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-hcm-holidays-body>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Loading…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>



    </div>
    <!-- /Page Wrapper -->

    @component('components.modal-popup')
    @endcomponent

    @include('hcm.partials.holiday-modals')

@endsection