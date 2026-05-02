<?php $page = 'tax-rates'; ?>
@php
    $taxGovernanceScreen = $taxGovernanceScreen ?? 'platform-billing';
    $taxGovernancePolicyUuid = null;
@endphp
@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper"
     data-tax-governance-page
     data-tax-governance-screen="{{ $taxGovernanceScreen }}"
     data-tax-governance-policy-uuid="{{ $taxGovernancePolicyUuid }}"
     role="main"
     aria-label="Pricing and Plans">
    <div class="content">

        {{-- Breadcrumb & Actions --}}
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Pricing &amp; Plans</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">Settings</li>
                        <li class="breadcrumb-item active" aria-current="page">Pricing &amp; Plans</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-primary" data-tax-governance-refresh>
                    <i class="ti ti-refresh me-1"></i>Muat Ulang
                </button>
            </div>
        </div>

        {{-- Context banner --}}
        <div class="alert alert-info d-flex align-items-start gap-2 mb-3" role="alert">
            <i class="ti ti-info-circle mt-1"></i>
            <div>
                <div class="fw-semibold">Halaman ini adalah katalog produk SaaS: subscription plan dan add-on yang dijual ke tenant.</div>
                <div class="small">
                    Harga produk ditampilkan dalam Rupiah (Rp), bukan persentase.
                    Untuk pajak platform ke pemerintah, gunakan menu
                    <a href="{{ route('platform-tax-compliance.policies') }}">Government Tax &amp; Compliance</a>.
                </div>
            </div>
        </div>

        <div class="alert alert-danger d-none" data-tax-governance-error></div>
        <div class="alert alert-info d-none" data-tax-platform-gate></div>

        {{-- ─── SECTION 1: Subscription Plans (read-only) ─── --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0"><i class="ti ti-package me-2 text-primary"></i>Subscription Plans</h5>
                    <small class="text-muted">Data dari modul Packages — baca saja. Edit plan melalui halaman Packages.</small>
                </div>
                <a href="{{ url('saas/packages') }}" class="btn btn-outline-primary btn-sm">
                    <i class="ti ti-external-link me-1"></i>Kelola Plans
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Nama Plan</th>
                                <th>Harga Bulanan</th>
                                <th>Harga Tahunan</th>
                                <th>Billing Unit</th>
                                <th>Fitur</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody data-pricing-plans-table>
                            <tr><td colspan="7" class="text-center text-muted py-4">Memuat subscription plans...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ─── SECTION 2: Add-on Catalog ─── --}}
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="ti ti-puzzle me-2 text-purple"></i>Add-on Catalog</h5>
                <small class="text-muted">Fitur tambahan yang dapat dibeli tenant secara terpisah. Harga dalam Rupiah.</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Nama Add-on</th>
                                <th>Harga / Unit (Rp)</th>
                                <th>Unit</th>
                                <th>Status</th>
                                <th>Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody data-pricing-addons-table>
                            <tr><td colspan="6" class="text-center text-muted py-4">Memuat add-on catalog...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ─── SECTION 4: Revenue Summary (read-only) ─── --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0"><i class="ti ti-chart-bar me-2 text-success"></i>Revenue Summary</h5>
                    <small class="text-muted">Ringkasan pendapatan platform — <strong>sebelum pajak</strong>. Baca saja, bukan konfigurasi.</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <input type="month" class="form-control" data-tax-platform-report-month>
                    <button type="button" class="btn btn-outline-primary" data-tax-platform-report-refresh>
                        <i class="ti ti-refresh me-1"></i>Muat
                    </button>
                </div>
            </div>
            <div class="card-body border-bottom">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted fs-13 mb-1">Total Subscription Revenue</div>
                            <h5 class="mb-0" data-tax-platform-summary-subscription-revenue>Rp 0</h5>
                            <small class="text-muted">Sebelum pajak</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted fs-13 mb-1">Total Add-on Revenue</div>
                            <h5 class="mb-0" data-tax-platform-summary-addon-revenue>Rp 0</h5>
                            <small class="text-muted">Dari fitur tambahan</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted fs-13 mb-1">Total Platform Revenue</div>
                            <h5 class="mb-0" data-tax-platform-summary-net-revenue>Rp 0</h5>
                            <small class="text-success">Akumulasi semua stream — sebelum pajak</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Tenant</th>
                                <th>Plan</th>
                                <th>Subscription (Rp)</th>
                                <th>Add-on (Rp)</th>
                                <th>Gross Revenue (Rp)</th>
                                <th>Billing Charge (Rp)</th>
                                <th>Total Revenue (Rp)</th>
                            </tr>
                        </thead>
                        <tbody data-tax-platform-report-table>
                            <tr><td colspan="7" class="text-center text-muted py-4">Pilih bulan dan klik Muat untuk melihat ringkasan revenue.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    {{-- ─── Edit Harga Add-on Modal ─── --}}
    <div class="modal fade" id="addonCrudModal" tabindex="-1"
         aria-labelledby="addonCrudModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addonCrudModalLabel">Edit Harga Add-on</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form data-pricing-addon-form novalidate>
                    <input type="hidden" name="addon_id" value="">
                    <input type="hidden" name="code" value="">
                    <div class="modal-body">
                        <p class="text-muted small mb-3">
                            Ubah harga add-on <strong data-addon-name-display></strong>
                            (<code data-addon-code-display></code>).
                        </p>
                        <div class="mb-3">
                            <label class="form-label">Harga per Unit (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" name="price_per_unit"
                                       min="0" step="1000" placeholder="Misal: 150000" required>
                            </div>
                            <small class="text-muted">Harga tetap dalam Rupiah - bukan persentase.</small>
                        </div>
                        <div class="alert alert-danger d-none mb-0" data-pricing-addon-form-error></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" data-pricing-addon-submit>
                            <i class="ti ti-device-floppy me-1"></i>Simpan Harga
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection
