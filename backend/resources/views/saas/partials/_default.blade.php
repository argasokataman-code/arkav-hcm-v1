<div class="row g-3">
    <div class="col-lg-5 order-lg-2">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-2 flex-wrap">
                    <div class="co-section-title mb-0">Ringkasan Tagihan</div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-info-subtle text-info" data-checkout-company-badge>—</span>
                        <span class="badge bg-warning-subtle text-warning d-none" data-checkout-trial-badge>Trial</span>
                    </div>
                </div>
                <div class="co-section-lead mb-3">
                    {{ $isInactiveContext
                        ? 'Panel ini menampilkan tagihan yang perlu dibayar untuk mengaktifkan kembali langganan Anda.'
                        : 'Setelah membuat invoice, detail pembayaran akan muncul di sini.' }}
                </div>

                <div class="co-card-sm p-3 d-none" role="status" data-checkout-invoice-box>
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <div class="co-invoice-title" data-checkout-invoice-title>Tagihan Siap</div>
                            <div class="small text-muted mt-1" data-checkout-invoice-subtitle>—</div>
                        </div>
                        <div class="text-end">
                            <div class="co-invoice-amount" data-checkout-invoice-amount>—</div>
                            <div class="co-invoice-due" data-checkout-invoice-due>—</div>
                        </div>
                    </div>
                    <div class="co-invoice-statebar d-none" data-checkout-invoice-statebar>
                        <div class="co-invoice-statecopy">
                            <div class="title">Status tagihan</div>
                            <p class="note" data-checkout-invoice-state-note>—</p>
                        </div>
                        <span class="badge bg-light text-secondary border" data-checkout-invoice-state-badge>—</span>
                    </div>
                    <div class="small text-muted mt-2 d-none" data-checkout-invoice-breakdown></div>
                    <div class="d-flex align-items-center justify-content-end gap-2 mt-3 flex-wrap">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ url('/company/invoices') }}" data-checkout-open-invoices>Lihat Riwayat Tagihan</a>
                        <button type="button" class="btn btn-sm btn-primary d-none" data-checkout-pay-now>
                            <i class="ti ti-credit-card me-1"></i> Bayar sekarang
                        </button>
                        <a class="btn btn-sm btn-success d-none" href="{{ url('/index') }}" data-checkout-go-dashboard>
                            <i class="ti ti-layout-dashboard me-1"></i> Masuk dashboard
                        </a>
                    </div>
                </div>

                <div class="text-muted small" data-checkout-invoice-hint>
                    {{ $isInactiveContext
                        ? 'Belum ada tagihan aktif. Pilih paket lalu klik ' : 'Belum ada tagihan. Pilih paket lalu klik ' }}<strong>{{ $checkoutCtaLabel }}</strong>.
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="co-section-title mb-2">Catatan</div>
                <ul class="co-notes mb-0 ps-3">
                    @if ($isInactiveContext)
                        <li>Jika masih ada tagihan yang belum dibayar, selesaikan pembayaran tersebut terlebih dahulu.</li>
                        <li>Gunakan tombol <strong>Semua tagihan</strong> di atas untuk melihat histori pembayaran.</li>
                        <li>Setelah pembayaran berhasil, akses aplikasi akan aktif kembali dan Anda dapat melanjutkan ke perubahan paket lainnya.</li>
                    @else
                        <li>Tagihan dibuat untuk perusahaan yang sedang aktif.</li>
                        <li>Jika sudah ada tagihan yang belum dibayar, selesaikan pembayaran terlebih dahulu sebelum membuat tagihan baru.</li>
                        <li>Setelah pembayaran berhasil, Anda dapat kembali ke halaman ini untuk upgrade atau perpanjangan paket.</li>
                    @endif
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-7 order-lg-1">
        <div class="card">
            <div class="card-body">
                <div class="mb-3">
                    <div class="co-gradient-bar"></div>
                    <div class="co-section-title mt-3">{{ $checkoutHeading }}</div>
                    <div class="co-section-lead">{{ $checkoutSubheading }}</div>
                </div>

                <div class="co-meta-grid mb-3">
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

                <div class="alert d-none" role="alert" data-checkout-feedback></div>

                {{-- Hidden select for JS card sync --}}
                <div class="d-none" id="checkout_package_wrapper" data-checkout-package-wrapper>
                <select class="form-select" id="checkout_package_select" data-checkout-package-select>
                    <option value="">Pilih paket…</option>
                    @if (isset($availablePackages))
                        @foreach ($availablePackages as $pkg)
                            @php $pkgId = $pkg->uuid ?? $pkg->id; @endphp
                            <option value="{{ $pkgId }}" data-code="{{ $pkg->code }}">{{ $pkg->name }}</option>
                        @endforeach
                    @endif
                </select>
                </div>

                @if (isset($availablePackages) && $availablePackages->isNotEmpty())
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <div class="co-section-title">Pilih Paket</div>
                                <div class="co-section-lead">Klik kartu paket untuk memilih dan lanjutkan checkout.</div>
                            </div>
                            <a href="{{ url('/landing#pricing') }}" class="btn btn-sm btn-outline-secondary">Lihat Semua Paket</a>
                        </div>
                        <div class="row g-3" id="checkout-package-cards">
                            @foreach ($availablePackages as $pkg)
                                @php
                                    $pkgMonthly = (float) ($pkg->monthly_price ?? 0);
                                    $pkgYearly = (float) ($pkg->yearly_price ?? 0);
                                    $pkgId = $pkg->uuid ?? $pkg->id;
                                    $pkgColor = $pkg->color ?? '#2563eb';
                                    $yearlySavings = ($pkgMonthly > 0 && $pkgYearly > 0) ? round((1 - ($pkgYearly / ($pkgMonthly * 12))) * 100) : 0;
                                    $pkgFeatures = $pkg->relationLoaded('features') ? $pkg->features : collect();
                                    $featureIcons = [
                                        'max_employees' => 'ti-users', 'payroll' => 'ti-coin', 'performance' => 'ti-chart-bar',
                                        'attendance' => 'ti-clock', 'leave_management' => 'ti-calendar-off',
                                        'asset_management' => 'ti-archive', 'training' => 'ti-book', 'tickets' => 'ti-help',
                                    ];
                                @endphp
                                <div class="col-md-4 col-6">
                                    <div class="co-pkg-card" data-package-id="{{ $pkgId }}" data-package-code="{{ $pkg->code }}" role="button" tabindex="0" style="border-top: 3px solid {{ $pkgColor }};">
                                        <div class="co-pkg-card-top">
                                            <div class="co-pkg-name">{{ $pkg->name }}</div>
                                            <div class="co-pkg-code">{{ strtoupper((string) $pkg->code) }}</div>
                                        </div>
                                        <div class="co-pkg-card-body">
                                            <div class="co-pkg-price">
                                                <span class="co-pkg-price-num" data-price-monthly="{{ $pkgMonthly }}" data-price-yearly="{{ $pkgYearly }}">Rp {{ number_format($pkgMonthly, 0, ',', '.') }}</span>
                                                <span class="co-pkg-price-unit">/ bulan</span>
                                                @if ($yearlySavings > 0)
                                                    <span class="co-pkg-yearly-badge" data-yearly-savings="{{ $yearlySavings }}">Hemat {{ $yearlySavings }}%</span>
                                                @endif
                                            </div>
                                            @if ($pkgYearly > 0 && $pkgYearly !== $pkgMonthly)
                                                <div class="co-pkg-yearly-hint" style="font-size:0.7rem;color:var(--co-muted);">Rp {{ number_format($pkgYearly, 0, ',', '.') }}/tahun</div>
                                            @endif
                                            @if ($pkg->description)
                                                <div class="co-pkg-desc">{{ $pkg->description }}</div>
                                            @endif
                                            @if ($pkgFeatures->isNotEmpty())
                                                <ul class="co-pkg-features">
                                                    @foreach ($pkgFeatures as $feature)
                                                        @php $icon = $featureIcons[$feature->feature_code] ?? 'ti-check'; @endphp
                                                        <li><i class="ti {{ $icon }}"></i> {{ $feature->feature_name ?: $feature->feature_code }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                        <div class="co-pkg-card-check">✓</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($hasBlockingPendingInvoice)
                <div class="alert alert-warning d-flex align-items-start gap-2 py-3 mb-0" role="alert">
                    <i class="ti ti-alert-triangle mt-1"></i>
                    <div>
                        <div class="fw-semibold mb-1">Tagihan belum dibayar ditemukan</div>
                        <p class="small mb-0">Membuat invoice baru akan <strong>membatalkan tagihan sebelumnya</strong>. Invoice lama akan otomatis dicancel saat invoice baru berhasil dibuat. <a href="{{ url('/company/invoices') }}" class="alert-link">Lihat tagihan</a></p>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Checkout modal (billing cycle + email readonly + confirm) --}}
