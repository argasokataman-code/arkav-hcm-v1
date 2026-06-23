<div class="row justify-content-center">
    <div class="col-12 col-xxl-9">
        <div class="card co-active-card">
            <div class="card-body p-4 p-lg-5">
                <div class="co-active-hero">
                    <div>
                        <div class="co-active-kicker">Subscription Overview</div>
                        <h5 class="co-active-title">Paket Kamu Sedang Aktif</h5>
                        <p class="co-active-lead">Halaman ini hanya menampilkan detail paket aktif. Untuk upgrade, downgrade, atau perubahan plan, gunakan halaman Upgrade Plan.</p>
                    </div>
                    <span class="badge bg-success-subtle text-success px-3 py-2"><span class="co-pulse-dot me-1"></span> Status Aktif</span>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="co-active-meta-item">
                            <div class="co-meta-label">Perusahaan</div>
                            <div class="co-meta-value">{{ $checkoutCompany?->name ?: '—' }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="co-active-meta-item">
                            <div class="co-meta-label">Kode Perusahaan</div>
                            <div class="co-meta-value">{{ $checkoutCompany?->code ?: '—' }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="co-active-meta-item">
                            <div class="co-meta-label">Auto Renew</div>
                            <div class="co-meta-value">{{ $activeAutoRenewLabel }}</div>
                        </div>
                    </div>
                </div>

                <div class="co-active-plan-shell p-4">
                    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                        <div>
                            <div class="small text-muted mb-1">Paket Aktif</div>
                            <div class="co-active-plan-name">{{ $activePackage?->name ?: '—' }}</div>
                            <div class="text-muted small mt-1">{{ strtoupper((string) ($activePackage?->code ?: '-')) }}</div>
                        </div>
                        <div class="text-lg-end">
                            <div class="co-active-plan-price">Rp {{ number_format($activePackageAmount, 0, ',', '.') }}</div>
                            <div class="small text-muted">{{ $activePackageCycleLabel }}</div>
                        </div>
                    </div>

                    <div class="row g-3 co-active-divider">
                        <div class="col-12 col-md-6">
                            <div class="small text-muted">Mulai aktif</div>
                            <div class="fw-semibold">{{ $activeStartDate }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="small text-muted">Akhir masa aktif</div>
                            <div class="fw-semibold">{{ $activeEndDate }}</div>
                        </div>
                    </div>

                    <div class="co-active-divider">
                        <div class="small text-muted mb-2">Add-on Aktif</div>
                        @if ($activePaidAddons->isNotEmpty())
                            <div class="co-active-addon-grid">
                                @foreach ($activePaidAddons as $addonTransaction)
                                    @php
                                        $addon = $addonTransaction->packageAddon;
                                        $addonName = $addon?->name ?: 'Add-on';
                                        $addonCode = strtoupper((string) ($addon?->code ?: '-'));
                                        $addonUnit = (string) ($addon?->unit_name ?: 'tenant / month');
                                        $addonPrice = (float) ($addon?->price_per_unit ?? $addonTransaction->total_amount ?? 0);
                                    @endphp
                                    <div class="co-active-addon-item">
                                        <div class="d-flex align-items-start justify-content-between gap-2">
                                            <div>
                                                <div class="co-active-addon-name">{{ $addonName }}</div>
                                                <div class="co-active-addon-meta">{{ $addonCode }} · {{ $addonUnit }}</div>
                                            </div>
                                            <span class="badge bg-primary-subtle text-primary">aktif</span>
                                        </div>
                                        <div class="co-active-addon-price mt-1">Rp {{ number_format($addonPrice, 0, ',', '.') }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-muted small">Belum ada add-on aktif pada langganan ini.</div>
                        @endif
                    </div>
                </div>

                @if (isset($availablePackages) && $availablePackages->isNotEmpty())
                    <div class="co-active-divider mt-4 pt-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <div class="co-section-title">Tingkatkan Paket</div>
                                <div class="co-section-lead">Bandingkan paket lain dan upgrade kapan saja.</div>
                            </div>
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
                                            @if ($pkgFeatures->isNotEmpty())
                                                <ul class="co-pkg-features">
                                                    @foreach ($pkgFeatures as $feature)
                                                        @php $icon = $featureIcons[$feature->feature_code] ?? 'ti-check'; @endphp
                                                        <li><i class="ti {{ $icon }}"></i> {{ $feature->feature_name ?: $feature->feature_code }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-3">
                            <a href="{{ route('upgrade') }}" class="btn btn-outline-primary btn-sm">
                                <i class="ti ti-arrow-up-right-circle me-1"></i> Lihat Semua Paket & Upgrade
                            </a>
                        </div>
                    </div>
                @endif

                <div class="d-flex align-items-center justify-content-end gap-2 flex-wrap mt-4">
                    <a href="{{ url('/company/invoices') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-file-invoice me-1"></i> Lihat Invoice
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
