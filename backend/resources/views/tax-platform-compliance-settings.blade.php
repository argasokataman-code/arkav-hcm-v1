<?php $page = 'tax-rates'; ?>
@php
    $taxGovernanceScreen = $taxGovernanceScreen ?? 'platform-tax-compliance';
    $taxGovernancePolicyUuid = null;
@endphp
@extends('layout.mainlayout')
@section('content')
<div class="page-wrapper"
    data-tax-governance-page
    data-tax-governance-screen="{{ $taxGovernanceScreen }}"
    data-tax-governance-policy-uuid="{{ $taxGovernancePolicyUuid }}"
     role="main"
    aria-label="Platform Finance Government Tax Compliance">
    <div class="content">
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Platform Finance - Government Tax & Compliance</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">Settings</li>
                        <li class="breadcrumb-item active" aria-current="page">Platform Finance - Government Tax & Compliance</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap gap-2">
                <button type="button" class="btn btn-outline-info d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#platformTaxComplianceGuideModal">
                    <i class="ti ti-info-circle me-1"></i>Panduan
                </button>
                <div class="mb-2">
                    <div class="dropdown">
                        <a href="javascript:void(0);" class="dropdown-toggle btn btn-white d-inline-flex align-items-center" data-bs-toggle="dropdown">
                            <i class="ti ti-file-export me-1"></i>Export
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end p-3">
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                    <i class="ti ti-file-type-pdf me-1"></i>Export as PDF
                                </a>
                            </li>
                            <li>
                                <a href="javascript:void(0);" class="dropdown-item rounded-1">
                                    <i class="ti ti-file-type-csv me-1"></i>Export as CSV
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="head-icons ms-2">
                    <a href="javascript:void(0);" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-original-title="Collapse" id="collapse-header">
                        <i class="ti ti-chevrons-up"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="modal fade" id="platformTaxComplianceGuideModal" tabindex="-1" aria-labelledby="platformTaxComplianceGuideModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="platformTaxComplianceGuideModalLabel">Panduan Ringkas Government Tax & Compliance</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <ul class="mb-0 ps-3">
                            <li>Layer ini khusus untuk kewajiban pajak platform ke pemerintah.</li>
                            <li>Tarif bisa diubah mengikuti regulasi (misalnya PPh 21 dan corporate tax).</li>
                            <li>Rekap di halaman ini dipakai untuk tax payable dan evidence compliance global platform.</li>
                            <li>Domain ini berbeda dari Billing & Revenue yang fokus ke charge/invoice tenant.</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Mengerti</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info d-flex align-items-start gap-2 mb-3" role="alert">
            <i class="ti ti-shield-check mt-1"></i>
            <div>
                <div class="fw-semibold">Layer ini khusus domain kewajiban pajak platform ke pemerintah.</div>
                <div class="small">Perhitungan tenant billing tetap dikelola di menu Billing & Revenue.</div>
            </div>
        </div>

        <div class="alert alert-danger d-none" data-tax-governance-error></div>
        <div class="alert alert-info d-none" data-tax-platform-gate></div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Konfigurasi Government Tax & Compliance</h5>
                <small class="text-muted">Kebijakan pajak platform untuk perhitungan payable ke pemerintah</small>
            </div>
            <div class="card-body">
                <form class="row g-3" data-tax-platform-policy-form>
                    <div class="col-md-3">
                        <label class="form-label">Platform Revenue Tax Rate (%)</label>
                        <input type="number" class="form-control" name="subscription_tax_rate" min="0" max="100" step="0.01" placeholder="Misal: 15.00" required>
                        <small class="text-muted">Tarif pajak atas basis revenue platform</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tarif Komponen Payroll Service (%)</label>
                        <input type="number" class="form-control" name="payroll_service_fee" min="0" max="100" step="0.01" placeholder="Misal: 2.00" required>
                        <small class="text-muted">Komponen tarif untuk stream payroll service</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tarif Komponen Add-on (%)</label>
                        <input type="number" class="form-control" name="addon_markup_rate" min="0" max="100" step="0.01" placeholder="Misal: 22.00" required>
                        <small class="text-muted">Komponen tarif untuk stream add-on</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status Regulasi</label>
                        <select class="form-select" name="status">
                            <option value="active">Aktif</option>
                            <option value="draft">Draft</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                        <small class="text-muted">Kontrol penerapan kebijakan</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Efektif Berlaku</label>
                        <input type="date" class="form-control" name="effective_from">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Catatan Perubahan Regulasi</label>
                        <input type="text" class="form-control" name="notes" placeholder="Misal: Penyesuaian PMK terbaru untuk periode Q3 2026">
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary" data-tax-platform-policy-submit>
                            <i class="ti ti-device-floppy me-1"></i>Simpan Kebijakan Compliance
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted fs-13 mb-1">Gross Revenue Platform</div>
                        <h4 class="mb-1" data-tax-compliance-summary-gross>Rp 0</h4>
                        <div class="small text-muted">Sebelum potongan pajak</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted fs-13 mb-1">Pajak Terutang</div>
                        <h4 class="mb-1" data-tax-compliance-summary-tax-due>Rp 0</h4>
                        <div class="small text-muted">Akumulasi kewajiban pajak berdasarkan policy aktif</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                            <div class="text-muted fs-13 mb-1">Net Revenue Setelah Pajak</div>
                        <h4 class="mb-1" data-tax-compliance-summary-net-profit>Rp 0</h4>
                        <div class="small text-muted">Setelah kewajiban pajak</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted fs-13 mb-1">Effective Tax Rate</div>
                        <h4 class="mb-1" data-tax-compliance-summary-effective-rate>0%</h4>
                        <div class="small text-muted">Rasio pajak terhadap gross revenue</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Rekap Kewajiban Pajak Platform</h5>
                <div class="d-flex align-items-center gap-2">
                    <input type="month" class="form-control" data-tax-platform-report-month>
                    <button type="button" class="btn btn-outline-primary" data-tax-platform-report-refresh>
                        <i class="ti ti-refresh me-1"></i>Muat
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Scope Revenue</th>
                                <th>Taxable Revenue (Rp)</th>
                                <th>Komponen Payroll Service (Rp)</th>
                                <th>Komponen Add-on (Rp)</th>
                                <th>Total Tax Payable (Rp)</th>
                                <th>Net Revenue (Rp)</th>
                                <th>Compliance State</th>
                            </tr>
                        </thead>
                        <tbody data-tax-platform-report-table>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Memuat rekap compliance per tenant scope...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
