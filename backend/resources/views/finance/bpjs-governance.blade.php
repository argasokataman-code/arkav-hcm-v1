<?php $page = 'bpjs-governance'; ?>
@php
    $bpjsGovernanceScreen = $bpjsGovernanceScreen ?? 'landing';
@endphp
@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper" role="main" aria-label="BPJS Governance" data-bpjs-governance-page data-bpjs-screen="{{ $bpjsGovernanceScreen }}">
    <div class="content">
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">BPJS Governance</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">Settings</li>
                        <li class="breadcrumb-item">BPJS Governance</li>
                        <li class="breadcrumb-item active" aria-current="page">BPJS Governance</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                <div class="me-2 mb-2">
                    <a href="javascript:void(0);" class="btn btn-light d-inline-flex align-items-center"
                       data-bs-toggle="modal" data-bs-target="#bpjsGuideModal">
                        <i class="ti ti-info-circle me-1"></i>Panduan
                    </a>
                </div>
                <div class="head-icons ms-2">
                    <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                        <i class="ti ti-chevrons-up"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gy-2 justify-content-between my-4">
            <ul class="nav nav-pills gap-2" role="navigation" aria-label="Submenu BPJS Governance">
                <li class="nav-item">
                    <a href="{{ route('bpjs-governance.index') }}"
                       class="nav-link btn btn-white {{ $bpjsGovernanceScreen === 'landing' ? 'active' : '' }}"
                       aria-current="{{ $bpjsGovernanceScreen === 'landing' ? 'page' : 'false' }}">Overview</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('bpjs-governance.policies') }}"
                       class="nav-link btn btn-white {{ $bpjsGovernanceScreen === 'policies' ? 'active' : '' }}"
                       aria-current="{{ $bpjsGovernanceScreen === 'policies' ? 'page' : 'false' }}">Policy Setup</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('bpjs-governance.employee-membership') }}"
                       class="nav-link btn btn-white {{ $bpjsGovernanceScreen === 'employee-membership' ? 'active' : '' }}"
                       aria-current="{{ $bpjsGovernanceScreen === 'employee-membership' ? 'page' : 'false' }}">Employee Membership</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('bpjs-governance.reports') }}"
                       class="nav-link btn btn-white {{ $bpjsGovernanceScreen === 'reports' ? 'active' : '' }}"
                       aria-current="{{ $bpjsGovernanceScreen === 'reports' ? 'page' : 'false' }}">Compliance Reports</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('bpjs-governance.rate-baselines') }}"
                       class="nav-link btn btn-white {{ $bpjsGovernanceScreen === 'rate-baselines' ? 'active' : '' }}"
                       aria-current="{{ $bpjsGovernanceScreen === 'rate-baselines' ? 'page' : 'false' }}">Konfigurasi Baseline</a>
                </li>
            </ul>
        </div>

        @if ($bpjsGovernanceScreen === 'landing')
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <p class="text-muted mb-1 fs-13">Program Aktif</p>
                        <h4 class="fw-semibold mb-0" data-bpjs-kpi-programs>0</h4>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <p class="text-muted mb-1 fs-13">Rata-rata Iuran Pekerja</p>
                        <h4 class="fw-semibold mb-0" data-bpjs-kpi-employee-rate>0%</h4>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <p class="text-muted mb-1 fs-13">Karyawan Dengan Membership Lengkap</p>
                        <h4 class="fw-semibold mb-0" data-bpjs-kpi-membership>0/0</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Ringkasan Program BPJS</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Program</th>
                                        <th>Iuran Pekerja</th>
                                        <th>Iuran Perusahaan</th>
                                    </tr>
                                </thead>
                                <tbody data-bpjs-policy-summary-body>
                                    <tr><td colspan="3" class="text-muted text-center py-3">Memuat data BPJS...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">BPJS Compliance Reports</h5>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bpjs-report-export>
                                <i class="ti ti-file-download me-1"></i>Export JSON
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bpjs-report-refresh>
                                <i class="ti ti-refresh me-1"></i>Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Checklist</th>
                                        <th>Status</th>
                                        <th>Evidence</th>
                                    </tr>
                                </thead>
                                <tbody data-bpjs-report-checklist-body>
                                    <tr><td colspan="3" class="text-center text-muted py-3">Menyusun laporan kepatuhan...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Skor Kepatuhan</h5></div>
                    <div class="card-body">
                        <h2 class="fw-bold mb-1" data-bpjs-report-score>0%</h2>
                        <p class="text-muted mb-3" data-bpjs-report-summary>Belum ada evaluasi compliance.</p>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar" role="progressbar" style="width: 0%" aria-valuemin="0" aria-valuemax="100" data-bpjs-report-score-bar></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @elseif ($bpjsGovernanceScreen === 'policies')
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">BPJS Policy Setup</h5>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-primary" data-bpjs-policy-add>
                        <i class="ti ti-plus me-1"></i>Tambah Policy
                    </button>
                    <button type="button" class="btn btn-sm btn-light" data-bpjs-policy-refresh>
                        <i class="ti ti-refresh me-1"></i>Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Program</th>
                                <th>Porsi</th>
                                <th>Tarif</th>
                                <th>Salary Cap</th>
                                <th>Basis Upah</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody data-bpjs-policy-table-body>
                            <tr><td colspan="8" class="text-center text-muted py-4">Memuat policy BPJS...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0">Riwayat Perubahan Kebijakan</h6>
                <small class="text-muted">50 perubahan terakhir</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Aksi</th>
                                <th>Program</th>
                                <th>Porsi</th>
                                <th>Tarif</th>
                                <th>Pelaku</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody data-bpjs-policy-history-body>
                            <tr><td colspan="7" class="text-center text-muted py-4">Memuat riwayat perubahan...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @elseif ($bpjsGovernanceScreen === 'employee-membership')
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Employee BPJS Membership</h5>
                <div class="d-flex align-items-center gap-2">
                    <input type="search" class="form-control form-control-sm" placeholder="Cari karyawan..." data-bpjs-employee-search>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bpjs-employee-refresh>
                        <i class="ti ti-refresh me-1"></i>Refresh
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="px-3 py-2 border-bottom bg-light">
                    <span class="text-muted small" data-bpjs-employee-summary>0 karyawan</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Karyawan</th>
                                <th>BPJS Kesehatan</th>
                                <th>BPJS Ketenagakerjaan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody data-bpjs-employee-membership-body>
                            <tr><td colspan="5" class="text-center text-muted py-4">Memuat membership karyawan...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @elseif ($bpjsGovernanceScreen === 'reports')
        <div class="row g-3">
            <div class="col-12 col-xl-7">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">BPJS Compliance Reports</h5>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bpjs-report-export>
                                <i class="ti ti-file-download me-1"></i>Export JSON
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bpjs-report-refresh>
                                <i class="ti ti-refresh me-1"></i>Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Checklist</th>
                                        <th>Status</th>
                                        <th>Evidence</th>
                                    </tr>
                                </thead>
                                <tbody data-bpjs-report-checklist-body>
                                    <tr><td colspan="3" class="text-center text-muted py-3">Menyusun laporan kepatuhan...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-5">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0">Skor Kepatuhan</h5></div>
                    <div class="card-body">
                        <h2 class="fw-bold mb-1" data-bpjs-report-score>0%</h2>
                        <p class="text-muted mb-3" data-bpjs-report-summary>Belum ada evaluasi compliance.</p>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar" role="progressbar" style="width: 0%" aria-valuemin="0" aria-valuemax="100" data-bpjs-report-score-bar></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @elseif ($bpjsGovernanceScreen === 'rate-baselines')
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-1">Konfigurasi Baseline Tarif BPJS</h5>
                    <p class="text-muted small mb-0">
                        Override tarif minimum/maksimum per program. Jika tidak dikonfigurasi, sistem menggunakan default regulasi nasional.
                    </p>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bpjs-baseline-refresh>
                    <i class="ti ti-refresh me-1"></i>Refresh
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Program</th>
                                <th>Porsi</th>
                                <th>Tarif Min (%)</th>
                                <th>Tarif Maks (%)</th>
                                <th>Basis Upah</th>
                                <th>Sumber</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody data-bpjs-baseline-table-body>
                            <tr><td colspan="7" class="text-center text-muted py-4">Memuat konfigurasi baseline...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <div class="modal fade" id="bpjsRateBaselineModal" tabindex="-1" aria-labelledby="bpjsRateBaselineModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bpjsRateBaselineModalLabel">Edit Baseline Tarif</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form data-bpjs-baseline-form>
                        <input type="hidden" data-bpjs-baseline-program>
                        <input type="hidden" data-bpjs-baseline-party>
                        <div class="modal-body">
                            <p class="text-muted small mb-3">
                                Program: <strong data-bpjs-baseline-program-label></strong>
                            </p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Tarif Minimum (%)</label>
                                    <input type="number" class="form-control" step="0.0001" min="0" max="100" data-bpjs-baseline-min-rate required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tarif Maksimum (%)</label>
                                    <input type="number" class="form-control" step="0.0001" min="0" max="100" data-bpjs-baseline-max-rate required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Basis Upah</label>
                                    <input type="hidden" data-bpjs-baseline-wage-base>
                                    <input type="text" class="form-control bg-body-secondary" data-bpjs-baseline-wage-base-label readonly
                                           title="Basis upah ditetapkan oleh regulasi pemerintah dan tidak dapat diubah.">
                                    <div class="form-text text-muted"><i class="ti ti-lock me-1"></i>Ditetapkan regulasi — tidak dapat diubah.</div>
                                </div>
                                {{-- JKK: Kategori Risiko (hanya tampil jika program = jkk & porsi = employer) --}}
                                <div class="col-md-12 d-none" data-bpjs-baseline-jkk-section>
                                    <label class="form-label fw-semibold">Kategori Risiko JKK</label>
                                    <select class="form-select" data-bpjs-baseline-risk-category>
                                        <option value="">— Pilih Kategori —</option>
                                        <option value="1">1 — Sangat Rendah (0,24%)</option>
                                        <option value="2">2 — Rendah (0,54%)</option>
                                        <option value="3">3 — Sedang (0,89%)</option>
                                        <option value="4">4 — Tinggi (1,27%)</option>
                                        <option value="5">5 — Sangat Tinggi (1,74%)</option>
                                    </select>
                                    <div class="form-text text-muted">PP No. 44/2015 — tarif ditentukan sesuai jenis usaha.</div>
                                </div>
                                {{-- JP salary cap (hanya tampil jika program = jp) --}}
                                <div class="col-md-12 d-none" data-bpjs-baseline-jp-cap-section>
                                    <label class="form-label fw-semibold">Batas Atas Upah JP (Rp)</label>
                                    <input type="number" class="form-control" min="0" step="1"
                                           data-bpjs-baseline-jp-salary-cap
                                           placeholder="Kosongkan = gunakan default sistem (PP 45/2015)">
                                    <div class="form-text text-muted">
                                        Default sistem: <strong>Rp&nbsp;9.077.600</strong> — gaji di atas batas ini akan di-cap untuk perhitungan iuran JP.
                                    </div>
                                </div>
                                {{-- BPJS Kes salary cap (hanya tampil jika program = bpjs_kesehatan) --}}
                                <div class="col-md-12 d-none" data-bpjs-baseline-kes-cap-section>
                                    <label class="form-label fw-semibold">Batas Atas Upah BPJS Kesehatan (Rp)</label>
                                    <input type="number" class="form-control" min="0" step="1"
                                           data-bpjs-baseline-kes-salary-cap
                                           placeholder="Kosongkan = gunakan default sistem (Perpres 75/2019)">
                                    <div class="form-text text-muted">
                                        Default sistem: <strong>Rp&nbsp;12.000.000</strong> — gaji di atas batas ini akan di-cap untuk perhitungan iuran BPJS Kesehatan.
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Catatan</label>
                                    <textarea class="form-control" rows="2" data-bpjs-baseline-notes placeholder="Opsional — contoh: tarif industri risiko tinggi"></textarea>
                                </div>
                            </div>
                            <div class="alert alert-danger d-none mt-3" data-bpjs-baseline-error role="alert"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" data-bpjs-baseline-submit>
                                <span class="spinner-border spinner-border-sm me-1 d-none" data-bpjs-baseline-spinner></span>
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="bpjsPolicyModal" tabindex="-1" aria-labelledby="bpjsPolicyModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bpjsPolicyModalLabel">Policy BPJS</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form data-bpjs-policy-form>
                        <input type="hidden" data-bpjs-policy-id>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Program</label>
                                    <select class="form-select" data-bpjs-policy-program required>
                                        <option value="bpjs_kesehatan">BPJS Kesehatan</option>
                                        <option value="jht">JHT</option>
                                        <option value="jp">JP</option>
                                        <option value="jkk">JKK</option>
                                        <option value="jkm">JKM</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Porsi</label>
                                    <select class="form-select" data-bpjs-policy-party required>
                                        <option value="employee">Pekerja</option>
                                        <option value="employer">Perusahaan</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tarif (%)</label>
                                    <input type="number" class="form-control" step="0.0001" min="0" max="100" data-bpjs-policy-rate required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Basis Upah</label>
                                    <select class="form-select" data-bpjs-policy-wage-base>
                                        <option value="">-</option>
                                        <option value="wage_bpjs_health">Dasar BPJS Kesehatan</option>
                                        <option value="wage_bpjs_tk">Dasar BPJS Ketenagakerjaan</option>
                                        <option value="fixed_nominal">Nominal Tetap</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Mulai Berlaku</label>
                                    <input type="date" class="form-control" data-bpjs-policy-start required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Sampai Berlaku</label>
                                    <input type="date" class="form-control" data-bpjs-policy-end>
                                </div>
                                <div class="col-md-12 d-none" data-bpjs-policy-jp-cap-section>
                                    <label class="form-label">Salary Cap JP (Rp)</label>
                                    <input type="number" class="form-control" min="0" step="1" data-bpjs-policy-jp-salary-cap
                                           placeholder="Kosongkan = default sistem">
                                    <div class="form-text">Dipakai saat hitung iuran JP untuk pekerja dan perusahaan.</div>
                                </div>
                                <div class="col-md-12 d-none" data-bpjs-policy-kes-cap-section>
                                    <label class="form-label">Salary Cap BPJS Kesehatan (Rp)</label>
                                    <input type="number" class="form-control" min="0" step="1" data-bpjs-policy-kes-salary-cap
                                           placeholder="Kosongkan = default sistem">
                                    <div class="form-text">Dipakai saat hitung iuran BPJS Kesehatan untuk pekerja dan perusahaan.</div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Dasar Hukum</label>
                                    <textarea class="form-control" rows="2" data-bpjs-policy-legal-basis></textarea>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Catatan</label>
                                    <textarea class="form-control" rows="2" data-bpjs-policy-notes></textarea>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="1" id="bpjs_policy_is_active" data-bpjs-policy-active checked>
                                        <label class="form-check-label" for="bpjs_policy_is_active">Policy aktif</label>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-danger d-none mt-3" data-bpjs-policy-error role="alert"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" data-bpjs-policy-submit>
                                <span class="spinner-border spinner-border-sm me-1 d-none" data-bpjs-policy-spinner></span>
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="bpjsMembershipModal" tabindex="-1" aria-labelledby="bpjsMembershipModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bpjsMembershipModalLabel">Update Membership BPJS</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form data-bpjs-membership-form>
                        <input type="hidden" data-bpjs-membership-user-id>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Karyawan</label>
                                <input type="text" class="form-control" data-bpjs-membership-name readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">No BPJS Kesehatan</label>
                                <input type="text" class="form-control" data-bpjs-membership-kes>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">No BPJS Ketenagakerjaan</label>
                                <input type="text" class="form-control" data-bpjs-membership-tk>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tanggal Efektif</label>
                                <input type="date" class="form-control" data-bpjs-membership-effective-date>
                            </div>
                            <div class="alert alert-danger d-none" data-bpjs-membership-error role="alert"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" data-bpjs-membership-submit>
                                <span class="spinner-border spinner-border-sm me-1 d-none" data-bpjs-membership-spinner></span>
                                Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Panduan BPJS Governance --}}
    <div class="modal fade" id="bpjsGuideModal" tabindex="-1" aria-labelledby="bpjsGuideModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bpjsGuideModalLabel">
                        <i class="ti ti-info-circle me-2 text-primary"></i>Panduan BPJS Governance
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                    <h6 class="fw-semibold mb-2">Apa itu BPJS Governance?</h6>
                    <p class="text-muted small mb-3">
                        Modul ini mengatur konfigurasi kepatuhan Jaminan Sosial Indonesia — mencakup BPJS Kesehatan
                        dan BPJS Ketenagakerjaan (JHT, JP, JKK, JKM) — yang dipisahkan dari konfigurasi PPh 21.
                    </p>

                    <h6 class="fw-semibold mb-2">Tab &amp; Fungsinya</h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered text-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Tab</th>
                                    <th>Fungsi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Overview</strong></td>
                                    <td>Ringkasan program aktif, rata-rata iuran, dan status membership karyawan.</td>
                                </tr>
                                <tr>
                                    <td><strong>Policy Setup</strong></td>
                                    <td>Daftar policy iuran BPJS per program dan porsi (pekerja/perusahaan). Policy aktif digunakan untuk kalkulasi payroll.</td>
                                </tr>
                                <tr>
                                    <td><strong>Employee Membership</strong></td>
                                    <td>Status kepesertaan BPJS per karyawan — pastikan semua karyawan terdaftar sebelum proses payroll.</td>
                                </tr>
                                <tr>
                                    <td><strong>Compliance Reports</strong></td>
                                    <td>Laporan kepatuhan — karyawan yang belum terdaftar atau ada ketidaksesuaian tarif.</td>
                                </tr>
                                <tr>
                                    <td><strong>Konfigurasi Baseline</strong></td>
                                    <td>Override tarif min/maks per program. Juga tempat mengatur <strong>kategori risiko JKK</strong> dan <strong>batas atas upah</strong> untuk JP &amp; BPJS Kesehatan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h6 class="fw-semibold mb-2">Kalkulasi Iuran di Payroll</h6>
                    <ul class="text-muted small mb-3">
                        <li><strong>BPJS Kesehatan</strong> — gaji di-cap pada batas atas (default Rp 12.000.000 per Perpres 75/2019). Iuran pekerja 1%, perusahaan 4%.</li>
                        <li><strong>JHT</strong> — tidak ada cap; pekerja 2%, perusahaan 3,7%.</li>
                        <li><strong>JP</strong> — gaji di-cap (default Rp 9.077.600 per PP 45/2015). Pekerja 1%, perusahaan 2%.</li>
                        <li><strong>JKK</strong> — hanya porsi perusahaan. Rate ditentukan oleh <em>kategori risiko</em> usaha (PP 44/2015):
                            <span class="ms-1 badge bg-light text-dark">1 = 0,24%</span>
                            <span class="ms-1 badge bg-light text-dark">2 = 0,54%</span>
                            <span class="ms-1 badge bg-light text-dark">3 = 0,89%</span>
                            <span class="ms-1 badge bg-light text-dark">4 = 1,27%</span>
                            <span class="ms-1 badge bg-light text-dark">5 = 1,74%</span>
                        </li>
                        <li><strong>JKM</strong> — hanya porsi perusahaan, rate flat sesuai policy (0,3%).</li>
                    </ul>

                    <h6 class="fw-semibold mb-2">Alur Setup Pertama Kali</h6>
                    <ol class="text-muted small mb-0">
                        <li>Buka <strong>Konfigurasi Baseline</strong> → set kategori risiko JKK dan batas atas upah (jika berbeda dari default).</li>
                        <li>Buka <strong>Policy Setup</strong> → pastikan ada policy aktif untuk setiap program.</li>
                        <li>Buka <strong>Employee Membership</strong> → pastikan semua karyawan aktif sudah terdaftar.</li>
                        <li>Jalankan payroll — iuran BPJS akan otomatis terhitung di slip gaji.</li>
                    </ol>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
