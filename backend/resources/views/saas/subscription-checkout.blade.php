<?php $page = 'subscription-checkout'; ?>
@extends('layout.mainlayout')
@section('content')

<div class="page-wrapper">
    <div class="content" data-subscription-checkout-page>
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Subscription</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                        </li>
                        <li class="breadcrumb-item">Billing</li>
                        <li class="breadcrumb-item active" aria-current="page">Checkout</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-3">
                            <div>
                                <h4 class="mb-1">Upgrade paket</h4>
                                <div class="text-muted">Pilih paket dan lanjutkan pembayaran untuk mengaktifkan layanan.</div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-info-subtle text-info" data-checkout-company-badge>—</span>
                                <span class="badge bg-warning-subtle text-warning d-none" data-checkout-trial-badge>Trial</span>
                            </div>
                        </div>

                        <div class="alert d-none" role="alert" data-checkout-feedback></div>

                        <form action="javascript:void(0);" data-checkout-form>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Company</label>
                                    <input type="text" class="form-control" readonly data-checkout-company-name>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Company ID</label>
                                    <input type="text" class="form-control" readonly data-checkout-company-id>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Company Code</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" readonly data-checkout-company-code>
                                        <button type="button" class="btn btn-outline-secondary" data-checkout-copy-code title="Copy">
                                            <i class="ti ti-copy"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Billing cycle</label>
                                    <div class="d-flex align-items-center gap-4">
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

                                <div class="col-12">
                                    <label class="form-label">Pilih paket *</label>
                                    <select class="form-select" required data-checkout-package-select>
                                        <option value="">Loading packages…</option>
                                    </select>
                                    <div class="form-text">Trial package tidak ditampilkan di sini (upgrade hanya untuk paket berbayar).</div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Billing email (optional)</label>
                                    <input type="email" class="form-control" placeholder="email untuk invoice / payment notification" data-checkout-billing-email>
                                </div>
                            </div>

                            <div class="d-flex align-items-center justify-content-end gap-2 mt-4">
                                <a href="{{ url('/landing#pricing') }}" class="btn btn-outline-secondary">Lihat paket</a>
                                <button type="submit" class="btn btn-primary" data-checkout-submit>
                                    <i class="ti ti-receipt me-1"></i> Buat invoice & lanjut bayar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-2">Invoice</h5>
                        <div class="text-muted mb-3">Setelah invoice dibuat, kamu bisa lanjut bayar dari halaman invoice.</div>

                        <div class="alert alert-light border mb-0 d-none" role="status" data-checkout-invoice-box>
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div>
                                    <div class="fw-semibold mb-1" data-checkout-invoice-title>Invoice siap</div>
                                    <div class="small text-muted" data-checkout-invoice-subtitle>—</div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-semibold" data-checkout-invoice-amount>—</div>
                                    <div class="small text-muted" data-checkout-invoice-due>—</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-end gap-2 mt-3">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ url('/company/invoices') }}">Buka invoice</a>
                                <a class="btn btn-sm btn-primary" href="{{ url('/company/invoices') }}">Bayar sekarang</a>
                            </div>
                        </div>

                        <div class="text-muted small" data-checkout-invoice-hint>
                            Belum ada invoice. Pilih paket lalu klik <strong>Buat invoice & lanjut bayar</strong>.
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-2">Catatan</h6>
                        <ul class="text-muted mb-0">
                            <li>Invoice dibuat untuk company yang sedang aktif (tenant context).</li>
                            <li>Jika ada invoice pending sebelumnya, sistem akan pakai invoice yang masih aktif.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('build/js/subscription-checkout.js') }}?v={{ file_exists(public_path('build/js/subscription-checkout.js')) ? filemtime(public_path('build/js/subscription-checkout.js')) : time() }}"></script>

@endsection

