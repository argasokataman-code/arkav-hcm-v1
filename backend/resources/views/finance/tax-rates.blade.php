<?php $page = 'tax-employees'; ?>
@php
    $taxGovernanceScreen = $taxGovernanceScreen ?? 'landing';
    $taxGovernancePolicyUuid = $taxGovernancePolicyUuid ?? null;
    $isGlobalHcmAdmin = auth()->user()?->isGlobalHcmAdmin() ?? false;

@endphp
@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper" data-tax-governance-page data-tax-governance-screen="{{ $taxGovernanceScreen }}" data-tax-governance-policy-uuid="{{ $taxGovernancePolicyUuid }}" role="main" aria-label="Employee tax and payroll compliance">
    <div class="content">
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Employee Tax & Payroll Compliance</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">HR / Payroll</li>
                        <li class="breadcrumb-item active" aria-current="page">Employee Tax & Compliance</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                <div class="mb-2">
                    <div class="dropdown">
                        <a href="javascript:void(0);" class="dropdown-toggle btn btn-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
                            <i class="ti ti-file-export me-1"></i>Export
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end p-3">
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1" data-tax-governance-export-pdf>
                                    <i class="ti ti-file-type-pdf me-1"></i>Ekspor PDF
                                </a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1" data-tax-governance-export-json>
                                    <i class="ti ti-file-type-json me-1"></i>Ekspor JSON
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="head-icons ms-2">
                    <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                        <i class="ti ti-chevrons-up"></i>
                    </a>
                </div>
            </div>
        </div>



        <div class="alert alert-info d-flex align-items-start gap-2 mb-3" role="alert">
            <i class="ti ti-info-circle mt-1"></i>
            <div>
                <div class="fw-semibold">
                    Tenant Employee Tax Setup
                </div>
                <div class="small">
                    Manage the active company's PPh 21 policy, employee tax profiles, component mapping, and compliance evidence in one tenant-focused workspace.
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gy-2 justify-content-between my-4">
            <ul class="nav nav-pills gap-2" role="navigation" aria-label="Submenu Tenant Tax">
                <li class="nav-item">
                    <a href="{{ route('tax-employees') }}"
                       class="nav-link btn btn-white {{ $taxGovernanceScreen === 'landing' ? 'active' : '' }}"
                       aria-current="{{ $taxGovernanceScreen === 'landing' ? 'page' : 'false' }}">Compliance Overview</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('tax-employees.policies') }}"
                       class="nav-link btn btn-white {{ $taxGovernanceScreen === 'tenant-policies' ? 'active' : '' }}"
                       aria-current="{{ $taxGovernanceScreen === 'tenant-policies' ? 'page' : 'false' }}">PPh 21 Policies</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('tax-employees.tenant-compliance') }}"
                       class="nav-link btn btn-white {{ $taxGovernanceScreen === 'tenant-compliance' ? 'active' : '' }}"
                        aria-current="{{ $taxGovernanceScreen === 'tenant-compliance' ? 'page' : 'false' }}">Tenant Compliance</a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('tax-employees.employee-tax-profiles') }}"
                       class="nav-link btn btn-white {{ $taxGovernanceScreen === 'employee-tax-profiles' ? 'active' : '' }}"
                       aria-current="{{ $taxGovernanceScreen === 'employee-tax-profiles' ? 'page' : 'false' }}">Employee Tax Profiles</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('tax-employees.reports') }}"
                       class="nav-link btn btn-white {{ $taxGovernanceScreen === 'tenant-reports' ? 'active' : '' }}"
                       aria-current="{{ $taxGovernanceScreen === 'tenant-reports' ? 'page' : 'false' }}">Audit Reports</a>
                </li>
            </ul>
            <div class="mb-2 d-flex gap-2">
                <button type="button" class="btn btn-light d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#taxEmployeeGuideModal">
                    <i class="ti ti-book me-2"></i>Usage Guide
                </button>
                @if ($taxGovernanceScreen === 'tenant-policies')
                    <button type="button" class="btn btn-primary d-inline-flex align-items-center" data-tax-policy-create>
                        <i class="ti ti-circle-plus me-2"></i>New Policy
                    </button>
                @endif
            </div>
        </div>

        <div class="alert alert-danger d-none" data-tax-governance-error></div>
        <div class="alert alert-info d-none" data-tax-platform-gate></div>

        @if ($taxGovernanceScreen === 'landing')
            <div class="row g-3 mb-3" data-tax-governance-summary>
                <div class="col-md-6 col-xl-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted fs-13 mb-1">Status Kepatuhan PPh 21</div>
                            <div class="d-flex align-items-center justify-content-between">
                                <h4 class="mb-0" data-tax-overall-status>-</h4>
                                <span class="badge bg-secondary-subtle text-secondary" data-tax-overall-badge>Unknown</span>
                            </div>
                            <div class="small text-muted mt-2" data-tax-next-review>Next review: -</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted fs-13 mb-1">Kebijakan PPh 21 Aktif</div>
                            <h4 class="mb-1" data-tax-policy-version>-</h4>
                            <div class="small text-muted" data-tax-policy-publication>-</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted fs-13 mb-1">Anomali Belum Selesai</div>
                            <h4 class="mb-1" data-tax-anomaly-count>0</h4>
                            <div class="small text-muted" data-tax-anomaly-hint>No active anomaly</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted fs-13 mb-1">Karyawan Terdaftar</div>
                            <h4 class="mb-1" data-tax-employee-count>0</h4>
                            <div class="small text-muted" data-tax-employee-hint>Dengan profil pajak</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Tindak Lanjut Prioritas</h5>
                        </div>
                        <div class="card-body">
                            <ol class="list-group list-group-numbered small" data-tax-action-list>
                                <li class="list-group-item text-muted">Rekomendasi tindakan akan ditampilkan di sini.</li>
                            </ol>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Daftar Anomali Pajak</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-sm mb-0" role="grid" aria-label="Daftar Anomali Pajak">
                                    <thead class="thead-light">
                                        <tr>
                                            <th scope="col" style="font-size: 11px;">Tipe</th>
                                            <th scope="col" style="font-size: 11px;">Prioritas</th>
                                            <th scope="col" style="font-size: 11px;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody data-tax-anomaly-table>
                                        <tr><td colspan="3" class="text-center text-muted py-3" style="font-size: 12px;">Belum ada data anomali untuk ditampilkan.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Riwayat Perubahan Kebijakan</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                        <table class="table table-sm mb-0" role="grid" aria-label="Riwayat Perubahan Kebijakan">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col" style="font-size: 11px;">Versi</th>
                                    <th scope="col" style="font-size: 11px;">Aksi</th>
                                    <th scope="col" style="font-size: 11px;">Pelaku</th>
                                    <th scope="col" style="font-size: 11px;">Waktu</th>
                                    <th scope="col" style="font-size: 11px;">Ringkasan</th>
                                </tr>
                            </thead>
                            <tbody data-tax-event-table>
                                <tr><td colspan="5" class="text-center text-muted py-3" style="font-size: 12px;">Belum ada riwayat perubahan untuk ditampilkan.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if ($taxGovernanceScreen === 'tenant-reports')
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Laporan Audit Tenant</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-sm mb-0" role="grid" aria-label="Laporan Audit">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col" style="font-size: 11px;">Tenant</th>
                                    <th scope="col" style="font-size: 11px;">Tipe Anomali</th>
                                    <th scope="col" style="font-size: 11px;">Jumlah</th>
                                    <th scope="col" style="font-size: 11px;">Status</th>
                                    <th scope="col" style="font-size: 11px;">Waktu Terdeteksi</th>
                                </tr>
                            </thead>
                            <tbody data-tax-report-audit-table>
                                <tr><td colspan="5" class="text-center text-muted py-3" style="font-size: 12px;">Laporan audit akan ditampilkan di sini.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if ($taxGovernanceScreen === 'tenant-policies')
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Daftar Kebijakan PPh 21 Tenant</h5>
                    <button type="button" class="btn btn-sm btn-primary" data-tax-policy-create>
                        <i class="ti ti-circle-plus me-1"></i>Buat Draft
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Kode Kebijakan</th>
                                    <th>Nama Kebijakan</th>
                                    <th>Status</th>
                                    <th>Versi</th>
                                    <th>Mulai Berlaku</th>
                                    <th>Diperbarui</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody data-tax-tenant-policy-table>
                                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada kebijakan tenant untuk ditampilkan.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- komponen-pajak screen removed; CRUD ada di /salary-component-master --}}
        @if (false)
            <div class="row g-3 mb-3 d-none" data-tax-map-summary>
                <div class="col-md-6 col-xl-2">
                    <button type="button" class="card w-100 text-start border-0 shadow-sm" data-tax-map-card="all">
                        <div class="card-body py-3">
                            <p class="text-muted mb-1 fs-13">Total Components</p>
                            <h4 class="mb-0" data-tax-map-total>0</h4>
                        </div>
                    </button>
                </div>
                <div class="col-md-6 col-xl-2">
                    <button type="button" class="card w-100 text-start border-0 shadow-sm" data-tax-map-card="taxable">
                        <div class="card-body py-3">
                            <p class="text-muted mb-1 fs-13">Taxable Components</p>
                            <h4 class="mb-0 text-success" data-tax-map-taxable>0</h4>
                        </div>
                    </button>
                </div>
                <div class="col-md-6 col-xl-2">
                    <button type="button" class="card w-100 text-start border-0 shadow-sm" data-tax-map-card="non-taxable">
                        <div class="card-body py-3">
                            <p class="text-muted mb-1 fs-13">Non-Taxable Components</p>
                            <h4 class="mb-0 text-secondary" data-tax-map-non-taxable>0</h4>
                        </div>
                    </button>
                </div>
                <div class="col-md-6 col-xl-3">
                    <button type="button" class="card w-100 text-start border border-danger shadow-sm" data-tax-map-card="unmapped">
                        <div class="card-body py-3">
                            <p class="text-danger mb-1 fs-13">Unmapped Components</p>
                            <h4 class="mb-0 text-danger" data-tax-map-unmapped>0</h4>
                        </div>
                    </button>
                </div>
                <div class="col-md-6 col-xl-3">
                    <button type="button" class="card w-100 text-start border-0 shadow-sm" data-tax-map-card="bpjs">
                        <div class="card-body py-3">
                            <p class="text-muted mb-1 fs-13">BPJS Components</p>
                            <h4 class="mb-0 text-info" data-tax-map-bpjs>0</h4>
                        </div>
                    </button>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <div class="flex-grow-1" style="min-width:220px;">
                            <input type="search" class="form-control" placeholder="Search by component name or code" data-tax-map-search>
                        </div>
                        <div class="btn-group" role="group" aria-label="Quick filter">
                            <button type="button" class="btn btn-outline-secondary active" data-tax-map-chip="all">All</button>
                            <button type="button" class="btn btn-outline-success" data-tax-map-chip="income">Income</button>
                            <button type="button" class="btn btn-outline-danger" data-tax-map-chip="deduction">Deduction</button>
                            <button type="button" class="btn btn-outline-info" data-tax-map-chip="bpjs">BPJS</button>
                            <button type="button" class="btn btn-outline-danger" data-tax-map-chip="unmapped">Unmapped</button>
                        </div>
                        <select class="form-select w-auto" data-tax-map-treatment>
                            <option value="">All Tax Treatment</option>
                            <option value="pph21_taxable_full">PPh 21 Taxable Full</option>
                            <option value="pph21_taxable_partial">PPh 21 Taxable Partial</option>
                            <option value="non_object">Non-Object</option>
                            <option value="deductible">Deductible</option>
                            <option value="pph21_final">PPh 21 Final</option>
                            <option value="pph21_separate">Separate Handling</option>
                            <option value="employer_display_only">Employer Display Only</option>
                            <option value="unmapped">Unmapped</option>
                        </select>
                        <select class="form-select w-auto" data-tax-map-category>
                            <option value="">All Categories</option>
                        </select>
                        <div class="form-check form-switch ms-1">
                            <input class="form-check-input" type="checkbox" id="taxMapIncompleteOnly" data-tax-map-only-incomplete>
                            <label class="form-check-label" for="taxMapIncompleteOnly">Show only incomplete mapping</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-success d-none" data-tax-map-all-mapped role="status">
                <i class="ti ti-circle-check me-1"></i>All components are properly mapped to tax rules.
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" role="grid" aria-label="PPh 21 Component Mapping Table">
                            <thead class="thead-light">
                                <tr>
                                    <th>Component</th>
                                    <th>Type</th>
                                    <th>Category</th>
                                    <th>Tax Classification</th>
                                    <th>Payroll Effect</th>
                                    <th>BPJS Info</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody data-tax-komponen-table>
                                <tr><td colspan="8" class="text-center text-muted py-4">Loading payroll components...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center text-muted py-5 d-none" data-tax-map-empty>
                        <i class="ti ti-database-off fs-24 d-block mb-2"></i>
                        No payroll components found. Please create components in Salary Components first.
                    </div>
                </div>
                <div class="card-footer text-muted small d-flex flex-wrap gap-3">
                    <span><i class="ti ti-square-rounded-check-filled text-success me-1"></i>PPh 21 taxable</span>
                    <span><i class="ti ti-square-rounded-check-filled text-primary me-1"></i>Deductible</span>
                    <span><i class="ti ti-square-rounded-check-filled text-secondary me-1"></i>Non-object / employer display only</span>
                    <span><i class="ti ti-alert-triangle text-danger me-1"></i>Unmapped (requires action)</span>
                </div>
            </div>
        @endif {{-- /komponen-pajak removed block --}}

        @if ($taxGovernanceScreen === 'policy-editor')
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">PPh 21 Policy Editor</h5>
                    <span class="badge bg-info-subtle text-info" data-tax-editor-mode>Draft</span>
                </div>
                <div class="card-body">
                    <div class="alert alert-secondary mb-3" data-tax-editor-meta>
                        Referensi kebijakan: <strong data-tax-editor-policy-ref>{{ $taxGovernancePolicyUuid ?? 'draft-baru' }}</strong>
                    </div>

                    <div class="alert alert-info mb-3">
                        Policy ini menyimpan referensi regulasi dan schedule efektif statutory PPh 21 untuk perusahaan aktif. Sistem akan melampirkan lookup TER kategori A/B/C resmi sesuai periode berlaku kebijakan.
                    </div>

                    <form class="row g-3" data-tax-policy-editor-form>
                        <div class="col-md-4">
                            <label class="form-label">Kode Kebijakan</label>
                            <input type="text" class="form-control" name="policyCode" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Nama Kebijakan</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Mulai Berlaku</label>
                            <input type="date" class="form-control" name="effectiveStartDate" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Akhir Berlaku</label>
                            <input type="date" class="form-control" name="effectiveEndDate">
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-outline-secondary" data-tax-policy-validate>Validation Preview</button>
                            <button type="submit" class="btn btn-primary" data-tax-policy-save>Save Configuration</button>
                            <a href="{{ route('tax-employees.policies') }}" class="btn btn-light">Back to Policies</a>
                        </div>
                    </form>

                    <div class="alert alert-info mt-3 d-none" data-tax-policy-validation-preview></div>

                    <div class="d-flex gap-2 flex-wrap mt-3" data-tax-policy-workflow-actions>
                        <button type="button" class="btn btn-success d-none" data-tax-policy-workflow-btn="publish">Publikasikan</button>
                    </div>
                </div>
            </div>
        @endif


        {{-- ================================================================ --}}
        {{-- SCREEN: employee-tax-profiles                                    --}}
        {{-- Role: Tenant Admin / HR. Shows per-employee PTKP / NPWP data     --}}
        {{-- ================================================================ --}}
        @if ($taxGovernanceScreen === 'employee-tax-profiles')
            <div class="card mb-3" role="region" aria-label="Filter Profil Pajak Karyawan">
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" class="form-control w-auto" placeholder="Cari nama / email..." data-emp-tax-search aria-label="Cari karyawan">
                        <select class="form-select w-auto" data-emp-tax-filter aria-label="Filter kelengkapan data pajak">
                            <option value="">Semua Karyawan</option>
                            <option value="missing_npwp">NPWP Kosong</option>
                            <option value="missing_ptkp">PTKP Kosong</option>
                            <option value="incomplete">Data Tidak Lengkap</option>
                            <option value="complete">Data Lengkap</option>
                        </select>
                        <button type="button" class="btn btn-outline-primary" data-emp-tax-refresh aria-label="Muat ulang data">
                            <i class="ti ti-refresh me-1" aria-hidden="true"></i>Muat Ulang
                        </button>
                        <div class="ms-auto">
                            <span class="badge bg-info-subtle text-info" data-emp-tax-count aria-live="polite">Memuat...</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3" role="region" aria-label="KPI Kelengkapan Pajak Karyawan">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted fs-13 mb-1" id="kpi-emp-total">Total Karyawan</div>
                            <h4 class="mb-0" data-emp-tax-kpi-total aria-labelledby="kpi-emp-total">-</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted fs-13 mb-1" id="kpi-emp-npwp">NPWP Terisi</div>
                            <h4 class="mb-0" data-emp-tax-kpi-npwp aria-labelledby="kpi-emp-npwp">-</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted fs-13 mb-1" id="kpi-emp-ptkp">Status PTKP Terisi</div>
                            <h4 class="mb-0" data-emp-tax-kpi-ptkp aria-labelledby="kpi-emp-ptkp">-</h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">Data Profil Pajak Per Karyawan</h5>
                    <small class="text-muted">NPWP, PTKP, dan kelengkapan data pajak</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0" role="grid" aria-label="Profil Pajak Karyawan">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col">Karyawan</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">NPWP</th>
                                    <th scope="col">Status PTKP</th>
                                    <th scope="col">Status Pajak</th>
                                    <th scope="col">Kelengkapan</th>
                                    <th scope="col">Aksi</th>
                                </tr>
                            </thead>
                            <tbody data-emp-tax-tbody>
                                <tr><td colspan="7" class="text-center text-muted py-4" aria-live="polite">Belum ada profil pajak karyawan untuk ditampilkan.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <span class="text-muted small" data-emp-tax-pagination-info></span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-light" data-emp-tax-prev aria-label="Halaman sebelumnya" disabled>
                            <i class="ti ti-chevron-left" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-light" data-emp-tax-next aria-label="Halaman berikutnya" disabled>
                            <i class="ti ti-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="empTaxEditModal" tabindex="-1" aria-labelledby="empTaxEditModalLabel" aria-modal="true" role="dialog">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="empTaxEditModalLabel">Edit Profil Pajak Karyawan</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup modal"></button>
                        </div>
                        <form id="empTaxEditForm" data-emp-tax-edit-form novalidate>
                            <div class="modal-body">
                                <input type="hidden" name="userId" data-emp-tax-edit-user-id>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold" for="emp-tax-edit-name">Karyawan</label>
                                    <input type="text" class="form-control" id="emp-tax-edit-name" data-emp-tax-edit-name readonly aria-readonly="true">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="emp-tax-edit-npwp">NPWP</label>
                                    <input type="text" class="form-control" id="emp-tax-edit-npwp" name="npwp" maxlength="30" placeholder="XX.XXX.XXX.X-XXX.XXX" data-emp-tax-edit-npwp aria-describedby="npwp-hint">
                                    <div id="npwp-hint" class="form-text">Format 15 atau 16 digit. Kosongkan jika belum tersedia.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="emp-tax-edit-tax-status">Status PTKP / Pajak</label>
                                    <select class="form-select" id="emp-tax-edit-tax-status" name="taxStatus" data-emp-tax-edit-tax-status>
                                        <option value="">— Pilih Status —</option>
                                        <option value="TK0">TK/0 (Tidak Kawin, 0 tanggungan)</option>
                                        <option value="TK1">TK/1 (Tidak Kawin, 1 tanggungan)</option>
                                        <option value="TK2">TK/2 (Tidak Kawin, 2 tanggungan)</option>
                                        <option value="TK3">TK/3 (Tidak Kawin, 3 tanggungan)</option>
                                        <option value="K0">K/0 (Kawin, 0 tanggungan)</option>
                                        <option value="K1">K/1 (Kawin, 1 tanggungan)</option>
                                        <option value="K2">K/2 (Kawin, 2 tanggungan)</option>
                                        <option value="K3">K/3 (Kawin, 3 tanggungan)</option>
                                    </select>
                                </div>
                                <div class="alert alert-danger d-none" data-emp-tax-edit-error role="alert" aria-live="assertive"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" class="btn btn-primary" data-emp-tax-edit-submit>
                                    <span class="spinner-border spinner-border-sm me-1 d-none" data-emp-tax-edit-spinner role="status" aria-hidden="true"></span>
                                    Simpan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        {{-- ================================================================ --}}
        {{-- SCREEN: tenant-compliance                                        --}}
        {{-- Dedicated compliance summary: checklist + policy snapshot + history --}}
        {{-- ================================================================ --}}
        @if ($taxGovernanceScreen === 'tenant-compliance')
            <div class="alert alert-info d-flex align-items-start gap-2 mb-3" role="alert">
                <i class="ti ti-shield-check mt-1"></i>
                <div>
                    <div class="fw-semibold">Tenant compliance tracker</div>
                    <div class="small">
                        This tab tracks company-level PPh 21 readiness: policy publication status, payroll coverage, anomaly status, and policy change history for the selected period.
                    </div>
                </div>
            </div>

            <div class="card mb-3" role="region" aria-label="Checklist Kepatuhan Pajak Tenant">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">Checklist Kepatuhan Pajak Tenant</h5>
                    <div class="d-flex align-items-center gap-2">
                        <input type="month" class="form-control w-auto" data-compliance-period-start aria-label="Mulai periode">
                        <span class="text-muted small">s/d</span>
                        <input type="month" class="form-control w-auto" data-compliance-period-end aria-label="Akhir periode">
                        <button type="button" class="btn btn-outline-primary" data-compliance-refresh aria-label="Muat ulang data kepatuhan">
                            <i class="ti ti-refresh me-1" aria-hidden="true"></i>Muat
                        </button>
                        <a href="#" class="btn btn-primary" data-compliance-export-pdf aria-label="Ekspor PDF" target="_blank">
                            <i class="ti ti-file-type-pdf me-1" aria-hidden="true"></i>Export PDF
                        </a>
                    </div>
                </div>
                <div class="card-body" data-compliance-checklist-area>
                    <div class="row g-3">
                        <div class="col-md-6 col-xl-3">
                            <div class="border rounded p-3 h-100 d-flex align-items-center gap-3" role="status" aria-label="Status: Kebijakan Dipublikasikan">
                                <span class="fs-4" data-compliance-check-icon-policy aria-hidden="true">&#x23F3;</span>
                                <div>
                                    <div class="fw-semibold">Kebijakan PPh 21 Diterbitkan</div>
                                    <div class="small text-muted" data-compliance-check-label-policy>Memeriksa...</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="border rounded p-3 h-100 d-flex align-items-center gap-3" role="status" aria-label="Status: Publikasi Terbaru">
                                <span class="fs-4" data-compliance-check-icon-recent aria-hidden="true">&#x23F3;</span>
                                <div>
                                    <div class="fw-semibold">Publikasi Tidak Kedaluwarsa</div>
                                    <div class="small text-muted" data-compliance-check-label-recent>Memeriksa...</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="border rounded p-3 h-100 d-flex align-items-center gap-3" role="status" aria-label="Status: Cakupan Payroll Run">
                                <span class="fs-4" data-compliance-check-icon-payroll aria-hidden="true">&#x23F3;</span>
                                <div>
                                    <div class="fw-semibold">Seluruh Proses Payroll Menggunakan Kebijakan</div>
                                    <div class="small text-muted" data-compliance-check-label-payroll>Memeriksa...</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                                <div class="border rounded p-3 h-100 d-flex align-items-center gap-3" role="status" aria-label="Status: Anomali Terbuka">
                                <span class="fs-4" data-compliance-check-icon-anomaly aria-hidden="true">&#x23F3;</span>
                                <div>
                                    <div class="fw-semibold">Tidak Ada Anomali Terbuka</div>
                                    <div class="small text-muted" data-compliance-check-label-anomaly>Memeriksa...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-xl-12">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Snapshot Kebijakan Aktif</h5>
                        </div>
                        <div class="card-body" data-compliance-policy-snapshot>
                            <dl class="row mb-0">
                                <dt class="col-5">Kode Kebijakan</dt>
                                <dd class="col-7" data-compliance-policy-code>-</dd>
                                <dt class="col-5">Nama Kebijakan</dt>
                                <dd class="col-7" data-compliance-policy-name>-</dd>
                                <dt class="col-5">Versi</dt>
                                <dd class="col-7" data-compliance-policy-version>-</dd>
                                <dt class="col-5">Berlaku Mulai</dt>
                                <dd class="col-7" data-compliance-policy-effective>-</dd>
                                <dt class="col-5">Payroll Run Tercakup</dt>
                                <dd class="col-7" data-compliance-payroll-runs>-</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Riwayat Perubahan Kebijakan (Periode Terpilih)</h5>
                    <small class="text-muted" data-compliance-change-period>-</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table mb-0" role="grid" aria-label="Riwayat Perubahan Kebijakan">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col">Waktu</th>
                                    <th scope="col">Tipe Event</th>
                                    <th scope="col">Pelaku</th>
                                    <th scope="col">Catatan</th>
                                </tr>
                            </thead>
                            <tbody data-compliance-history-tbody>
                                <tr><td colspan="4" class="text-center text-muted py-4" aria-live="polite">Pilih periode dan klik Muat.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="alert alert-danger d-none mt-3" data-compliance-error role="alert" aria-live="assertive"></div>
        @endif

        <div class="modal fade" id="taxEmployeeGuideModal" tabindex="-1" aria-labelledby="taxEmployeeGuideModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="taxEmployeeGuideModalLabel">Panduan Penggunaan — Tax &amp; Payroll Compliance</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">

                        {{-- Section 1: Kebijakan PPh 21 --}}
                        <h6 class="fw-semibold mb-2 d-flex align-items-center gap-2">
                            <span class="badge bg-primary-subtle text-primary rounded-pill">1</span>
                            Kebijakan PPh 21 Tenant
                        </h6>
                        <p class="text-muted small mb-2">
                            Kebijakan adalah <strong>aturan dasar perhitungan PPh 21</strong> perusahaan Anda — metode
                            perhitungan, PTKP, tarif TER, dan lain-lain. Setiap perusahaan perlu memiliki minimal
                            <strong>1 kebijakan berstatus Published</strong> agar fitur payroll pajak aktif.
                        </p>
                        <div class="card border-0 bg-light mb-3">
                            <div class="card-body py-2 px-3">
                                <p class="small fw-semibold mb-1">Kenapa bisa ada lebih dari satu kebijakan?</p>
                                <ul class="small mb-0 ps-3">
                                    <li><strong>Pergantian regulasi pemerintah</strong> — Saat ada PMK atau PP baru (seperti PMK 168/2023 menggantikan PMK 252), kebijakan lama tetap disimpan sebagai arsip audit, bukan dihapus.</li>
                                    <li><strong>Periode berlaku berbeda</strong> — Tiap kebijakan punya tanggal <em>Mulai Berlaku</em> dan <em>Akhir Berlaku</em>. Payroll bulan Januari 2024 pakai kebijakan yang aktif di tanggal tersebut.</li>
                                    <li><strong>Revisi/versi</strong> — Jika ada koreksi interpretasi aturan di tengah tahun, kebijakan baru dibuat (versi naik), kebijakan lama otomatis di-<em>supersede</em>.</li>
                                    <li><strong>Kepatuhan audit DJP</strong> — DJP dapat memeriksa data hingga 5 tahun ke belakang. Riwayat kebijakan menjadi bukti bahwa perhitungan pajak sudah sesuai aturan yang berlaku di periode tersebut.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="card border-0 bg-light mb-3">
                            <div class="card-body py-2 px-3">
                                <p class="small fw-semibold mb-1">Lifecycle status kebijakan</p>
                                <div class="d-flex flex-wrap gap-2 small">
                                    <span><span class="badge bg-secondary">Draft</span> — sedang disusun, belum berlaku</span>
                                    <span><span class="badge bg-success">Published</span> — aktif, dipakai payroll</span>
                                    <span><span class="badge bg-warning text-dark">Superseded</span> — digantikan versi baru, arsip saja</span>
                                    <span><span class="badge bg-danger">Void</span> — dibatalkan, tidak pernah berlaku</span>
                                </div>
                            </div>
                        </div>
                        <div class="card border-warning border mb-4">
                            <div class="card-body py-2 px-3">
                                <p class="small fw-semibold mb-1 text-warning-emphasis">Kapan harus buat kebijakan baru vs edit kebijakan lama?</p>
                                <ul class="small mb-0 ps-3">
                                    <li><strong>Edit kebijakan lama</strong> — jika ada kesalahan kecil sebelum kebijakan tersebut dipakai payroll (masih Draft).</li>
                                    <li><strong>Buat kebijakan baru</strong> — jika ada pergantian aturan pemerintah, atau kebijakan lama sudah digunakan untuk payroll bulan sebelumnya (sudah Published dan ada slip gaji yang mengacu ke sana).</li>
                                </ul>
                            </div>
                        </div>

                        <hr class="my-3">

                        {{-- Section 2: Component Mapping --}}
                        <h6 class="fw-semibold mb-2 d-flex align-items-center gap-2">
                            <span class="badge bg-primary-subtle text-primary rounded-pill">2</span>
                            PPh 21 Component Mapping
                        </h6>
                        <ol class="mb-0 ps-3 small">
                            <li>Buka tab <strong>PPh 21 Component Mapping</strong> lalu klik <strong>Sync Components</strong> untuk memuat komponen payroll terbaru.</li>
                            <li>Prioritaskan baris berlabel <strong>Unmapped</strong> — komponen tanpa klasifikasi pajak dapat menyebabkan risiko pelaporan PPh 21.</li>
                            <li>Untuk setiap komponen, pilih satu <strong>Tax Classification</strong> yang sesuai dengan perlakuan statutori komponen tersebut.</li>
                            <li>Gunakan <strong>Audit Mapping</strong> untuk memfilter baris yang belum lengkap sebelum cut-off payroll.</li>
                            <li>Untuk komponen BPJS, verifikasi dengan hati-hati perlakuan iuran <strong>Karyawan</strong> vs <strong>Perusahaan</strong>.</li>
                            <li>Untuk menambah atau mengedit komponen, klik <strong>Tambah Komponen</strong> atau ikon edit pada baris terkait. Penghapusan dinonaktifkan untuk komponen yang dikunci sistem.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
    @include('hcm.partials.salary-component-modals')
    @include('hcm.partials.salary-component-guide-modal')

@endsection
