<div class="co-focus-shell">
    <div class="card co-focus-card">
        <div class="card-body p-4 p-lg-5">
            <div class="co-gradient-bar"></div>
            <div class="co-focus-hero mt-3">
                <div>
                    <div class="co-focus-kicker">Payment Required</div>
                    <h1 class="co-focus-title mb-2">Selesaikan pembayaran</h1>
                    <p class="co-focus-lead">Akses aplikasi dikunci sampai invoice dibayar. {{ $pendingLockSupportText }}</p>
                </div>
                <div class="co-company-pill">
                    <span class="badge bg-info-subtle text-info" data-checkout-company-badge>—</span>
                    <span class="badge bg-warning-subtle text-warning d-none" data-checkout-trial-badge>Trial</span>
                </div>
            </div>

            <div class="co-focus-meta co-meta-grid">
                <div class="co-meta-item">
                    <div class="co-meta-label">Perusahaan</div>
                    <div class="co-meta-value" data-checkout-company-name>—</div>
                </div>
                <div class="co-meta-item">
                    <div class="co-meta-label">ID Perusahaan</div>
                    <div class="co-meta-value" data-checkout-company-id>—</div>
                </div>
                <div class="co-meta-item">
                    <div class="co-meta-label">Kode Perusahaan</div>
                    <div class="co-meta-row">
                        <span class="co-meta-value" data-checkout-company-code>—</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-checkout-copy-code title="Copy company code" aria-label="Copy company code">
                            <i class="ti ti-copy"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="alert d-none mb-3" role="alert" data-checkout-feedback></div>

            <div class="co-card-sm p-3 p-lg-4 d-none" role="status" data-checkout-invoice-box>
                <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                    <div>
                        <div class="co-invoice-title" data-checkout-invoice-title>Invoice siap</div>
                        <div class="small text-muted mt-1" data-checkout-invoice-subtitle>—</div>
                    </div>
                    <div class="text-lg-end">
                        <div class="co-invoice-amount" data-checkout-invoice-amount>—</div>
                        <div class="co-invoice-due" data-checkout-invoice-due>—</div>
                    </div>
                </div>
                <div class="small text-muted mt-2 d-none" data-checkout-invoice-breakdown></div>
                <div class="co-focus-actions">
                    <div class="co-focus-footnote">Begitu pembayaran berhasil, kamu akan langsung bisa masuk ke dashboard.</div>
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
