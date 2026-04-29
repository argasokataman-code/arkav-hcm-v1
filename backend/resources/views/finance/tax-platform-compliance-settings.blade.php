<?php $page = 'tax-rates'; ?>
@php
    $taxGovernanceScreen = $taxGovernanceScreen ?? 'platform-tax-compliance';
    $taxGovernancePolicyUuid = null;
@endphp
@extends('layout.mainlayout')
@section('content')
<style>
    .tax-platform-report-table th:nth-child(2),
    .tax-platform-report-table td:nth-child(2) {
        min-width: 240px;
    }

    .tax-cycle-card {
        display: flex;
        flex-direction: column;
        gap: 0.55rem;
        padding: 0.8rem 0.9rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 0.85rem;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
    }

    .tax-cycle-card__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .tax-cycle-card__title {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #64748b;
    }

    .tax-cycle-card__meta {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .tax-cycle-card__item {
        display: flex;
        flex-direction: column;
        gap: 0.12rem;
    }

    .tax-cycle-card__label {
        font-size: 0.78rem;
        color: #64748b;
    }

    .tax-cycle-card__value {
        font-size: 0.82rem;
        font-weight: 600;
        color: #0f172a;
        line-height: 1.35;
        white-space: normal;
        word-break: break-word;
    }

    .tax-cycle-card__value--muted {
        color: #475569;
    }
</style>
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
                <div class="fw-semibold">Pemisahan layer pajak: PPN transaksi, PPh Badan, dan tracking pembayaran PPh 25.</div>
                <div class="small">Jangan gabungkan pajak yang dipungut dari tenant dengan pajak yang menjadi beban platform.</div>
            </div>
        </div>

        <div class="alert alert-danger d-none" data-tax-governance-error></div>
        <div class="alert alert-info d-none" data-tax-platform-gate></div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Konfigurasi Government Tax & Compliance</h5>
                <small class="text-muted">Pisahkan layer pajak tidak langsung, pajak langsung, dan histori pembayaran</small>
            </div>
            <div class="card-body">
                <form class="row g-3" data-tax-platform-policy-form>
                    <input type="hidden" name="addon_markup_rate" value="0">

                    <div class="col-12">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                <h6 class="mb-0">Transaction Tax (PPN)</h6>
                                <span class="badge bg-info-subtle text-info">Collected from tenant (Tax Liability)</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Tax Name</label>
                                    <div class="form-control-plaintext fw-semibold px-2">PPN</div>
                                    <input type="hidden" name="transaction_tax_name" value="PPN">
                                    <small class="text-muted">Pajak tidak langsung per transaksi.</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Tax Rate (%)</label>
                                    <input type="number" class="form-control" name="transaction_tax_rate" min="0" max="100" step="0.01" placeholder="Misal: 11.00">
                                    <small class="text-muted">Dipungut dari tenant, bukan expense platform.</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Applies To (Backend-Governed)</label>
                                    <div class="d-flex flex-wrap gap-2 pt-1">
                                        <span class="badge bg-primary-subtle text-primary">Subscription</span>
                                        <span class="badge bg-primary-subtle text-primary">Add-ons</span>
                                    </div>
                                    <small class="text-muted d-block mt-1">Scope komponen mengikuti rule taxability di backend billing, bukan checklist manual di halaman ini.</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Description</label>
                                    <input type="text" class="form-control" name="transaction_tax_description" placeholder="Contoh: PPN keluaran dipungut saat invoice diterbitkan">
                                    <small class="text-muted">Catatan basis pemungutan dan pengakuan liability.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                <h6 class="mb-0">Corporate Tax (PPh Badan)</h6>
                                <span class="badge bg-warning-subtle text-warning">Paid by platform (Tax Expense)</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Tax Name</label>
                                    <div class="form-control-plaintext fw-semibold px-2">PPh Badan</div>
                                    <input type="hidden" name="corporate_tax_name" value="PPh Badan">
                                    <small class="text-muted">Pajak langsung atas laba perusahaan.</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Tax Rate (%)</label>
                                    <input type="number" class="form-control" name="subscription_tax_rate" min="0" max="100" step="0.01" placeholder="Misal: 22.00" required>
                                    <small class="text-muted">Tarif diterapkan ke basis Net Profit.</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Tax Base</label>
                                    <input type="text" class="form-control" value="Net Profit" readonly>
                                    <small class="text-muted">Basis tetap untuk corporate tax.</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="active">Aktif</option>
                                        <option value="draft">Draft</option>
                                        <option value="inactive">Nonaktif</option>
                                    </select>
                                    <small class="text-muted">Kontrol penerapan kebijakan.</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Billing Cycle Type</label>
                                    <select class="form-select" name="billing_cycle_type">
                                        <option value="monthly">Bulanan</option>
                                        <option value="yearly">Tahunan</option>
                                        <option value="custom">Custom Contract</option>
                                    </select>
                                    <small class="text-muted">Menentukan pola renewal kewajiban tenant pada rekap compliance.</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Effective Date</label>
                                    <input type="date" class="form-control" name="effective_from">
                                    <small class="text-muted">Tanggal mulai berlaku regulasi.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Notes (Regulation Changes)</label>
                                    <input type="text" class="form-control" name="notes" placeholder="Misal: Perubahan tarif PPh Badan PMK Q3 2026">
                                    <small class="text-muted">Cantumkan perubahan regulasi untuk kebutuhan audit.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                <h6 class="mb-0">Tax Installments Tracking (PPh 25)</h6>
                                <span class="badge bg-secondary-subtle text-secondary">Payment activity only (not tax rule)</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Period</th>
                                            <th>Amount Paid</th>
                                            <th>Status</th>
                                            <th>Payment Date</th>
                                        </tr>
                                    </thead>
                                    <tbody data-tax-compliance-installment-table>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">Belum ada histori pembayaran installment pada periode ini.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="small text-muted mt-2">PPh 25 adalah tracking pembayaran berkala, bukan parameter tarif pajak.</div>
                        </div>
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary" data-tax-platform-policy-submit>
                            <i class="ti ti-device-floppy me-1"></i>Simpan Konfigurasi Tax Layers
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">History Setup Aturan Tax</h5>
                <span class="badge bg-success-subtle text-success" data-tax-platform-active-rule>Aturan aktif saat ini: belum tersedia</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Versi</th>
                                <th>Corporate Tax Rate (%)</th>
                                <th>Transaction Tax (PPN) (%)</th>
                                <th>Status Rule</th>
                                <th>Dibuat</th>
                                <th>Effective Date</th>
                            </tr>
                        </thead>
                        <tbody data-tax-platform-policy-table>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Memuat history setup aturan tax...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted fs-13 mb-1">Gross Revenue</div>
                        <h4 class="mb-1" data-tax-compliance-summary-gross-revenue>Rp 0</h4>
                        <div class="small text-muted">Revenue kotor sebelum tax liability.</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted fs-13 mb-1">Collected Tax (PPN) - Tax Liability</div>
                        <h4 class="mb-1" data-tax-compliance-summary-tax-liability>Rp 0</h4>
                        <div class="small text-muted">Dipungut dari tenant, bukan revenue platform.</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted fs-13 mb-1">Net Revenue (Excluding Tax)</div>
                        <h4 class="mb-1" data-tax-compliance-summary-net-revenue>Rp 0</h4>
                        <div class="small text-muted">Gross revenue dikurangi collected tax.</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted fs-13 mb-1">Corporate Tax Expense (PPh Badan)</div>
                        <h4 class="mb-1" data-tax-compliance-summary-corporate-tax-expense>Rp 0</h4>
                        <div class="small text-muted">Expense pajak langsung berbasis Net Profit.</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-6">
                <div class="card h-100 border-success">
                    <div class="card-body">
                        <div class="text-muted fs-13 mb-1">Net Profit</div>
                        <h4 class="mb-1 text-success" data-tax-compliance-summary-net-profit>Rp 0</h4>
                        <div class="small text-muted">Net revenue setelah dikurangi corporate tax expense.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">Rekap Kewajiban Pajak Platform</h5>
                    <small class="text-muted">Tipe kewajiban dan renewal tenant ditampilkan langsung agar bulanan vs tahunan tidak tertukar.</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <input type="month" class="form-control" data-tax-platform-report-month>
                    <button type="button" class="btn btn-outline-primary" data-tax-platform-report-refresh>
                        <i class="ti ti-refresh me-1"></i>Muat
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 tax-platform-report-table">
                        <thead class="thead-light">
                            <tr>
                                <th>Tenant</th>
                                <th>Tipe Kewajiban &amp; Renewal</th>
                                <th>Gross Revenue (Rp)</th>
                                <th>Tax Liability - PPN (Rp)</th>
                                <th>Corporate Tax Expense (Rp)</th>
                                <th>Net Profit (Rp)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody data-tax-platform-report-table>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Memuat rekap layer pajak per tenant...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
