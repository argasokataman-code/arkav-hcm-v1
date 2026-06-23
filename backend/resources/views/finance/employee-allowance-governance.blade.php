<?php $page = 'employee-allowance-governance'; ?>
@php
    $allowanceGovernanceScreen = $allowanceGovernanceScreen ?? 'landing';
@endphp
@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper" role="main" aria-label="Employee Allowance Governance" data-allowance-governance-page data-allowance-screen="{{ $allowanceGovernanceScreen }}">
    <div class="content">
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Employee Allowance Governance</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item">Payroll</li>
                        <li class="breadcrumb-item active" aria-current="page">Allowance Governance</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="alert alert-info d-flex align-items-start gap-2 mb-3" role="alert">
            <i class="ti ti-info-circle mt-1"></i>
            <div>
                <div class="fw-semibold">Allowance baseline default Indonesia sudah aktif</div>
                <div class="small">Sistem menyediakan starter policy tunjangan umum. Tenant bisa override penuh sesuai kebijakan bisnis.</div>
            </div>
        </div>

        <div class="d-flex flex-wrap gy-2 justify-content-between my-4">
            <ul class="nav nav-pills gap-2" role="navigation" aria-label="Submenu Allowance Governance">
                <li class="nav-item">
                    <a href="{{ route('employee-allowance-governance.index') }}" class="nav-link btn btn-white {{ $allowanceGovernanceScreen === 'landing' ? 'active' : '' }}">Overview</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('employee-allowance-governance.policies') }}" class="nav-link btn btn-white {{ $allowanceGovernanceScreen === 'policies' ? 'active' : '' }}">Policy Setup</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('employee-allowance-governance.assignments') }}" class="nav-link btn btn-white {{ $allowanceGovernanceScreen === 'assignments' ? 'active' : '' }}">Assignments</a>
                </li>
            </ul>
        </div>

        <div class="alert alert-danger d-none" data-allowance-error></div>

        @if ($allowanceGovernanceScreen === 'landing')
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <div class="card h-100"><div class="card-body"><p class="text-muted mb-1 fs-13">Policy Aktif</p><h4 class="fw-semibold mb-0" data-allowance-kpi-policy>0</h4></div></div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card h-100"><div class="card-body"><p class="text-muted mb-1 fs-13">Karyawan Scope Payroll</p><h4 class="fw-semibold mb-0" data-allowance-kpi-employees>0</h4></div></div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card h-100"><div class="card-body"><p class="text-muted mb-1 fs-13">Skor Compliance</p><h4 class="fw-semibold mb-0" data-allowance-kpi-score>0%</h4></div></div>
                </div>
            </div>
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Checklist Compliance</h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-allowance-report-export><i class="ti ti-file-download me-1"></i>Export JSON</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-allowance-report-refresh><i class="ti ti-refresh me-1"></i>Refresh</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Checklist</th><th>Status</th><th>Evidence</th></tr></thead>
                            <tbody data-allowance-report-checks>
                                <tr><td colspan="3" class="text-center text-muted py-4">Memuat laporan compliance...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @elseif ($allowanceGovernanceScreen === 'policies')
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Allowance Policy Setup</h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary" data-allowance-policy-add><i class="ti ti-plus me-1"></i>Tambah Policy</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-allowance-policy-refresh><i class="ti ti-refresh me-1"></i>Refresh</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama</th>
                                    <th>Mandatory</th>
                                    <th>Taxable</th>
                                    <th>Nominal Default</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody data-allowance-policy-body>
                                <tr><td colspan="7" class="text-center text-muted py-4">Memuat policy...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-header"><h6 class="mb-0">Riwayat Perubahan Policy</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Waktu</th><th>Aksi</th><th>Policy</th><th>Status</th><th>Pelaku</th></tr></thead>
                            <tbody data-allowance-policy-history-body>
                                <tr><td colspan="5" class="text-center text-muted py-4">Memuat riwayat...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @elseif ($allowanceGovernanceScreen === 'assignments')
            <div class="alert alert-info d-flex align-items-center gap-2 mb-3" role="alert">
                <i class="ti ti-info-circle fs-16"></i>
                <span>Tab ini hanya menampilkan data assignment tunjangan. Untuk menambah atau mengubah assignment karyawan, gunakan halaman <a href="{{ url('employee-salary') }}" class="alert-link">Employee Salary</a>.</span>
            </div>
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h5 class="mb-0">Employee Allowance Assignments</h5>
                    <div class="d-flex gap-2">
                        <input type="search" class="form-control form-control-sm" data-allowance-assignment-search placeholder="Cari karyawan/policy...">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-allowance-assignment-refresh><i class="ti ti-refresh me-1"></i>Refresh</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Karyawan</th><th>Policy</th><th>Nominal Override</th><th>Periode</th><th>Status</th></tr></thead>
                            <tbody data-allowance-assignment-body>
                                <tr><td colspan="5" class="text-center text-muted py-4">Memuat assignment...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="allowancePolicyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" data-allowance-policy-modal-title>Tambah Allowance Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form data-allowance-policy-form>
                <div class="modal-body">
                    <input type="hidden" data-allowance-policy-id>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Kode</label>
                            <div class="invalid-feedback">This field is required.</div><input type="text" class="form-control" data-allowance-policy-code required></div>
                        <div class="col-md-6"><label class="form-label">Nama</label>
                            <div class="invalid-feedback">This field is required.</div><input type="text" class="form-control" data-allowance-policy-name required></div>
                        <div class="col-md-4"><label class="form-label">Nominal Default</label><input type="number" min="0" step="0.01" class="form-control" data-allowance-policy-default-amount></div>
                        <div class="col-md-4"><label class="form-label">Mulai Berlaku</label><input type="date" class="form-control" data-allowance-policy-start required>
                            <div class="invalid-feedback">Please select a date.</div></div>
                        <div class="col-md-4"><label class="form-label">Akhir Berlaku</label><input type="date" class="form-control" data-allowance-policy-end></div>
                        <div class="col-md-4">
                            <label class="form-label">Mandatory</label>
                            <select class="form-select" data-allowance-policy-mandatory>
                                <option value="1">Ya</option>
                                <option value="0">Tidak</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Taxable</label>
                            <select class="form-select" data-allowance-policy-taxable>
                                <option value="1">Ya</option>
                                <option value="0">Tidak</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" data-allowance-policy-status>
                                <option value="active">Active</option>
                                <option value="draft">Draft</option>
                                <option value="superseded">Superseded</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label">Catatan</label><textarea class="form-control" rows="2" data-allowance-policy-notes></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>


@endsection
