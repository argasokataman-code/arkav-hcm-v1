<?php $page = 'subscription-checkout'; ?>
@extends('layout.mainlayout')
@section('content')

@php
    $checkoutCompany = request()->attributes->get('activeCompany');
    $latestCheckoutSubscription = $checkoutCompany instanceof \App\Models\Company
        ? $checkoutCompany->latestSubscription()->first()
        : null;
    $isPendingPaymentLock = ($latestCheckoutSubscription?->status ?? null) === 'pending_payment';
    $isTrialContext = ($latestCheckoutSubscription?->status ?? null) === 'trial';

    $checkoutHeading = $isPendingPaymentLock
        ? 'Aktifkan langganan'
        : ($isTrialContext ? 'Mulai langganan' : 'Upgrade paket');
    $checkoutSubheading = $isPendingPaymentLock
        ? 'Invoice pendaftaran kamu sudah dibuat — selesaikan pembayaran untuk membuka akses aplikasi.'
        : ($isTrialContext
            ? 'Lanjutkan ke paket berbayar sebelum masa trial berakhir.'
            : 'Pilih paket baru dan buat invoice untuk menambah/meng-upgrade langganan.');
    $checkoutCtaLabel = $isPendingPaymentLock ? 'Buat invoice baru' : 'Buat invoice & lanjut bayar';
    $packageHintLabel = $isPendingPaymentLock
        ? 'Paket trial tidak tersedia di sini karena registrasi kamu memilih langganan berbayar.'
        : 'Paket trial tidak ditampilkan. Halaman ini khusus untuk paket berbayar.';
    $pendingLockSupportText = 'Setelah pembayaran berhasil, akses aplikasi langsung terbuka.';
@endphp

