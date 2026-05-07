<?php $page = 'company-overview'; ?>
@extends('layout.mainlayout')
@section('content')

<style>
    .company-profile-completion-card {
        border: 1px solid rgba(13, 110, 253, 0.16);
        background: linear-gradient(120deg, rgba(13, 110, 253, 0.06), rgba(25, 135, 84, 0.05));
        overflow: hidden;
    }

    .company-profile-completion-bar {
        height: 10px;
        background: #e9ecef;
        border-radius: 999px;
        overflow: hidden;
    }

    .company-profile-completion-fill {
        display: block;
        height: 100%;
        width: 0;
        border-radius: 999px;
        background: linear-gradient(90deg, #0d6efd, #20c997);
        transition: width 0.9s ease;
        box-shadow: 0 0 0 rgba(13, 110, 253, 0.45);
        animation: co-completion-pulse 1.8s ease-in-out infinite;
    }

    @keyframes co-completion-pulse {
        0% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.36); }
        70% { box-shadow: 0 0 0 12px rgba(13, 110, 253, 0); }
        100% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0); }
    }

    .company-profile-missing-list {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
</style>

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content">

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h6 class="fw-medium d-inline-flex align-items-center mb-3 mb-sm-0">
                    <i class="ti ti-building-community me-2"></i>Company Profile
                </h6>
            </div>
            <div class="ms-2">
                <a href="{{ url('company-profile') }}" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-settings me-1"></i>Edit Profile
                </a>
            </div>
        </div>
        <!-- /Breadcrumb -->

        <div class="row">
            <!-- Left Column: Company Identity Card -->
            <div class="col-xl-4 theiaStickySidebar">
                <div class="card card-bg-1">
                    <div class="card-body p-0">
                        <!-- Logo & Name -->
                        <div class="text-center px-3 pb-3 border-bottom pt-3">
                            <div class="avatar avatar-xxl avatar-rounded border border-2 border-white m-auto d-flex mb-2 align-items-center justify-content-center bg-white text-primary fw-bold overflow-hidden">
                                <i class="ti ti-building-community fs-24 text-primary" data-company-logo-placeholder></i>
                            </div>
                            <h5 class="d-flex align-items-center justify-content-center mb-1 text-dark">
                                <span data-company-name>—</span>
                            </h5>
                            <span class="badge badge-soft-dark fw-medium me-2">
                                <i class="ti ti-point-filled me-1"></i><span data-company-legal-name>—</span>
                            </span>
                        </div>

                        <!-- Company Details -->
                        <div class="p-3 border-bottom">
                            <h6 class="mb-2">Identitas Perusahaan</h6>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span><i class="ti ti-building me-2"></i>Nama Resmi</span>
                                <p class="text-dark text-end" data-company-name-display>—</p>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span><i class="ti ti-file-certificate me-2"></i>Nama Legal</span>
                                <p class="text-dark text-end" data-company-legal-name-display>—</p>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span><i class="ti ti-id me-2"></i>NPWP</span>
                                <p class="text-dark" data-company-npwp>—</p>
                            </div>
                        </div>

                        <!-- Address -->
                        <div class="p-3 border-bottom">
                            <h6 class="mb-2">Alamat</h6>
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <span><i class="ti ti-map-pin me-2"></i>Alamat</span>
                                <p class="text-dark text-end" data-company-address>—</p>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span><i class="ti ti-map me-2"></i>Kota</span>
                                <p class="text-dark" data-company-city>—</p>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span><i class="ti ti-flag me-2"></i>Provinsi</span>
                                <p class="text-dark" data-company-state>—</p>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span><i class="ti ti-world me-2"></i>Negara</span>
                                <p class="text-dark" data-company-country>—</p>
                            </div>
                            <div class="d-flex align-items-center justify-content-between">
                                <span><i class="ti ti-mailbox me-2"></i>Kode Pos</span>
                                <p class="text-dark" data-company-postal-code>—</p>
                            </div>
                        </div>

                        <!-- Owner Info -->
                        <div class="p-3">
                            <h6 class="mb-2">Owner</h6>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span><i class="ti ti-user me-2"></i>Nama</span>
                                <p class="text-dark" data-owner-name>—</p>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span><i class="ti ti-mail me-2"></i>Email</span>
                                <p class="text-dark text-end" data-owner-email>—</p>
                            </div>
                            <div class="d-flex align-items-center justify-content-between">
                                <span><i class="ti ti-phone me-2"></i>Telepon</span>
                                <p class="text-dark" data-owner-phone>—</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /Left Column -->

            <!-- Right Column: Tabs -->
            <div class="col-xl-8">

                <!-- Company Profile Completion Reminder -->
                <div class="card company-profile-completion-card mb-3" data-co-completion-card>
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                            <div>
                                <h5 class="mb-1">
                                    <i class="ti ti-bulb me-1 text-primary"></i>
                                    Lengkapi Profil Perusahaan
                                </h5>
                                <p class="text-muted mb-0" data-co-completion-subtitle>
                                    Biar tim kamu lebih mudah verifikasi data legal, alamat, dan kontak perusahaan.
                                </p>
                            </div>
                            <a href="{{ url('company-profile') }}" class="btn btn-sm btn-primary">
                                <i class="ti ti-edit me-1"></i>Lengkapi Sekarang
                            </a>
                        </div>

                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-medium">Progress Kelengkapan</span>
                            <span class="badge badge-soft-primary fs-13" data-co-completion-percent>0%</span>
                        </div>
                        <div class="company-profile-completion-bar mb-2" role="progressbar" aria-label="Progress kelengkapan profil perusahaan" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-co-completion-progress>
                            <span class="company-profile-completion-fill" data-co-completion-fill></span>
                        </div>
                        <p class="text-muted fs-12 mb-2" data-co-completion-detail>Masih ada 11 field penting yang perlu dilengkapi.</p>

                        <div class="company-profile-missing-list" data-co-completion-missing>
                            <span class="badge badge-soft-danger">Memuat data...</span>
                        </div>
                    </div>
                </div>
                <!-- /Company Profile Completion Reminder -->

                <!-- Stats Row -->
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="avatar avatar-lg bg-soft-primary rounded">
                                    <i class="ti ti-users fs-20 text-primary"></i>
                                </div>
                                <div>
                                    <p class="text-muted fs-12 mb-1">Total Karyawan</p>
                                    <h4 class="mb-0 fw-bold" data-co-stat-total>—</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="avatar avatar-lg bg-soft-success rounded">
                                    <i class="ti ti-user-check fs-20 text-success"></i>
                                </div>
                                <div>
                                    <p class="text-muted fs-12 mb-1">Aktif</p>
                                    <h4 class="mb-0 fw-bold" data-co-stat-active>—</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="avatar avatar-lg bg-soft-danger rounded">
                                    <i class="ti ti-user-off fs-20 text-danger"></i>
                                </div>
                                <div>
                                    <p class="text-muted fs-12 mb-1">Tidak Aktif</p>
                                    <h4 class="mb-0 fw-bold" data-co-stat-inactive>—</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /Stats Row -->

                <!-- SPT Masa Section -->
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">
                            <i class="ti ti-receipt-tax me-2 text-primary"></i>SPT Masa PPh 21
                        </h5>
                        <a href="{{ url('tax-employees') }}" class="btn btn-sm btn-outline-primary">
                            <i class="ti ti-external-link me-1"></i>Kelola SPT
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="alert alert-info m-3 d-none" data-co-spt-empty>
                            <i class="ti ti-info-circle me-2"></i>Belum ada data SPT Masa. Buat SPT pertama di menu <a href="{{ url('tax-employees') }}">Tax Employees</a>.
                        </div>
                        <div class="alert alert-warning m-3 d-none" data-co-spt-error>
                            <i class="ti ti-alert-triangle me-2"></i><span data-co-spt-error-msg>Gagal memuat data SPT.</span>
                        </div>
                        <div class="table-responsive d-none" data-co-spt-table>
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Periode</th>
                                        <th>Tahun</th>
                                        <th>Status</th>
                                        <th>Total PPh</th>
                                        <th class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody data-co-spt-tbody>
                                    <!-- filled by JS -->
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center py-4" data-co-spt-loading>
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <p class="mt-2 text-muted fs-12">Memuat data SPT...</p>
                        </div>
                    </div>
                </div>
                <!-- /SPT Masa Section -->

                <!-- Tax Governance Section -->
                <div class="card mt-3">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">
                            <i class="ti ti-scale me-2 text-warning"></i>Kebijakan Pajak (PPh21 Governance)
                        </h5>
                        <a href="{{ url('tax-employees/policies') }}" class="btn btn-sm btn-outline-warning">
                            <i class="ti ti-external-link me-1"></i>Kelola Kebijakan
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="alert alert-info m-3 d-none" data-co-tax-empty>
                            <i class="ti ti-info-circle me-2"></i>Belum ada kebijakan pajak aktif. Buat di menu <a href="{{ url('tax-employees/policies') }}">Tax Policies</a>.
                        </div>
                        <div class="alert alert-warning m-3 d-none" data-co-tax-error>
                            <i class="ti ti-alert-triangle me-2"></i><span data-co-tax-error-msg>Gagal memuat kebijakan pajak.</span>
                        </div>
                        <div class="table-responsive d-none" data-co-tax-table>
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nama Kebijakan</th>
                                        <th>Metode</th>
                                        <th>Status</th>
                                        <th class="text-end">Berlaku Sejak</th>
                                    </tr>
                                </thead>
                                <tbody data-co-tax-tbody>
                                    <!-- filled by JS -->
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center py-4" data-co-tax-loading>
                            <div class="spinner-border spinner-border-sm text-warning" role="status"></div>
                            <p class="mt-2 text-muted fs-12">Memuat kebijakan pajak...</p>
                        </div>
                    </div>
                </div>
                <!-- /Tax Governance Section -->

            </div>
            <!-- /Right Column -->
        </div>

    </div>
</div>
<!-- /Page Wrapper -->

@endsection