<div class="modal fade" id="checkout-checkout-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius:1.25rem;">
            <div class="modal-body p-4 text-center">
                <button type="button" class="btn-close position-absolute top-0 end-0 mt-3 me-3" data-bs-dismiss="modal" aria-label="Close"></button>

                {{-- Package name big --}}
                <div class="mb-1 text-muted small text-uppercase" style="letter-spacing:0.08em;font-weight:600;">Paket Dipilih</div>
                <div class="fw-bold mb-3" id="checkout-modal-pkg-name" style="font-size:1.5rem;color:var(--co-ink);">—</div>

                <form action="javascript:void(0);" data-checkout-form class="co-form checkout-upgrade-form">
                {{-- Billing cycle --}}
                <div class="mb-3 text-start">
                    <label class="form-label fw-semibold small">Siklus Tagihan</label>
                    <div class="d-flex gap-2">
                        <div class="form-check form-check-md mb-0 flex-fill">
                            <input class="form-check-input" type="radio" name="billing_cycle" value="monthly" id="modal_billing_monthly" checked>
                            <label class="form-check-label mt-0" for="modal_billing_monthly">Bulanan</label>
                        </div>
                        <div class="form-check form-check-md mb-0 flex-fill">
                            <input class="form-check-input" type="radio" name="billing_cycle" value="yearly" id="modal_billing_yearly">
                            <label class="form-check-label mt-0" for="modal_billing_yearly">Tahunan</label>
                        </div>
                    </div>
                </div>

                {{-- Email readonly --}}
                <div class="mb-0 text-start">
                    <label class="form-label fw-semibold small">Email Tagihan</label>
                    <div class="form-control bg-light" id="modal_billing_email_display" style="cursor:default;color:var(--co-ink);font-weight:600;border:1px solid #e2e8f0;border-radius:0.65rem;padding:0.5rem 0.85rem;">
                        Memuat...
                    </div>
                    <input type="hidden" data-checkout-billing-email id="modal_billing_email_hidden">
                    <div class="form-text small">Email terdaftar akun Anda. Invoice akan dikirim ke email ini.</div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary flex-fill" id="checkout-modal-submit" data-checkout-submit>
                        <i class="ti ti-receipt me-1"></i> Buat Invoice
                    </button>
                </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Success invoice modal --}}
