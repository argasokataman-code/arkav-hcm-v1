<?php $page = 'payslip-report'; ?>
@extends('layout.mainlayout')
@section('content')

<div class="page-wrapper">
    <div class="content">
        <div
            data-payslip-org-meta
            data-app-name="{{ $companyName }}"
            data-org-address="{{ $companyAddress }}"
        ></div>

        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Payslip Report</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item">Payroll</li>
                        <li class="breadcrumb-item active" aria-current="page">Payslip Report</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                <button type="button" class="btn btn-primary d-flex align-items-center" data-payslip-admin-send-selected disabled>
                    <i class="ti ti-mail-forward me-2"></i>Kirim Email Terpilih
                </button>
                <div class="head-icons">
                    <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                        <i class="ti ti-chevrons-up"></i>
                    </a>
                </div>
            </div>
        </div>


        {{-- Period filter --}}
        <div class="card mb-4">
            <div class="card-header border-0 pb-0">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h5 class="mb-1">Filter Data Payslip</h5>
                        <p class="text-muted small mb-0">Gunakan filter periode atau mode snapshot untuk meninjau slip yang siap dipreview dan dikirim email.</p>
                    </div>
                    <span class="badge bg-light text-dark" data-payslip-admin-source-badge>Source: Live</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row align-items-end g-3">
                    <div class="col-md-3">
                        <label class="form-label">Sumber Data</label>
                        <select class="form-select" data-payslip-admin-source>
                            <option value="live" selected>Live Data</option>
                            <option value="archive">Archive Snapshot</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-none" data-payslip-admin-snapshot-wrap>
                        <label class="form-label">Snapshot ID</label>
                        <input type="number" class="form-control" data-payslip-admin-snapshot-id min="1" placeholder="Contoh: 12">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tahun</label>
                        <select class="form-select" data-payslip-admin-year>
                            @php($currentYear = (int) date('Y'))
                            @for ($y = $currentYear + 1; $y >= $currentYear - 6; $y--)
                                <option value="{{ $y }}" {{ $y === $currentYear ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Bulan (opsional)</label>
                        <select class="form-select" data-payslip-admin-month>
                            <option value="">Semua bulan</option>
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}">
                                    {{ $m }} — {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                                </option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button type="button" class="btn btn-primary px-4" data-payslip-admin-load>
                            <i class="ti ti-refresh me-1"></i>Muat
                        </button>
                    </div>
                    <div class="col-lg-4 ms-lg-auto">
                        <div class="border rounded p-3 bg-light-subtle">
                            {{-- Note: Live = payroll admin API, Archive = Snapshot ID dari Reports Hub --}}
                            <div class="small" data-payslip-admin-run-info>Belum ada data dimuat.</div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-danger d-none py-2 small mt-3 mb-0" data-payslip-admin-error></div>
            </div>
        </div>

        {{-- Summary cards --}}
        <div class="row g-3 mb-4" data-payslip-admin-summary style="display:none!important;">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="avatar avatar-lg bg-soft-primary text-primary rounded-circle"><i class="ti ti-users fs-18"></i></span>
                        <div>
                            <p class="text-muted small mb-0">Jumlah slip</p>
                            <h4 class="mb-0" data-payslip-admin-count>0</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="avatar avatar-lg bg-soft-success text-success rounded-circle"><i class="ti ti-users-group fs-18"></i></span>
                        <div>
                            <p class="text-muted small mb-0">Jumlah karyawan unik</p>
                            <h4 class="mb-0" data-payslip-admin-employees>0</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="avatar avatar-lg bg-soft-info text-info rounded-circle"><i class="ti ti-receipt fs-18"></i></span>
                        <div>
                            <p class="text-muted small mb-0">Jumlah periode / total net pay</p>
                            <h6 class="mb-1" data-payslip-admin-periods>0</h6>
                            <h5 class="mb-0 text-primary" data-payslip-admin-total-net>Rp 0</h5>
                            <div class="small text-info">Overtime: <strong data-payslip-admin-total-overtime>Rp 0</strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Payslip list --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                <h5 class="mb-0">Daftar slip gaji karyawan</h5>
                <span class="badge bg-light text-dark">Preview tersedia per baris</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-nowrap table-hover mb-0 align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:40px;">
                                    <div class="form-check form-check-md mb-0">
                                        <input class="form-check-input" type="checkbox" data-payslip-admin-select-all>
                                    </div>
                                </th>
                                <th>Periode</th>
                                <th>Run</th>
                                <th>Pembayaran</th>
                                <th>Karyawan</th>
                                <th>Jabatan / Tim</th>
                                <th class="text-end">Penghasilan</th>
                                <th class="text-end">Overtime</th>
                                <th class="text-end">Potongan</th>
                                <th class="text-end">Net Pay</th>
                                <th>Status Email</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody data-payslip-admin-body>
                            <tr>
                                <td colspan="12" class="text-center text-muted py-5">Memuat data payslip lintas periode…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Preview modal --}}
<div class="modal fade" id="payslip_admin_preview_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h4 class="modal-title">Preview Slip Gaji <span class="text-muted">•</span> <span class="text-primary" data-payslip-preview-name></span></h4>
                <button type="button" class="btn-close custom-btn-close" data-bs-dismiss="modal"><i class="ti ti-x"></i></button>
            </div>
            <div class="modal-body bg-light-subtle" data-payslip-preview-body>
                <p class="text-muted text-center py-4">Memuat…</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-white border me-2" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" data-payslip-preview-send>
                    <i class="ti ti-mail-forward me-1"></i>Kirim Email
                </button>
            </div>
        </div>
    </div>
</div>

@endsection
