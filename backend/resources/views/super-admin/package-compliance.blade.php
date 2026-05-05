@php $page = 'super-admin-package-compliance'; @endphp
@extends('layout.mainlayout')

@section('content')
<div class="page-wrapper">
<div class="content">
<!-- Page Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col">
            <h3 class="page-title">Package Compliance</h3>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('dashboard') }}">Super Admin</a></li>
                <li class="breadcrumb-item active">Package Compliance</li>
            </ul>
        </div>
        <div class="col-auto d-flex align-items-center gap-2">
            <button id="btn-refresh-compliance" class="btn btn-sm btn-outline-primary">
                <i class="ti ti-refresh me-1"></i>Refresh
            </button>
            <button id="btn-export-compliance" class="btn btn-sm btn-outline-success">
                <i class="ti ti-download me-1"></i>Export CSV
            </button>
        </div>
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:3rem;height:3rem;background:rgba(220,53,69,.12);">
                    <i class="ti ti-alert-triangle fs-5 text-danger"></i>
                </div>
                <div>
                    <div id="pc-stat-violation" class="fw-bold fs-4 text-danger lh-1">—</div>
                    <div class="text-muted small mt-1">Violation</div>
                    <div class="text-muted" style="font-size:.7rem;">Melebihi limit paket</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:3rem;height:3rem;background:rgba(255,193,7,.15);">
                    <i class="ti ti-alert-circle fs-5 text-warning"></i>
                </div>
                <div>
                    <div id="pc-stat-warning" class="fw-bold fs-4 text-warning lh-1">—</div>
                    <div class="text-muted small mt-1">Warning</div>
                    <div class="text-muted" style="font-size:.7rem;">≥80% kapasitas</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:3rem;height:3rem;background:rgba(25,135,84,.12);">
                    <i class="ti ti-circle-check fs-5 text-success"></i>
                </div>
                <div>
                    <div id="pc-stat-compliant" class="fw-bold fs-4 text-success lh-1">—</div>
                    <div class="text-muted small mt-1">Compliant</div>
                    <div class="text-muted" style="font-size:.7rem;">Di bawah 80%</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:3rem;height:3rem;background:rgba(108,117,125,.1);">
                    <i class="ti ti-infinity fs-5 text-secondary"></i>
                </div>
                <div>
                    <div id="pc-stat-unlimited" class="fw-bold fs-4 text-secondary lh-1">—</div>
                    <div class="text-muted small mt-1">Unlimited</div>
                    <div class="text-muted" style="font-size:.7rem;">Tanpa batas</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0 pb-0 pt-3 px-4">
                <h6 class="mb-0 fw-semibold">Distribusi Compliance Status</h6>
                <p class="text-muted small mb-0">Snapshot semua tenant aktif</p>
            </div>
            <div class="card-body pt-2">
                <div id="pc-donut-chart" style="min-height:240px;"></div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header border-0 pb-0 pt-3 px-4">
                <h6 class="mb-0 fw-semibold">Usage % per Tenant</h6>
                <p class="text-muted small mb-0">Perbandingan employee aktual vs limit paket (0% = unlimited)</p>
            </div>
            <div class="card-body pt-2">
                <div id="pc-bar-chart" style="min-height:240px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar + Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header border-0 pt-3 pb-0 px-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h6 class="mb-0 fw-semibold">Quota Monitor per Tenant</h6>
                <p class="text-muted small mb-0">
                    Total <span id="pc-total-tenants">—</span> tenant aktif terpantau
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <!-- Status Filter -->
                <div id="pc-filter-group" class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary pc-filter-btn active" data-filter="all">Semua</button>
                    <button type="button" class="btn btn-outline-danger pc-filter-btn" data-filter="violation">
                        <i class="ti ti-alert-triangle me-1"></i>Violation
                    </button>
                    <button type="button" class="btn btn-outline-warning pc-filter-btn" data-filter="warning">
                        <i class="ti ti-alert-circle me-1"></i>Warning
                    </button>
                    <button type="button" class="btn btn-outline-success pc-filter-btn" data-filter="compliant">Compliant</button>
                    <button type="button" class="btn btn-outline-secondary pc-filter-btn" data-filter="unlimited">Unlimited</button>
                </div>
                <!-- Search -->
                <input type="text" id="pc-search-input" class="form-control form-control-sm"
                       placeholder="Cari company / package..." style="min-width:200px;">
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="pc-table" class="table table-hover mb-0 align-middle" style="font-size:.875rem;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="min-width:180px;">Company</th>
                        <th style="min-width:120px;">Package</th>
                        <th class="text-center" style="width:90px;">Sub Status</th>
                        <th class="text-center" style="width:70px;">Limit</th>
                        <th class="text-center" style="width:70px;">Actual</th>
                        <th class="text-center" style="width:70px;">Excess</th>
                        <th style="min-width:130px;">Usage</th>
                        <th class="text-center" style="width:110px;">Compliance</th>
                        <th class="text-center" style="width:80px;">Action</th>
                    </tr>
                </thead>
                <tbody id="pc-tbody">
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            Memuat data compliance...
                        </td>
                    </tr>
                </tbody>
                <tfoot id="pc-tfoot"></tfoot>
            </table>
        </div>
    </div>
</div>
</div>{{-- .content --}}
</div>{{-- .page-wrapper --}}
@endsection

@push('scripts')
<script src="{{ asset('build/js/super-admin/package-compliance.js') }}?v={{ filemtime(public_path('build/js/super-admin/package-compliance.js')) }}"></script>
@endpush