<div class="modal fade" data-checkout-success-state tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center p-4">
            <div class="modal-body p-0">
                <div class="mb-3">
                    <i class="ti ti-circle-check text-success" style="font-size:3rem;"></i>
                </div>
                <h5 class="fw-bold mb-1">Invoice Berhasil Dibuat!</h5>
                <p class="small text-muted mb-3" data-checkout-success-message>Tagihan telah dibuat dan siap untuk pembayaran.</p>

                <div class="bg-light rounded p-3 mb-3 text-start">
                    <div class="small text-muted mb-1">Paket Baru</div>
                    <div class="fw-bold" data-checkout-active-package-name>—</div>
                    <div class="small text-muted" data-checkout-active-package-code>—</div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-muted">Tagihan</span>
                        <span class="fw-bold text-primary" data-checkout-active-package-price>—</span>
                    </div>
                    <div class="small text-muted" data-checkout-active-package-unit>per bulan</div>
                </div>

                <div class="d-flex flex-column gap-2">
                    <a href="{{ url('/company/invoices') }}" class="btn btn-primary w-100">
                        <i class="ti ti-file-invoice me-1"></i> Lihat & Bayar Tagihan
                    </a>
                    <a href="{{ url('/index') }}" class="btn btn-light w-100">
                        <i class="ti ti-layout-dashboard me-1"></i> Buka Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
