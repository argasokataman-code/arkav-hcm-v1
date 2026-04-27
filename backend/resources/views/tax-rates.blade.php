<?php $page = 'tax-rates'; ?>
@php
    $taxGovernanceScreen = $taxGovernanceScreen ?? 'landing';
    $taxGovernancePolicyUuid = $taxGovernancePolicyUuid ?? null;
    $isGlobalHcmAdmin = auth()->user()?->isGlobalHcmAdmin() ?? false;
@endphp
@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper" data-tax-governance-page data-tax-governance-screen="{{ $taxGovernanceScreen }}" data-tax-governance-policy-uuid="{{ $taxGovernancePolicyUuid }}">
    <div class="content">
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Pengaturan Pajak Indonesia (PPh 21)</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">Administrasi</li>
                        <li class="breadcrumb-item active" aria-current="page">Pengaturan Pajak</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-primary" data-tax-governance-refresh>
                    <i class="ti ti-refresh me-1"></i>Muat Ulang
                </button>
                <button type="button" class="btn btn-white" data-tax-governance-export-json>
                    <i class="ti ti-file-type-json me-1"></i>Export JSON
                </button>
                <button type="button" class="btn btn-primary" data-tax-governance-export-pdf>
                    <i class="ti ti-file-type-pdf me-1"></i>Export PDF
                </button>
            </div>
        </div>

        <div class="alert alert-warning d-flex align-items-start gap-2 mb-3" role="alert">
            <i class="ti ti-alert-triangle mt-1"></i>
            <div>
                <div class="fw-semibold">Menu pajak sedang dimigrasi dari tax-rates lama ke pengaturan runtime.</div>
                <div class="small">Wording dan alur diselaraskan untuk konteks pajak Indonesia (PPh 21/TER) per role.</div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('tax-rates') }}" class="btn btn-sm {{ $taxGovernanceScreen === 'landing' ? 'btn-primary' : 'btn-light' }}">Ringkasan</a>
                    <a href="{{ route('tax-rates.policies') }}" class="btn btn-sm {{ $taxGovernanceScreen === 'tenant-policies' ? 'btn-primary' : 'btn-light' }}">Kebijakan PPh 21</a>
                    <a href="{{ route('tax-rates.approvals') }}" class="btn btn-sm {{ $taxGovernanceScreen === 'approvals' ? 'btn-primary' : 'btn-light' }}">Persetujuan</a>
                    <a href="{{ route('tax-rates.publications') }}" class="btn btn-sm {{ $taxGovernanceScreen === 'publications' ? 'btn-primary' : 'btn-light' }}">Publikasi</a>
                    <a href="{{ url('tax-rates/komponen-pajak') }}" class="btn btn-sm {{ $taxGovernanceScreen === 'komponen-pajak' ? 'btn-primary' : 'btn-light' }}">Komponen Pajak</a>
                    <a href="{{ route('tax-rates.reports') }}" class="btn btn-sm {{ $taxGovernanceScreen === 'tenant-reports' ? 'btn-primary' : 'btn-light' }}">Audit Tenant</a>
                    @if ($isGlobalHcmAdmin)
                        <a href="{{ route('tax-rates.governance') }}" class="btn btn-sm {{ $taxGovernanceScreen === 'global-governance' ? 'btn-primary' : 'btn-light' }}">Global Governance</a>
                        <a href="{{ route('tax-rates.platform-billing.policies') }}" class="btn btn-sm {{ $taxGovernanceScreen === 'platform-policies' ? 'btn-primary' : 'btn-light' }}">Platform Billing Policy</a>
                        <a href="{{ route('tax-rates.platform-billing.reports') }}" class="btn btn-sm {{ $taxGovernanceScreen === 'platform-reports' ? 'btn-primary' : 'btn-light' }}">Platform Billing Reports</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="alert alert-danger d-none" data-tax-governance-error></div>
        <div class="alert alert-info d-none" data-tax-platform-gate></div>

        @if (in_array($taxGovernanceScreen, ['landing', 'tenant-reports', 'global-governance'], true))
            <div class="row g-3 mb-3" data-tax-governance-summary>
                <div class="col-md-6 col-xl-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted fs-13 mb-1">Status Kepatuhan Pajak</div>
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
                            <div class="text-muted fs-13 mb-1">Tagihan Pajak Platform</div>
                            <h4 class="mb-1" data-tax-billing-outstanding>Rp 0</h4>
                            <div class="small text-muted" data-tax-billing-status>-</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-xl-5">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Tindak Lanjut Prioritas</h5>
                            <span class="badge bg-info-subtle text-info" data-tax-reporting-period>-</span>
                        </div>
                        <div class="card-body">
                            <ol class="list-group list-group-numbered" data-tax-action-list>
                                <li class="list-group-item text-muted">Memuat rekomendasi tindakan...</li>
                            </ol>
                        </div>
                    </div>
                </div>
                <div class="col-xl-7">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Daftar Anomali Pajak</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Tipe</th>
                                            <th>Prioritas</th>
                                            <th>Terdeteksi</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody data-tax-anomaly-table>
                                        <tr><td colspan="4" class="text-center text-muted py-4">Memuat data anomali...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Riwayat Perubahan Kebijakan</h5>
                    <small class="text-muted" data-tax-audit-period>-</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Versi</th>
                                    <th>Aksi</th>
                                    <th>Pelaku</th>
                                    <th>Waktu</th>
                                    <th>Ringkasan</th>
                                </tr>
                            </thead>
                            <tbody data-tax-event-table>
                                <tr><td colspan="5" class="text-center text-muted py-4">Memuat riwayat perubahan...</td></tr>
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
                                <tr><td colspan="7" class="text-center text-muted py-4">Memuat kebijakan tenant...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if ($taxGovernanceScreen === 'komponen-pajak')
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">Klasifikasi PPh 21 per Komponen Gaji</h5>
                        <span class="text-muted small">Flag ini menentukan komponen mana yang masuk bruto kena pajak (TER) saat payroll run.</span>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-tax-component-filter="all">Semua</button>
                        <button type="button" class="btn btn-sm btn-outline-success" data-tax-component-filter="addition">Pendapatan</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-tax-component-filter="deduction">Potongan</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Nama Komponen</th>
                                    <th>Jenis</th>
                                    <th>Kategori</th>
                                    <th class="text-center">
                                        Masuk Bruto TER<br>
                                        <span class="text-muted fw-normal" style="font-size:11px">(include_pph21_ter_gross)</span>
                                    </th>
                                    <th class="text-center">
                                        Rekonsiliasi Tahunan<br>
                                        <span class="text-muted fw-normal" style="font-size:11px">(include_pph21_annual_reconciliation)</span>
                                    </th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody data-tax-komponen-table>
                                <tr><td colspan="7" class="text-center text-muted py-4">Memuat komponen gaji...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-muted small">
                    <i class="ti ti-info-circle me-1"></i>Klik toggle untuk mengubah flag per komponen. Perubahan disimpan langsung dan berlaku pada payroll run berikutnya.
                </div>
            </div>
        @endif

        @if ($taxGovernanceScreen === 'policy-editor')
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Editor Kebijakan PPh 21</h5>
                    <span class="badge bg-info-subtle text-info" data-tax-editor-mode>Draft</span>
                </div>
                <div class="card-body">
                    <div class="alert alert-secondary mb-3" data-tax-editor-meta>
                        Referensi kebijakan: <strong data-tax-editor-policy-ref>{{ $taxGovernancePolicyUuid ?? 'draft-baru' }}</strong>
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
                        <div class="col-md-3">
                            <label class="form-label">Kelompok TER</label>
                            <input type="text" class="form-control" name="rateBracket" value="A" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tarif PPh 21 (%)</label>
                            <input type="number" class="form-control" name="rateValue" min="0" max="100" step="0.01" value="5" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Catatan Pengajuan</label>
                            <textarea class="form-control" rows="2" name="submissionNote" placeholder="Alasan submit untuk approver"></textarea>
                        </div>
                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-outline-secondary" data-tax-policy-validate>Pratinjau Validasi</button>
                            <button type="submit" class="btn btn-primary" data-tax-policy-save>Simpan Draft</button>
                            <button type="button" class="btn btn-outline-primary" data-tax-policy-submit>Ajukan Persetujuan</button>
                            <a href="{{ route('tax-rates.policies') }}" class="btn btn-light">Kembali ke Daftar</a>
                        </div>
                    </form>

                    <div class="alert alert-info mt-3 d-none" data-tax-policy-validation-preview></div>
                </div>
            </div>
        @endif

        @if ($taxGovernanceScreen === 'approvals')
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Kotak Persetujuan</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Kebijakan</th>
                                    <th>Pembuat</th>
                                    <th>Status</th>
                                    <th>Alasan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody data-tax-approval-table>
                                <tr><td colspan="5" class="text-center text-muted py-4">Memuat antrian persetujuan...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if ($taxGovernanceScreen === 'publications')
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Timeline Publikasi</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Kebijakan</th>
                                    <th>Status</th>
                                    <th>Versi Saat Ini</th>
                                    <th>Alasan Publikasi</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody data-tax-publication-table>
                                <tr><td colspan="5" class="text-center text-muted py-4">Memuat antrian publikasi...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if ($taxGovernanceScreen === 'global-governance')
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">Drilldown Tata Kelola Global</h5>
                    <select class="form-select w-auto" data-tax-governance-risk-filter>
                        <option value="">Semua Level Risiko</option>
                        <option value="green">Green</option>
                        <option value="yellow">Yellow</option>
                        <option value="red">Red</option>
                    </select>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Perusahaan</th>
                                    <th>Status Kebijakan</th>
                                    <th>Risk</th>
                                    <th>Anomaly</th>
                                    <th>Pajak Billing Terutang</th>
                                </tr>
                            </thead>
                            <tbody data-tax-governance-drilldown-table>
                                <tr><td colspan="5" class="text-center text-muted py-4">Memuat data drilldown governance...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if (in_array($taxGovernanceScreen, ['landing', 'platform-policies'], true))
            <div class="card mt-3">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">Master Pajak Billing Platform</h5>
                    <small class="text-muted">Hanya global admin</small>
                </div>
                <div class="card-body border-bottom">
                    <form class="row g-3" data-tax-platform-policy-form>
                        <div class="col-md-2">
                            <label class="form-label">ID Perusahaan</label>
                            <input type="number" min="1" class="form-control" name="company_id" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Bulan Billing</label>
                            <input type="month" class="form-control" name="billing_month" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Siklus</label>
                            <select class="form-select" name="billing_cycle_type" required>
                                <option value="monthly">Bulanan</option>
                                <option value="yearly">Tahunan</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tarif Pajak (%)</label>
                            <input type="number" class="form-control" name="tax_rate_percentage" min="0" max="100" step="0.01" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Berlaku Mulai</label>
                            <input type="date" class="form-control" name="effective_from" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="active">Aktif</option>
                                <option value="draft">Draft</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="small text-muted">Master kebijakan ini menjadi basis perhitungan pajak billing platform per bulan.</div>
                            <button type="submit" class="btn btn-primary" data-tax-platform-policy-submit>
                                <i class="ti ti-device-floppy me-1"></i>Simpan Kebijakan
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Perusahaan</th>
                                    <th>Bulan</th>
                                    <th>Siklus</th>
                                    <th>Tarif</th>
                                    <th>Status</th>
                                    <th>Masa Berlaku</th>
                                </tr>
                            </thead>
                            <tbody data-tax-platform-policy-table>
                                <tr><td colspan="6" class="text-center text-muted py-4">Memuat kebijakan platform...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if (in_array($taxGovernanceScreen, ['landing', 'platform-reports'], true))
            <div class="card mt-3 mb-3">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">Laporan Pajak Billing Platform</h5>
                    <div class="d-flex align-items-center gap-2">
                        <input type="month" class="form-control" data-tax-platform-report-month>
                        <button type="button" class="btn btn-outline-primary" data-tax-platform-report-refresh>
                            <i class="ti ti-refresh me-1"></i>Muat
                        </button>
                    </div>
                </div>
                <div class="card-body border-bottom">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted fs-13 mb-1">Jumlah Tenant</div>
                                <h5 class="mb-0" data-tax-platform-summary-tenant-count>0</h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted fs-13 mb-1">Tenant Dengan Kebijakan</div>
                                <h5 class="mb-0" data-tax-platform-summary-tenant-with-policy>0</h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted fs-13 mb-1">Total Pajak Terutang</div>
                                <h5 class="mb-0" data-tax-platform-summary-tax-due>Rp 0</h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100">
                                <div class="text-muted fs-13 mb-1">Invoice Belum Dibayar</div>
                                <h5 class="mb-0" data-tax-platform-summary-unpaid>0</h5>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Perusahaan</th>
                                    <th>Bulan</th>
                                    <th>Tarif</th>
                                    <th>Invoice</th>
                                    <th>Dibayar</th>
                                    <th>Belum Dibayar</th>
                                    <th>Total Invoice</th>
                                    <th>Pajak Terutang</th>
                                </tr>
                            </thead>
                            <tbody data-tax-platform-report-table>
                                <tr><td colspan="8" class="text-center text-muted py-4">Memuat laporan pajak billing...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