<style>
    [data-subscription-checkout-page] {
        --checkout-ink: #1f2937;
        --checkout-muted: #6c757d;
        --checkout-soft: #f8fafc;
    }
    [data-subscription-checkout-page] .checkout-section-title {
        font-size: 1.15rem;
        font-weight: 600;
        color: var(--checkout-ink);
        margin-bottom: 0.25rem;
    }
    [data-subscription-checkout-page] .checkout-section-lead {
        font-size: 0.95rem;
        color: var(--checkout-muted);
    }
    [data-subscription-checkout-page] .checkout-form .form-label {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--checkout-ink);
    }
    [data-subscription-checkout-page] .checkout-form .form-control,
    [data-subscription-checkout-page] .checkout-form .form-select {
        font-size: 0.95rem;
    }
    [data-subscription-checkout-page] .checkout-meta-list {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem 1.25rem;
    }
    @media (max-width: 575.98px) {
        [data-subscription-checkout-page] .checkout-meta-list {
            grid-template-columns: 1fr;
        }
    }
    [data-subscription-checkout-page] .checkout-meta-list .label {
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--checkout-muted);
        margin-bottom: 2px;
    }
    [data-subscription-checkout-page] .checkout-meta-list .value {
        font-size: 0.98rem;
        font-weight: 600;
        color: var(--checkout-ink);
        word-break: break-word;
    }
    [data-subscription-checkout-page] .checkout-meta-list .value-row {
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    [data-subscription-checkout-page] .checkout-invoice-amount {
        font-size: 1.35rem;
        font-weight: 700;
        color: var(--checkout-ink);
    }
    [data-subscription-checkout-page] .checkout-invoice-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--checkout-ink);
    }
    [data-subscription-checkout-page] .checkout-invoice-due {
        font-size: 0.88rem;
        color: var(--checkout-muted);
    }
    [data-subscription-checkout-page] .checkout-notes {
        font-size: 0.92rem;
        color: var(--checkout-muted);
    }
    [data-subscription-checkout-page] .checkout-notes li + li {
        margin-top: 0.35rem;
    }
    [data-subscription-checkout-page] .checkout-focus-shell {
        max-width: 960px;
        margin: 0 auto;
    }
    [data-subscription-checkout-page] .checkout-focus-card {
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 14px 40px rgba(15, 23, 42, 0.08);
    }
    [data-subscription-checkout-page] .checkout-focus-hero {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }
    [data-subscription-checkout-page] .checkout-focus-kicker {
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #ea580c;
        margin-bottom: 0.35rem;
    }
    [data-subscription-checkout-page] .checkout-focus-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--checkout-ink);
        margin-bottom: 0.4rem;
    }
    [data-subscription-checkout-page] .checkout-focus-lead {
        font-size: 0.98rem;
        color: var(--checkout-muted);
        margin-bottom: 0;
        max-width: 56ch;
    }
    [data-subscription-checkout-page] .checkout-company-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.55rem 0.8rem;
        border-radius: 999px;
        background: var(--checkout-soft);
        border: 1px solid rgba(15, 23, 42, 0.08);
        font-weight: 600;
        color: var(--checkout-ink);
        white-space: nowrap;
    }
    [data-subscription-checkout-page] .checkout-focus-meta {
        padding: 1rem;
        border-radius: 1rem;
        background: var(--checkout-soft);
        margin-bottom: 1rem;
    }
    [data-subscription-checkout-page] .checkout-focus-actions {
        margin-top: 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    [data-subscription-checkout-page] .checkout-focus-footnote {
        font-size: 0.9rem;
        color: var(--checkout-muted);
    }
    @media (max-width: 767.98px) {
        [data-subscription-checkout-page] .checkout-focus-hero {
            flex-direction: column;
        }
        [data-subscription-checkout-page] .checkout-focus-title {
            font-size: 1.3rem;
        }
    }
</style>

<div class="page-wrapper">
    <div
        class="content"
        data-subscription-checkout-page
        data-checkout-mock-pay-enabled="{{ app()->isLocal() || config('app.mock_payments_enabled') ? '1' : '0' }}"
        data-checkout-pending-lock="{{ $isPendingPaymentLock ? '1' : '0' }}"
    >
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h4 class="mb-1">Subscription</h4>
                <nav>
                    <ol class="breadcrumb mb-0">
                        @if (! $isPendingPaymentLock)
                            <li class="breadcrumb-item">
                                <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                            </li>
                        @endif
                        <li class="breadcrumb-item">Billing</li>
                        <li class="breadcrumb-item active" aria-current="page">Checkout</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @if ($isPendingPaymentLock)
                    <a href="{{ url('/landing') }}" class="btn btn-light">
                        <i class="ti ti-arrow-left me-1"></i> Kembali ke landing
                    </a>
                    <button type="button" class="btn btn-outline-danger" data-auth-logout>
                        <i class="ti ti-logout me-1"></i> Logout
                    </button>
                @else
                    <a href="{{ url('/index') }}" class="btn btn-light">
                        <i class="ti ti-arrow-left me-1"></i> Kembali ke dashboard
                    </a>
                    <a href="{{ url('/company/invoices') }}" class="btn btn-outline-primary">
                        <i class="ti ti-file-invoice me-1"></i> Semua invoice
                    </a>
                @endif
            </div>
        </div>

        @if ($isPendingPaymentLock)
            <div class="checkout-focus-shell">
                <div class="card checkout-focus-card">
                    <div class="card-body p-4 p-lg-5">
                        <div class="checkout-focus-hero">
                            <div>
                                <div class="checkout-focus-kicker">Payment Required</div>
                                <h1 class="checkout-focus-title mb-2">Selesaikan pembayaran</h1>
                                <p class="checkout-focus-lead">Akses aplikasi dikunci sampai invoice dibayar. {{ $pendingLockSupportText }}</p>
                            </div>
                            <div class="checkout-company-pill">
                                <span class="badge bg-info-subtle text-info" data-checkout-company-badge>—</span>
                                <span class="badge bg-warning-subtle text-warning d-none" data-checkout-trial-badge>Trial</span>
                            </div>
                        </div>

                        <div class="checkout-focus-meta checkout-meta-list">
                            <div>
                                <div class="label">Company</div>
                                <div class="value" data-checkout-company-name>—</div>
                            </div>
                            <div>
                                <div class="label">Company ID</div>
                                <div class="value" data-checkout-company-id>—</div>
                            </div>
                            <div>
                                <div class="label">Company Code</div>
                                <div class="value-row">
                                    <span class="value" data-checkout-company-code>—</span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-checkout-copy-code title="Copy company code" aria-label="Copy company code">
                                        <i class="ti ti-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="alert d-none mb-3" role="alert" data-checkout-feedback></div>

                        <div class="border rounded-3 p-3 p-lg-4 d-none" role="status" data-checkout-invoice-box>
                            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                                <div>
                                    <div class="checkout-invoice-title" data-checkout-invoice-title>Invoice siap</div>
                                    <div class="small text-muted mt-1" data-checkout-invoice-subtitle>—</div>
                                </div>
                                <div class="text-lg-end">
                                    <div class="checkout-invoice-amount" data-checkout-invoice-amount>—</div>
                                    <div class="checkout-invoice-due" data-checkout-invoice-due>—</div>
                                </div>
                            </div>
                            <div class="small text-muted mt-2 d-none" data-checkout-invoice-breakdown></div>
                            <div class="checkout-focus-actions">
                                <div class="checkout-focus-footnote">Begitu pembayaran berhasil, kamu akan langsung bisa masuk ke dashboard.</div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <button type="button" class="btn btn-primary d-none" data-checkout-pay-now>
                                        <i class="ti ti-credit-card me-1"></i> Bayar sekarang
                                    </button>
                                    <a class="btn btn-success d-none" href="{{ url('/index') }}" data-checkout-go-dashboard>
                                        <i class="ti ti-layout-dashboard me-1"></i> Masuk dashboard
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="text-muted" data-checkout-invoice-hint>
                            Kami sedang memuat invoice pendaftaran kamu.
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="row g-3">
                <div class="col-lg-5 order-lg-2">
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-2 mb-2 flex-wrap">
                                <div class="checkout-section-title mb-0">Invoice pembayaran</div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-info-subtle text-info" data-checkout-company-badge>—</span>
                                    <span class="badge bg-warning-subtle text-warning d-none" data-checkout-trial-badge>Trial</span>
                                </div>
                            </div>
                            <div class="checkout-section-lead mb-3">Setelah membuat invoice, detail pembayarannya muncul di sini.</div>

                            <div class="border rounded p-3 d-none" role="status" data-checkout-invoice-box>
                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <div>
                                        <div class="checkout-invoice-title" data-checkout-invoice-title>Invoice siap</div>
                                        <div class="small text-muted mt-1" data-checkout-invoice-subtitle>—</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="checkout-invoice-amount" data-checkout-invoice-amount>—</div>
                                        <div class="checkout-invoice-due" data-checkout-invoice-due>—</div>
                                    </div>
                                </div>
                                <div class="small text-muted mt-2 d-none" data-checkout-invoice-breakdown></div>
                                <div class="d-flex align-items-center justify-content-end gap-2 mt-3 flex-wrap">
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ url('/company/invoices') }}" data-checkout-open-invoices>Buka invoice</a>
                                    <button type="button" class="btn btn-sm btn-primary d-none" data-checkout-pay-now>
                                        <i class="ti ti-credit-card me-1"></i> Bayar sekarang
                                    </button>
                                    <a class="btn btn-sm btn-success d-none" href="{{ url('/index') }}" data-checkout-go-dashboard>
                                        <i class="ti ti-layout-dashboard me-1"></i> Masuk dashboard
                                    </a>
                                </div>
                            </div>

                            <div class="text-muted small" data-checkout-invoice-hint>
                                Belum ada invoice. Pilih paket lalu klik <strong>{{ $checkoutCtaLabel }}</strong>.
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="checkout-section-title mb-2" style="font-size:1rem;">Catatan</div>
                            <ul class="checkout-notes mb-0 ps-3">
                                <li>Invoice dibuat untuk company yang sedang aktif (tenant context).</li>
                                <li>Jika sudah ada invoice pending yang belum dibayar, form di-lock — selesaikan pembayaran terlebih dulu.</li>
                                <li>Setelah pembayaran berhasil, kamu bisa kembali ke halaman ini untuk upgrade atau perpanjang paket.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 order-lg-1">
                    <div class="card">
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="checkout-section-title">{{ $checkoutHeading }}</div>
                                <div class="checkout-section-lead">{{ $checkoutSubheading }}</div>
                            </div>

                            <div class="checkout-meta-list mb-3">
                                <div>
                                    <div class="label">Company</div>
                                    <div class="value" data-checkout-company-name>—</div>
                                </div>
                                <div>
                                    <div class="label">Company ID</div>
                                    <div class="value" data-checkout-company-id>—</div>
                                </div>
                                <div>
                                    <div class="label">Company Code</div>
                                    <div class="value-row">
                                        <span class="value" data-checkout-company-code>—</span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-checkout-copy-code title="Copy company code" aria-label="Copy company code">
                                            <i class="ti ti-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="alert d-none" role="alert" data-checkout-feedback></div>

                            <form action="javascript:void(0);" data-checkout-form class="checkout-form checkout-upgrade-form">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Billing cycle</label>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="form-check form-check-md mb-0">
                                                <input class="form-check-input" type="radio" name="billing_cycle" value="monthly" id="billing_cycle_monthly" checked>
                                                <label class="form-check-label mt-0" for="billing_cycle_monthly">Monthly</label>
                                            </div>
                                            <div class="form-check form-check-md mb-0">
                                                <input class="form-check-input" type="radio" name="billing_cycle" value="yearly" id="billing_cycle_yearly">
                                                <label class="form-check-label mt-0" for="billing_cycle_yearly">Yearly</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label" for="checkout_billing_email">Billing email <span class="text-muted fw-normal">(opsional)</span></label>
                                        <input type="email" class="form-control" id="checkout_billing_email" placeholder="email untuk invoice" data-checkout-billing-email>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label" for="checkout_package_select">Pilih paket <span class="text-danger">*</span></label>
                                        <select class="form-select" id="checkout_package_select" required data-checkout-package-select>
                                            <option value="">Memuat paket…</option>
                                        </select>
                                        <div class="form-text">{{ $packageHintLabel }}</div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center justify-content-end gap-2 mt-4 flex-wrap">
                                    <a href="{{ url('/landing#pricing') }}" class="btn btn-outline-secondary">Lihat paket</a>
                                    <button type="submit" class="btn btn-primary" data-checkout-submit>
                                        <i class="ti ti-receipt me-1"></i> <span data-checkout-submit-label>{{ $checkoutCtaLabel }}</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<script src="{{ asset('build/js/subscription-checkout.js') }}?v={{ file_exists(public_path('build/js/subscription-checkout.js')) ? filemtime(public_path('build/js/subscription-checkout.js')) : time() }}"></script>

@endsection
