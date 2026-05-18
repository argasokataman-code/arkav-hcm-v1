<?php $page = 'monthly-report'; ?>
@extends('layout.mainlayout')
@section('content')

<div class="page-wrapper">
    <div class="content">
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Monthly Report</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item">Reports</li>
                        <li class="breadcrumb-item active" aria-current="page">Monthly Report</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary" data-monthly-report-export="csv">
                    <i class="ti ti-file-text me-1"></i>Export CSV
                </button>
                <button type="button" class="btn btn-primary" data-monthly-report-export="xlsx">
                    <i class="ti ti-file-spreadsheet me-1"></i>Export Excel
                </button>
            </div>
        </div>

        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header border-0 pb-0">
                <h5 class="mb-1">Filter Laporan Bulanan</h5>
                <p class="text-muted small mb-0">Gabungkan payroll monthly, THR, dan kompensasi PKWT per bulan kalender dengan detail per karyawan.</p>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Tahun</label>
                        <select class="form-select" data-monthly-report-year>
                            @php($currentYear = (int) date('Y'))
                            @for ($y = $currentYear + 1; $y >= $currentYear - 6; $y--)
                                <option value="{{ $y }}" {{ $y === $currentYear ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Bulan</label>
                        <select class="form-select" data-monthly-report-month>
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ $m === (int) date('n') ? 'selected' : '' }}>
                                    {{ $m }} — {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                                </option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button type="button" class="btn btn-primary px-4" data-monthly-report-load>
                            <i class="ti ti-refresh me-1"></i>Muat
                        </button>
                    </div>
                    <div class="col-lg-5 ms-lg-auto">
                        <div class="border rounded p-3 bg-light-subtle small text-muted" data-monthly-report-info>
                            Memuat laporan payroll bulanan detail…
                        </div>
                    </div>
                </div>
                <div class="alert alert-danger d-none py-2 small mt-3 mb-0" data-monthly-report-error></div>
            </div>
        </div>

        <div class="row g-3 mb-4" data-monthly-report-summary style="display:none!important;">
            <div class="col-md-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <p class="text-muted small mb-1">Baris laporan</p>
                        <h4 class="mb-0" data-monthly-report-total-rows>0</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <p class="text-muted small mb-1">Karyawan unik / periode</p>
                        <h6 class="mb-1"><span data-monthly-report-total-employees>0</span> karyawan</h6>
                        <h6 class="mb-0"><span data-monthly-report-total-periods>0</span> periode</h6>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <p class="text-muted small mb-1">Total net payroll</p>
                        <h4 class="mb-0 text-primary" data-monthly-report-total-net>Rp 0</h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <p class="text-muted small mb-1">Breakdown purpose</p>
                        <div class="small text-muted">Monthly: <strong data-monthly-report-total-monthly>Rp 0</strong></div>
                        <div class="small text-muted">THR: <strong data-monthly-report-total-thr>Rp 0</strong></div>
                        <div class="small text-muted">PKWT: <strong data-monthly-report-total-pkwt>Rp 0</strong></div>
                        <div class="small text-muted">Overtime: <strong class="text-info" data-monthly-report-total-overtime>Rp 0</strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Monthly Payroll Detail</h5>
                <span class="badge bg-light text-dark">Breakdown monthly, THR, PKWT per karyawan</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-nowrap table-hover mb-0 align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th>Periode</th>
                                <th>Karyawan</th>
                                <th>Bank</th>
                                <th>Pembayaran</th>
                                <th class="text-end">Monthly Net</th>
                                <th class="text-end">Monthly OT</th>
                                <th class="text-end">THR Net</th>
                                <th class="text-end">PKWT Net</th>
                                <th class="text-end">Total Net</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody data-monthly-report-body>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-5">Memuat laporan…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="monthly_report_detail_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h4 class="modal-title">Monthly Report Detail <span class="text-muted">•</span> <span class="text-primary" data-monthly-report-detail-title></span></h4>
                <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal"><i class="ti ti-x"></i></button>
            </div>
            <div class="modal-body bg-light-subtle" data-monthly-report-detail-body>
                <p class="text-muted text-center py-4">Memuat detail…</p>
            </div>
        </div>
    </div>
</div>

@endsection