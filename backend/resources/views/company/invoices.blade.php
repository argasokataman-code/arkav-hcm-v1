<?php $page = 'company-invoices'; ?>
@extends('layout.mainlayout')

@section('content')

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content" data-company-invoices-page>

        <!-- Breadcrumb -->
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">My Invoices & Billing</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{url('index')}}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Invoices</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
                <div class="mb-2">
                    <a href="#" class="btn btn-outline-primary d-flex align-items-center">
                        <i class="ti ti-download me-2"></i>Export
                    </a>
                </div>
            </div>
        </div>
        <!-- /Breadcrumb -->

        <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
            <i class="ti ti-info-circle mt-1"></i>
            <div>
                <strong>Disclosure Pemrosesan Pembayaran:</strong>
                Informasi pembayaran Anda diproses oleh penyedia pembayaran pihak ketiga
                (Xendit dan/atau Stripe). Untuk transaksi lintas negara, data tertentu dapat
                diproses pada infrastruktur di luar Indonesia sesuai kebutuhan pelaksanaan kontrak
                layanan (UU PDP Pasal 49 huruf b).
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Total Due</p>
                                <h3 class="mb-0" id="total_due">Rp 0</h3>
                            </div>
                            <i class="ti ti-coin text-primary fs-1 opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Unpaid</p>
                                <h3 class="mb-0" id="count_unpaid">0</h3>
                            </div>
                            <i class="ti ti-alert-hexagon text-warning fs-1 opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Overdue</p>
                                <h3 class="mb-0 text-danger" id="count_overdue">0</h3>
                            </div>
                            <i class="ti ti-alert-circle text-danger fs-1 opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Paid This Month</p>
                                <h3 class="mb-0 text-success" id="paid_this_month">Rp 0</h3>
                            </div>
                            <i class="ti ti-check-circle text-success fs-1 opacity-10"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" class="form-control" placeholder="Search invoice #..." id="search_invoices">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filter_invoice_status">
                            <option value="">All Status</option>
                            <option value="draft">Draft</option>
                            <option value="sent">Sent</option>
                            <option value="viewed">Viewed</option>
                            <option value="paid">Paid</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filter_invoice_paid">
                            <option value="">All Payments</option>
                            <option value="1">Paid</option>
                            <option value="0">Unpaid</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary w-100" id="btn_reset_invoice_filters">
                            <i class="ti ti-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-danger d-none" role="alert" data-company-invoices-feedback></div>

        <!-- Invoices List Container -->
        <div data-company-invoices-list-container>
            <div class="card"><div class="card-body text-center text-muted py-4">
                <i class="ti ti-loader-quarter fs-1 spin"></i> Loading invoices...
            </div></div>
        </div>

        <!-- Info Alert -->
        <div class="alert alert-info mt-3" role="alert">
            <i class="ti ti-info-circle me-2"></i>
            <strong>Need Help?</strong> If you have questions about your invoice, please contact our support team at billing@arcav.com
        </div>

    </div>
</div>
<!-- /Page Wrapper -->

