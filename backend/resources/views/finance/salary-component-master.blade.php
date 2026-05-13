<?php $page = 'salary-component-master'; ?>
@extends('layout.mainlayout')
@section('content')

    <div class="page-wrapper">
        <div class="content">

            <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
                <div class="my-auto mb-2">
                    <h2 class="mb-1">Salary Component</h2>
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                            </li>
                            <li class="breadcrumb-item">
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Salary Component</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                    <div class="me-2 mb-2">
                        <a href="{{ url('tax-rates') }}" class="btn btn-white d-inline-flex align-items-center">
                            <i class="ti ti-receipt-tax me-1"></i>Tax Rates
                        </a>
                    </div>
                    <div class="head-icons ms-2">
                        <a href="javascript:void(0);" class="" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                            <i class="ti ti-chevrons-up"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="alert alert-info border mb-3" role="alert">
                <strong>Dampak perubahan:</strong>
                Perubahan Salary Component berlaku ke payroll draft berikutnya.
                Jika draft lama perlu disesuaikan, lakukan void lalu hitung ulang dari
                <a href="{{ url('payroll-run') }}" class="alert-link fw-semibold">Payroll Run Bulanan</a>
                selama status belum paid.
            </div>

            <div class="alert alert-light border d-flex align-items-start gap-3 mb-3" role="alert">
                <i class="ti ti-lock fs-5 text-secondary mt-1 flex-shrink-0"></i>
                <div>
                    <strong>Governance lock aktif</strong> — Komponen dengan badge modul dikelola dari halaman governance terkait. Edit/hapus langsung untuk komponen tersebut dinonaktifkan pada halaman ini.
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-2">
                    <div>
                        <h5 class="mb-1">Compliance Monitor</h5>
                        <span class="text-muted small">Ringkasan anomali konfigurasi lintas modul governance (BPJS, PPh21, Allowance, dan modul payroll lainnya).</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3" data-hcm-salary-compliance-summary>
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100 bg-light">
                                <div class="text-muted small">Score</div>
                                <div class="fs-5 fw-semibold">Loading...</div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive mt-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Severity</th>
                                    <th>Component</th>
                                    <th>Temuan</th>
                                    <th>Tindak lanjut</th>
                                </tr>
                            </thead>
                            <tbody data-hcm-salary-compliance-body>
                                <tr><td colspan="4" class="text-center text-muted py-3">Memuat analisis...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                    <div>
                        <h5 class="mb-1">Salary Component</h5>
                        <span class="text-muted small">Audit dua sisi: master komponen gaji dan profil integrasi karyawan terhadap allowance/payroll assignments.</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="javascript:void(0);" class="btn btn-light btn-sm d-inline-flex align-items-center"
                           data-bs-toggle="modal" data-bs-target="#arcav_salary_component_guide">
                            <i class="ti ti-info-circle me-1"></i>Panduan
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="px-3 pt-3 pb-0 border-bottom" style="background:#f8fafc;">
                        <div class="nav nav-pills gap-2 pb-3" data-hcm-salary-view-tabs role="tablist" aria-label="Tampilan salary component">
                            <button type="button" class="btn btn-sm btn-primary" data-hcm-tab-view="components" role="tab" aria-selected="true">Registry Komponen</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-hcm-tab-view="profiles" role="tab" aria-selected="false">Profil Integrasi Karyawan</button>
                        </div>
                    </div>

                    <div data-hcm-salary-view="components">
                    <div class="px-3 pt-3 pb-0 border-bottom bg-light">
                        <div class="nav nav-pills flex-nowrap gap-2 overflow-auto pb-2" data-hcm-salary-category-tabs role="tablist" aria-label="Filter komponen gaji per kelompok">
                            <button type="button" class="btn btn-sm btn-primary" role="tab" aria-selected="true">Semua kategori</button>
                        </div>
                    </div>
                    <div class="custom-datatable-filter table-responsive">
                        <table class="table">
                            <thead class="thead-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama</th>
                                    <th>Jenis</th>
                                    <th>Kategori</th>
                                    <th>Integrasi</th>
                                    <th>Default %</th>
                                    <th>Dasar hukum</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody data-hcm-salary-components-body>
                                <tr><td colspan="9" class="text-center text-muted py-4">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                    </div>

                    <div class="d-none" data-hcm-salary-view="profiles">
                        <div class="px-3 pt-3 pb-2 border-bottom bg-light">
                            <div class="row g-2" data-hcm-salary-profile-summary>
                                <div class="col-md-4">
                                    <div class="border rounded p-2 h-100 bg-white text-muted small">Memuat ringkasan integrasi...</div>
                                </div>
                            </div>
                        </div>
                        <div class="custom-datatable-filter table-responsive">
                            <table class="table">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Karyawan</th>
                                        <th>Organisasi</th>
                                        <th>Take Home Pay</th>
                                        <th>Status Integrasi</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody data-hcm-salary-profile-body>
                                    <tr><td colspan="5" class="text-center text-muted py-4">Loading…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @component('components.modal-popup')
    @endcomponent

    @include('hcm.partials.salary-component-modals')
    @include('hcm.partials.salary-component-guide-modal')

    {{-- Employee Integration Profile Detail Modal --}}
    <div class="modal fade" id="arcav_employee_profile_detail" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="arcav_employee_profile_detail_title">Detail Profil Integrasi Karyawan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="arcav_employee_profile_detail_body">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

@endsection
