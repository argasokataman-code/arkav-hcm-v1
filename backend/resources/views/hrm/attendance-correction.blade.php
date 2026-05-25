<?php $page = 'attendance-correction'; ?>
@extends('layout.mainlayout')
@section('content')
    <style>
        [data-correction-body]:not([data-hydrated="1"]) { display: none; }
        .correction-row-approved { opacity: 0.45; transition: opacity 0.3s; }
        .correction-status-badge { min-width: 80px; }
    </style>

    <div class="page-wrapper">
        <div class="content">

            <!-- Breadcrumb -->
            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Attendance Correction</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a></li>
                            <li class="breadcrumb-item">Attendance</li>
                            <li class="breadcrumb-item active" aria-current="page">Attendance Correction</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                    <div class="me-2 mb-2">
                        <a href="{{ url('attendance-admin') }}" class="btn btn-light d-flex align-items-center">
                            <i class="ti ti-calendar-event me-1"></i>Attendance Admin
                        </a>
                    </div>
                    <div class="ms-2 head-icons">
                        <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Breadcrumb -->

            <!-- Summary Stats -->
            <div class="card border-0 mb-3">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-5">
                            <h4 class="mb-1">Pending Corrections</h4>
                            <p class="text-muted mb-0" data-correction-subtitle>Memuat...</p>
                        </div>
                        <div class="col-md-7">
                            <div class="d-flex align-items-center justify-content-md-end flex-wrap gap-3">
                                <div class="d-flex align-items-center" title="Total request pending">
                                    <h6 class="mb-0 me-2 text-muted">Total Pending</h6>
                                    <span class="avatar bg-warning avatar-rounded text-dark fs-12 d-inline-flex align-items-center justify-content-center"
                                        style="min-width:2.25rem; min-height:2.25rem;" data-correction-stat-total>—</span>
                                </div>
                                <div class="d-flex align-items-center" title="Jumlah karyawan dengan pending correction">
                                    <h6 class="mb-0 me-2 text-muted">Karyawan</h6>
                                    <span class="avatar bg-info avatar-rounded text-white fs-12 d-inline-flex align-items-center justify-content-center"
                                        style="min-width:2.25rem; min-height:2.25rem;" data-correction-stat-employees>—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main correction table -->
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <h5 class="d-flex align-items-center gap-2 mb-0">
                        <i class="ti ti-clock-edit text-warning"></i>
                        Daftar Correction Request
                        <span class="badge bg-warning text-dark" data-correction-total-badge>0</span>
                    </h5>
                    <div class="d-flex align-items-center flex-wrap gap-3">
                        <div>
                            <input type="text" class="form-control" placeholder="Cari karyawan…"
                                data-correction-filter-name style="min-width:180px;">
                        </div>
                        <div>
                            <input type="date" class="form-control" data-correction-filter-date-from
                                title="Dari tanggal">
                        </div>
                        <div>
                            <input type="date" class="form-control" data-correction-filter-date-to
                                title="Sampai tanggal">
                        </div>
                        <div>
                            <button type="button" class="btn btn-light" data-correction-filter-reset>
                                <i class="ti ti-x me-1"></i>Reset Filter
                            </button>
                        </div>
                        <div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-correction-refresh title="Refresh">
                                <i class="ti ti-refresh"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="correction-table">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width:180px">Karyawan</th>
                                    <th style="width:110px">Tanggal</th>
                                    <th style="width:100px">Check In</th>
                                    <th style="width:100px">Check Out</th>
                                    <th style="width:80px">Break</th>
                                    <th style="width:80px">Terlambat</th>
                                    <th>Alasan Koreksi</th>
                                    <th style="width:120px">Diminta Pada</th>
                                    <th style="width:200px" class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody data-correction-tbody>
                                <tr data-correction-loading>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                        Memuat data koreksi...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2"
                    data-correction-pagination style="display:none !important;">
                    <span class="text-muted small" data-correction-page-info></span>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-light border" data-correction-prev>Sebelumnya</button>
                        <button type="button" class="btn btn-sm btn-light border" data-correction-next>Berikutnya</button>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <!-- /Page Wrapper -->

    <!-- Correction Detail Modal -->
    <div class="modal fade" id="arcav_correction_detail_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ti ti-clock-edit me-1 text-warning"></i>Detail Correction Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label text-muted small mb-1">Karyawan</label>
                            <p class="fw-semibold mb-0" data-correction-modal-name>—</p>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label text-muted small mb-1">Tanggal Kerja</label>
                            <p class="fw-semibold mb-0" data-correction-modal-date>—</p>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-3">
                            <label class="form-label text-muted small mb-1">Check In</label>
                            <p class="fw-semibold mb-0" data-correction-modal-checkin>—</p>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label text-muted small mb-1">Check Out</label>
                            <p class="fw-semibold mb-0" data-correction-modal-checkout>—</p>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label text-muted small mb-1">Break</label>
                            <p class="fw-semibold mb-0" data-correction-modal-break>—</p>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label text-muted small mb-1">Terlambat</label>
                            <p class="fw-semibold mb-0" data-correction-modal-late>—</p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Alasan Koreksi</label>
                        <div class="border rounded p-3 bg-light" data-correction-modal-reason>—</div>
                    </div>
                    <div>
                        <label class="form-label text-muted small mb-1">Diminta pada</label>
                        <p class="text-muted small mb-0" data-correction-modal-requested-at>—</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                    <a href="#" class="btn btn-outline-primary" data-correction-modal-goto-admin target="_self">
                        <i class="ti ti-calendar-event me-1"></i>Buka di Attendance Admin
                    </a>
                    <button type="button" class="btn btn-outline-danger" data-correction-modal-dismiss>
                        <i class="ti ti-x me-1"></i>Tolak
                    </button>
                    <button type="button" class="btn btn-success" data-correction-modal-approve>
                        <i class="ti ti-check me-1"></i>Setujui
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- /Correction Detail Modal -->

    @component('components.modal-popup')
    @endcomponent

@endsection