<!-- Invoice Details Modal -->
<div class="modal fade" id="invoiceDetailsModal" tabindex="-1" data-company-invoice-modal>
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <p class="text-uppercase text-muted small fw-semibold mb-1 letter-spacing-1">Billing Preview</p>
                    <h5 class="modal-title mb-1">Invoice Details</h5>
                    <p class="text-muted mb-0">Preview invoice company dengan layout profesional sebelum diunduh atau dicetak.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="company-invoice-preview" data-company-invoice-print-root>
                    <div class="company-invoice-preview__sheet">
                        <div class="company-invoice-preview__hero">
                            <div class="row g-4 align-items-start">
                                <div class="col-lg-7">
                                    <p class="company-invoice-preview__eyebrow mb-2">Invoice Document</p>
                                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                                        <div>
                                            <h3 class="company-invoice-preview__number mb-1" data-invoice-modal-number>INV-2026-001</h3>
                                            <p class="company-invoice-preview__subtitle mb-0">Tagihan resmi untuk billing company dan aktivasi layanan.</p>
                                        </div>
                                        <div class="text-lg-end">
                                            <div class="company-invoice-preview__label mb-2">Status Invoice</div>
                                            <div data-invoice-modal-status-wrap>
                                                <span class="company-invoice-pill company-invoice-pill--info" data-invoice-modal-status>Sent</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-5">
                                    <div class="company-invoice-preview__amount-card">
                                        <div class="company-invoice-preview__label">Amount Due</div>
                                        <div class="company-invoice-preview__amount" data-invoice-modal-amount>Rp 5,000,000</div>
                                        <div class="company-invoice-preview__meta-line">
                                            <span class="text-muted">Payment Status</span>
                                            <span data-invoice-modal-payment-status-wrap>
                                                <span class="company-invoice-pill company-invoice-pill--warning" data-invoice-modal-payment-status>Unpaid</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-lg-5">
                                <div class="company-invoice-preview__panel h-100">
                                    <div class="company-invoice-preview__panel-title">Bill To</div>
                                    <div class="company-invoice-preview__company" data-invoice-modal-company>Company Name</div>
                                    <div class="company-invoice-preview__package mt-2" data-invoice-modal-package-name>Starter</div>
                                    <p class="company-invoice-preview__support mb-1" data-invoice-modal-package-summary>Starter - Bulanan</p>
                                    <p class="company-invoice-preview__support mb-0" data-invoice-modal-summary>Invoice company untuk kebutuhan onboarding atau subscription aktif.</p>
                                </div>
                            </div>
                            <div class="col-lg-7">
                                <div class="company-invoice-preview__panel h-100">
                                    <div class="company-invoice-preview__panel-title">Invoice Meta</div>
                                    <div class="row g-3">
                                        <div class="col-sm-4">
                                            <div class="company-invoice-preview__meta-label">Issue Date</div>
                                            <div class="company-invoice-preview__meta-value" data-invoice-modal-issue-date>2026-04-13</div>
                                        </div>
                                        <div class="col-sm-4">
                                            <div class="company-invoice-preview__meta-label">Due Date</div>
                                            <div class="company-invoice-preview__meta-value" data-invoice-modal-due-date>2026-05-13</div>
                                        </div>
                                        <div class="col-sm-4">
                                            <div class="company-invoice-preview__meta-label">Paid Date</div>
                                            <div class="company-invoice-preview__meta-value" data-invoice-modal-paid-date>-</div>
                                        </div>
                                        <div class="col-sm-4">
                                            <div class="company-invoice-preview__meta-label">Billing Cycle</div>
                                            <div class="company-invoice-preview__meta-value" data-invoice-modal-billing-cycle>-</div>
                                        </div>
                                        <div class="col-sm-4">
                                            <div class="company-invoice-preview__meta-label">Next Payment</div>
                                            <div class="company-invoice-preview__meta-value" data-invoice-modal-next-billing-date>-</div>
                                        </div>
                                        <div class="col-sm-4">
                                            <div class="company-invoice-preview__meta-label">Current Period</div>
                                            <div class="company-invoice-preview__meta-value" data-invoice-modal-current-period>-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="company-invoice-preview__panel mb-3">
                            <div class="company-invoice-preview__panel-title">Charge Summary</div>
                            <div class="table-responsive">
                                <table class="table company-invoice-preview__table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th>Status</th>
                                            <th class="text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold" data-invoice-modal-line-label>Subscription billing</div>
                                                <div class="company-invoice-preview__support" data-invoice-modal-line-caption>Tagihan layanan company sesuai invoice aktif.</div>
                                            </td>
                                            <td data-invoice-modal-charge-status-wrap>
                                                <span class="company-invoice-pill company-invoice-pill--info" data-invoice-modal-charge-status>Sent</span>
                                            </td>
                                            <td class="text-end fw-semibold" data-invoice-modal-table-amount>Rp 5,000,000</td>
                                        </tr>
                                        <tr class="company-invoice-preview__table-total">
                                            <td colspan="2" class="text-end">Total Due</td>
                                            <td class="text-end" data-invoice-modal-table-total>Rp 5,000,000</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-lg-6">
                                <div class="company-invoice-preview__panel h-100">
                                    <div class="company-invoice-preview__panel-title">Payment Guidance</div>
                                    <p class="company-invoice-preview__support mb-0" data-invoice-modal-guidance>Segera selesaikan pembayaran sebelum jatuh tempo agar layanan company tetap aktif tanpa gangguan.</p>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="company-invoice-preview__panel h-100">
                                    <div class="company-invoice-preview__panel-title">Invoice Terms</div>
                                    <p class="company-invoice-preview__support mb-2" data-invoice-modal-terms-summary>Prefix INV- | Due in 30 days | Tax shown | Round-off disabled</p>
                                    <p class="company-invoice-preview__support mb-1" data-invoice-modal-header-terms>-</p>
                                    <p class="company-invoice-preview__support mb-0" data-invoice-modal-footer-terms>-</p>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="company-invoice-preview__panel h-100">
                                    <div class="company-invoice-preview__panel-title">Notes</div>
                                    <p class="company-invoice-preview__notes mb-0" data-invoice-modal-notes>No notes</p>
                                </div>
                            </div>
                        </div>

                        <div class="company-invoice-preview__footer text-muted mt-3">
                            Dokumen billing ini dibuat otomatis dan dapat diunduh sebagai PDF resmi.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-primary" data-company-invoice-download>
                    <i class="ti ti-download me-2"></i>Download PDF
                </button>
                <button type="button" class="btn btn-primary" data-company-invoice-print>
                    <i class="ti ti-printer me-2"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .spin {
        animation: spin 1s linear infinite;
    }
    .letter-spacing-1 {
        letter-spacing: .08em;
    }
    .company-invoice-preview {
        background: linear-gradient(180deg, #f8fafc 0%, #eef4ff 100%);
        border: 1px solid #e6ecf5;
        border-radius: 20px;
        padding: 18px;
    }
    .company-invoice-preview__sheet {
        background: #fff;
        border: 1px solid #e6ecf5;
        border-radius: 18px;
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
        padding: 24px;
    }
    .company-invoice-preview__hero {
        border-bottom: 1px solid #edf2f7;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
    }
    .company-invoice-preview__eyebrow {
        color: #fc7f01;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .company-invoice-preview__number {
        color: #172554;
        font-size: 1.9rem;
        font-weight: 700;
    }
    .company-invoice-preview__subtitle,
    .company-invoice-preview__support,
    .company-invoice-preview__footer {
        color: #64748b;
        font-size: .95rem;
        line-height: 1.55;
    }
    .company-invoice-preview__amount-card {
        background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
        border: 1px solid #fdba74;
        border-radius: 16px;
        padding: 1rem 1.1rem;
    }
    .company-invoice-preview__label,
    .company-invoice-preview__meta-label,
    .company-invoice-preview__panel-title {
        color: #64748b;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
    }
    .company-invoice-preview__amount {
        color: #c2410c;
        font-size: 1.8rem;
        font-weight: 700;
        line-height: 1.2;
        margin: .35rem 0 .6rem;
    }
    .company-invoice-preview__meta-line {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
    }
    .company-invoice-preview__panel {
        background: #fff;
        border: 1px solid #e6ecf5;
        border-radius: 16px;
        padding: 1rem 1.05rem;
    }
    .company-invoice-preview__company,
    .company-invoice-preview__package,
    .company-invoice-preview__meta-value,
    .company-invoice-preview__notes {
        color: #0f172a;
        font-size: 1rem;
        font-weight: 600;
        margin-top: .35rem;
    }
    .company-invoice-preview__table thead th {
        background: #f8fafc;
        border-bottom: 1px solid #e6ecf5;
        color: #64748b;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
    }
    .company-invoice-preview__table td,
    .company-invoice-preview__table th {
        padding: .9rem 1rem;
        vertical-align: top;
    }
    .company-invoice-preview__table-total td {
        background: #f8fafc;
        font-weight: 700;
    }
    .company-invoice-pill {
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        font-size: .74rem;
        font-weight: 700;
        gap: .25rem;
        letter-spacing: .05em;
        padding: .4rem .72rem;
        text-transform: uppercase;
    }
    .company-invoice-pill--success {
        background: #dcfce7;
        color: #166534;
    }
    .company-invoice-pill--warning {
        background: #fef3c7;
        color: #92400e;
    }
    .company-invoice-pill--danger {
        background: #fee2e2;
        color: #b91c1c;
    }
    .company-invoice-pill--info {
        background: #dbeafe;
        color: #1d4ed8;
    }
    .company-invoice-pill--muted {
        background: #e2e8f0;
        color: #475569;
    }
    @media (max-width: 991.98px) {
        .company-invoice-preview__number,
        .company-invoice-preview__amount {
            font-size: 1.45rem;
        }
        .company-invoice-preview__meta-line {
            align-items: flex-start;
            flex-direction: column;
        }
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>
<script src="{{ asset('build/js/company-invoices.js') }}?v={{ file_exists(public_path('build/js/company-invoices.js')) ? filemtime(public_path('build/js/company-invoices.js')) : time() }}"></script>

@endsection
