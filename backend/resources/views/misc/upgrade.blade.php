<?php $page = 'upgrade'; ?>
@extends('layout.mainlayout')
@section('content')

@php
    $authUser = request()->user() ?: auth()->user();
    $primarySuperAdminEmail = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));
    $authUserEmail = strtolower(trim((string) ($authUser->email ?? '')));
    $isPrimarySuperAdminCodeOne = (bool) ($authUser && $authUserEmail === $primarySuperAdminEmail);
    $isGlobalHcmAdmin = (bool) ($authUser?->isGlobalHcmAdmin());

    $blocked = request()->query('blocked');
    $normalizedBlockedFeature = strtolower(trim((string) $blocked));
    $normalizedBlockedFeature = str_replace(['-', ' '], '_', $normalizedBlockedFeature);
    $normalizedBlockedFeature = preg_replace('/[^a-z0-9_]/', '', $normalizedBlockedFeature) ?? '';

    $featureCatalog = collect(config('saas_package_feature_catalog.groups', []))
        ->flatMap(function (array $group): array {
            return $group['features'] ?? [];
        })
        ->keyBy('code');

    $blockedFeatureMeta = $featureCatalog->get($normalizedBlockedFeature, []);
    $blockedFeatureLabel = $blockedFeatureMeta['name']
        ?? ($normalizedBlockedFeature !== ''
            ? \Illuminate\Support\Str::headline(str_replace('_', ' ', $normalizedBlockedFeature))
            : '');

    $activeCompany = request()->attributes->get('activeCompany');
    $currentSubscription = $activeCompany instanceof \App\Models\Company
        ? $activeCompany->activeSubscription()
        : null;
    $currentPackage = $currentSubscription?->package;
    $currentPackageSummary = $currentPackage ? [
        'uuid' => $currentPackage->uuid,
        'name' => $currentPackage->name,
        'code' => $currentPackage->code,
        'monthly_price' => (float) ($currentPackage->monthly_price ?? 0),
        'yearly_price' => (float) ($currentPackage->yearly_price ?? 0),
        'description' => $currentPackage->description,
        'feature_codes' => $currentPackage->features->pluck('feature_code')->values()->all(),
    ] : null;

    $recommendedPackages = collect();
    if ($normalizedBlockedFeature !== '') {
        $recommendedPackages = \App\Models\Package::query()
            ->with('features')
            ->where('status', 'active')
            ->when(! $isGlobalHcmAdmin, function ($query): void {
                $query->where('is_global_admin_only', false);
            })
            ->when($currentPackage, function ($query) use ($currentPackage): void {
                $query->where('uuid', '!=', $currentPackage->uuid);
            })
            ->whereHas('features', function ($featureQuery) use ($normalizedBlockedFeature): void {
                $featureQuery->where('feature_code', $normalizedBlockedFeature);
            })
            ->orderByRaw('COALESCE(monthly_price, 0) asc')
            ->get(['uuid', 'name', 'code', 'description', 'monthly_price', 'yearly_price', 'is_global_admin_only'])
            ->map(function (\App\Models\Package $package): array {
                return [
                    'uuid' => $package->uuid,
                    'name' => $package->name,
                    'code' => $package->code,
                    'description' => $package->description,
                    'monthly_price' => (float) ($package->monthly_price ?? 0),
                    'yearly_price' => (float) ($package->yearly_price ?? 0),
                    'feature_codes' => $package->features->pluck('feature_code')->values()->all(),
                ];
            })
            ->values();
    }

    $checkedOutAddonIds = collect();

    $fallbackPackages = \App\Models\Package::query()
        ->with('features')
        ->where('status', 'active')
        ->when(! $isGlobalHcmAdmin, function ($query): void {
            $query->where('is_global_admin_only', false);
        })
        ->when($currentPackage, function ($query) use ($currentPackage): void {
            $query->where('uuid', '!=', $currentPackage->uuid);
        })
        ->orderByRaw('COALESCE(monthly_price, 0) asc')
        ->get(['uuid', 'name', 'code', 'description', 'monthly_price', 'yearly_price', 'is_global_admin_only'])
        ->map(function (\App\Models\Package $package): array {
            return [
                'uuid' => $package->uuid,
                'name' => $package->name,
                'code' => $package->code,
                'description' => $package->description,
                'monthly_price' => (float) ($package->monthly_price ?? 0),
                'yearly_price' => (float) ($package->yearly_price ?? 0),
                'feature_codes' => $package->features->pluck('feature_code')->values()->all(),
            ];
        })
        ->values();

    $recommendationMode = 'empty';
    $recommendationPackages = collect();
    $hasFeatureContext = $normalizedBlockedFeature !== '';

    if ($recommendedPackages->isNotEmpty()) {
        $recommendationMode = 'match';
        $recommendationPackages = $recommendedPackages->take(3)->values();
    } elseif ($fallbackPackages->isNotEmpty()) {
        $recommendationMode = 'fallback';
        $recommendationPackages = $fallbackPackages->take(3)->values();
    }

    if ($hasFeatureContext) {
        $recommendationKicker = 'Rekomendasi Fitur';
        $recommendationTitle = 'Paket untuk Membuka Fitur '.($blockedFeatureLabel ?: 'yang diminta');
        $recommendationHeadline = $recommendationMode === 'fallback'
            ? 'Belum ada paket yang langsung memuat fitur ini, tapi kamu bisa pilih paket alternatif berikut.'
            : 'Prioritas paket yang memuat fitur '.($blockedFeatureLabel ?: 'yang diminta').'.';
        $recommendationBadge = $recommendationMode === 'fallback'
            ? 'Alternatif Paket'
            : 'Rekomendasi Fitur';
    } else {
        $recommendationKicker = 'Rekomendasi Upgrade';
        $recommendationTitle = 'Pilihan Paket untuk Upgrade';
        $recommendationHeadline = $recommendationMode === 'fallback'
            ? 'Belum ada paket yang bisa direkomendasikan dari konteks saat ini, tapi kamu tetap bisa pilih opsi upgrade berikut.'
            : 'Bandingkan paket aktif yang tersedia untuk menentukan upgrade terbaik sesuai kebutuhan tim kamu.';
        $recommendationBadge = $recommendationMode === 'fallback'
            ? 'Alternatif Upgrade'
            : 'Rekomendasi Upgrade';
    }
    if ($activeCompany instanceof \App\Models\Company) {
        $checkedOutAddonIds = \App\Models\PurchaseTransaction::query()
            ->where('company_id', $activeCompany->id)
            ->where('transaction_type', 'addon')
            ->whereIn('status', ['draft', 'issued', 'sent', 'paid', 'overdue'])
            ->whereNotNull('package_addon_id')
            ->pluck('package_addon_id')
            ->map(fn ($addonId) => (int) $addonId)
            ->unique()
            ->values();
    }

    $activeAddons = \App\Models\PackageAddon::query()
        ->active()
        ->when($checkedOutAddonIds->isNotEmpty(), function ($query) use ($checkedOutAddonIds): void {
            $query->whereNotIn('id', $checkedOutAddonIds->all());
        })
        ->orderByRaw('COALESCE(price_per_unit, 0) asc')
        ->limit(8)
        ->get(['id', 'uuid', 'code', 'name', 'description', 'price_per_unit', 'unit_name'])
        ->values();
@endphp

<style>
    .upgrade-shell {
        display: grid;
        gap: 1rem;
    }

    .upgrade-hero-card,
    .upgrade-package-card,
    .upgrade-preview-card,
    .upgrade-stage-card {
        border-radius: 1rem;
    }

    .upgrade-hero-card {
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 14px 36px rgba(15, 23, 42, 0.08);
    }

    .upgrade-stage-card {
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 14px 36px rgba(15, 23, 42, 0.06);
    }

    .upgrade-kicker {
        font-size: .74rem;
        letter-spacing: .08em;
        text-transform: uppercase;
        font-weight: 700;
        color: #0d6efd;
    }

    .upgrade-headline {
        font-size: clamp(1.1rem, 1rem + .5vw, 1.4rem);
        font-weight: 700;
        line-height: 1.25;
        color: #0f172a;
        margin-bottom: .25rem;
    }

    .upgrade-subline {
        color: #64748b;
        font-size: .9rem;
        margin-bottom: 0;
    }

    .upgrade-package-card {
        cursor: pointer;
        transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
        border: 1px solid var(--bs-border-color);
    }

    .upgrade-package-card:hover {
        transform: translateY(-2px);
        border-color: rgba(var(--bs-primary-rgb), .35);
        box-shadow: 0 .75rem 1.5rem rgba(15, 23, 42, .08);
    }

    .upgrade-package-card.is-selected {
        border-color: rgba(var(--bs-primary-rgb), .8);
        box-shadow: 0 0 0 .25rem rgba(var(--bs-primary-rgb), .12);
        background: linear-gradient(180deg, rgba(var(--bs-primary-rgb), .05) 0%, rgba(255, 255, 255, 1) 100%);
    }

    .upgrade-package-card.is-disabled {
        opacity: .6;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    #upgrade-package-catalog {
        max-height: 68vh;
        overflow: auto;
        padding-right: .25rem;
    }

    .upgrade-package-card .card-body {
        padding: 1rem;
    }

    .upgrade-package-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .75rem;
        align-items: start;
        margin-bottom: .75rem;
    }

    .upgrade-package-name {
        font-size: .95rem;
        font-weight: 700;
        line-height: 1.25;
        margin-bottom: .2rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .upgrade-package-code {
        color: var(--bs-secondary-color);
        font-size: .75rem;
        line-height: 1.2;
        word-break: break-all;
    }

    .upgrade-package-price-wrap {
        text-align: right;
        white-space: nowrap;
    }

    .upgrade-package-price-wrap .upgrade-price {
        font-size: clamp(1.05rem, .95rem + .55vw, 1.55rem);
    }

    .upgrade-package-description {
        color: var(--bs-secondary-color);
        font-size: .82rem;
        line-height: 1.35;
        min-height: 2.7rem;
        margin-bottom: .75rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .upgrade-feature-chip {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .25rem .5rem;
        border-radius: 999px;
        font-size: .75rem;
        font-weight: 600;
        background: var(--bs-light);
        color: var(--bs-secondary-color);
    }

    .upgrade-feature-chip.is-match {
        background: rgba(var(--bs-success-rgb), .12);
        color: var(--bs-success-text-emphasis);
    }

    .upgrade-summary-list {
        display: grid;
        gap: .75rem;
    }

    .upgrade-summary-item {
        padding: .85rem 1rem;
        border-radius: .85rem;
        background: var(--bs-light);
    }

    .upgrade-price {
        font-size: clamp(1.15rem, 1rem + .7vw, 1.75rem);
        line-height: 1.1;
        letter-spacing: -.02em;
    }

    .upgrade-price-meta {
        font-size: .78rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--bs-secondary-color);
    }

    .upgrade-history-empty,
    .upgrade-state-empty {
        border: 1px dashed var(--bs-border-color);
        border-radius: .85rem;
        padding: 1rem;
        background: rgba(var(--bs-light-rgb), .45);
    }

    .upgrade-primary-action {
        background: linear-gradient(90deg, #f97316, #ea580c);
        border: none;
    }

    .upgrade-primary-action:hover,
    .upgrade-primary-action:focus {
        background: linear-gradient(90deg, #ea580c, #c2410c);
    }

    .upgrade-current-panel {
        background: linear-gradient(180deg, rgba(var(--bs-primary-rgb), .05), #fff 42%);
        border: 1px solid rgba(var(--bs-primary-rgb), .15);
        border-radius: .95rem;
        padding: 1rem;
    }

    .upgrade-recommend-panel {
        background: linear-gradient(180deg, rgba(var(--bs-success-rgb), .08), #fff 46%);
        border: 1px solid rgba(var(--bs-success-rgb), .2);
        border-radius: .95rem;
        padding: 1rem;
    }

    .upgrade-addon-card {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: .95rem;
        background: #fff;
        height: 100%;
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }

    .upgrade-addon-card:hover {
        transform: translateY(-2px);
        border-color: rgba(var(--bs-primary-rgb), .35);
        box-shadow: 0 .8rem 1.6rem rgba(15, 23, 42, .08);
    }

    .upgrade-addon-icon {
        width: 2.1rem;
        height: 2.1rem;
        border-radius: .65rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(var(--bs-primary-rgb), .1);
        color: var(--bs-primary);
        font-size: 1.15rem;
    }

    .upgrade-addon-title {
        font-size: .95rem;
        line-height: 1.25;
        font-weight: 700;
        color: #0f172a;
        min-height: 2.35rem;
    }

    .upgrade-addon-description {
        font-size: .82rem;
        color: #64748b;
        min-height: 2.4rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    @media (max-width: 991.98px) {
        #upgrade-package-catalog {
            max-height: none;
            overflow: visible;
            padding-right: 0;
        }
    }
</style>

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content">

        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h2 class="mb-1">Upgrade Paket</h2>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a></li>
                        <li class="breadcrumb-item">Billing</li>
                        <li class="breadcrumb-item active" aria-current="page">Upgrade</li>
                    </ol>
                </nav>
            </div>
        </div>

        @if ($blocked)
            <div class="alert alert-warning d-flex align-items-start border-0 shadow-sm" role="alert">
                <i class="ti ti-alert-triangle me-2 fs-20"></i>
                <div>
                    <div class="fw-semibold mb-1">Fitur "<span data-testid="blocked-feature">{{ $blockedFeatureLabel ?: $blocked }}</span>" belum termasuk dalam paket aktif.</div>
                    <div class="small text-muted">Employee list memang akan diblok kalau paket aktif belum memuat feature code <span class="fw-semibold">{{ $normalizedBlockedFeature }}</span>. Pilih paket yang mendukung fitur ini untuk membuka aksesnya.</div>
                </div>
            </div>
        @endif

        <div id="upgrade-page-context"
             data-blocked-feature="{{ $normalizedBlockedFeature }}"
             data-blocked-feature-label="{{ $blockedFeatureLabel }}"
             data-is-primary-super-admin="{{ $isPrimarySuperAdminCodeOne ? '1' : '0' }}"
             data-recommended-packages='@json($recommendedPackages)'
               data-current-package='@json($currentPackageSummary)'
               data-checked-out-addon-ids='@json($checkedOutAddonIds)'>
        </div>

        @if (session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
        @endif

        <div class="upgrade-shell mb-4">
            <div class="row g-3">
                <div class="col-12 col-xl-5">
                    <div class="card border-0 upgrade-hero-card h-100">
                        <div class="card-body p-4">
                            <div class="upgrade-kicker mb-1">Current Plan</div>
                            <div class="upgrade-headline">Paket Aktif Saat Ini</div>
                            <p class="upgrade-subline mb-3">Lihat status paket berjalan sebelum mengajukan perubahan.</p>

                            <div class="upgrade-current-panel mb-3">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                    <div>
                                        <h4 class="mb-1" id="upgrade-current-package-name">{{ $currentPackage?->name ?? 'Belum ada paket aktif' }}</h4>
                                        <div class="text-muted small" id="upgrade-current-package-code">{{ $currentPackage?->code ?? 'Aktifkan paket dulu untuk mulai berlangganan.' }}</div>
                                    </div>
                                    <div class="avatar avatar-lg rounded-circle bg-white text-primary border">
                                        <i class="ti ti-package fs-24"></i>
                                    </div>
                                </div>

                                <div id="upgrade-current-change-status" class="alert alert-info py-2 d-none mb-2" role="status"></div>
                                <div id="upgrade-early-activate-wrap" class="d-none mb-2">
                                    <button type="button" class="btn btn-warning btn-sm w-100" id="upgrade-early-activate-btn">
                                        <i class="ti ti-bolt me-1"></i>Aktifkan Sekarang
                                    </button>
                                </div>

                                @if ($currentPackage)
                                    <div class="upgrade-summary-list">
                                        <div class="upgrade-summary-item">
                                            <div class="text-muted small mb-1">Harga bulanan</div>
                                            <div class="upgrade-price text-primary fw-bold" id="upgrade-current-package-monthly">Rp {{ number_format((float) ($currentPackage->monthly_price ?? 0), 0, ',', '.') }}</div>
                                            <div class="upgrade-price-meta">per bulan</div>
                                        </div>
                                        <div class="upgrade-summary-item">
                                            <div class="text-muted small mb-1">Ketersediaan fitur target</div>
                                            <div id="upgrade-current-feature-status" class="fw-semibold {{ in_array($normalizedBlockedFeature, $currentPackageSummary['feature_codes'] ?? [], true) ? 'text-success' : 'text-warning' }}">
                                                {{ in_array($normalizedBlockedFeature, $currentPackageSummary['feature_codes'] ?? [], true) ? 'Sudah tersedia' : 'Belum termasuk di paket aktif' }}
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="upgrade-state-empty small text-muted">
                                        Tenant ini belum punya paket aktif yang bisa dipakai sebagai baseline perubahan.
                                    </div>
                                @endif
                            </div>

                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-7">
                    <div class="card border-0 upgrade-hero-card h-100">
                        <div class="card-body p-4">
                            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                                <div>
                                    <div class="upgrade-kicker mb-1">{{ $recommendationKicker }}</div>
                                    <div class="upgrade-headline">{{ $recommendationTitle }}</div>
                                    <p class="upgrade-subline">{{ $recommendationHeadline }}</p>
                                </div>
                                @if ($recommendationPackages->isNotEmpty())
                                    <span class="badge bg-light text-dark">{{ $recommendationBadge }} · {{ $recommendationPackages->count() }} opsi</span>
                                @endif
                            </div>

                            <div class="upgrade-recommend-panel">
                                <div id="upgrade-recommendation-grid" class="row g-3">
                                    @forelse ($recommendationPackages as $package)
                                        <div class="col-12 col-md-6">
                                            <div class="card h-100 border bg-white shadow-none">
                                                <div class="card-body">
                                                    <div class="fw-semibold">{{ $package['name'] }}</div>
                                                    <div class="text-muted small mb-2">{{ $package['code'] }}</div>
                                                    <div class="upgrade-price text-primary fw-bold mb-1">Rp {{ number_format((float) ($package['monthly_price'] ?? 0), 0, ',', '.') }}</div>
                                                    <div class="upgrade-price-meta mb-2">per bulan</div>
                                                    <div class="small text-muted">
                                                        @if ($recommendationMode === 'fallback')
                                                            {{ $package['description'] ?: 'Alternatif paket aktif yang bisa kamu pilih sambil menunggu paket dengan fitur target tersedia.' }}
                                                        @else
                                                            {{ $package['description'] ?: 'Paket aktif yang mendukung fitur terblokir.' }}
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="col-12">
                                            <div class="upgrade-state-empty small text-muted">
                                                Belum ada paket aktif yang tersedia untuk ditampilkan saat ini. Coba lagi setelah admin menambahkan paket baru atau hubungi tim platform.
                                            </div>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 upgrade-hero-card mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                    <div>
                        <div class="upgrade-kicker mb-1">Add-on Catalog</div>
                        <div class="upgrade-headline">Fitur Tambahan Siap Aktif</div>
                        <p class="upgrade-subline">Pilih add-on, buat invoice, selesaikan pembayaran, lalu fitur aktif otomatis di tenant kamu.</p>
                    </div>
                    <span class="badge bg-primary-subtle text-primary">Add-on</span>
                </div>

                <div class="alert alert-light border mb-3 small" role="status">
                    <div class="fw-semibold mb-1">Alur tambah add-on (end-to-end):</div>
                    <ol class="mb-0 ps-3">
                        <li>Pilih add-on dari katalog di bawah.</li>
                        <li>Klik <span class="fw-semibold">Buat Invoice Add-on</span> pada kartu add-on.</li>
                        <li>Jika invoice terbentuk, lanjutkan pembayaran dari halaman invoice.</li>
                        <li>Setelah paid, add-on aktif otomatis untuk company aktif saat ini.</li>
                    </ol>
                </div>

                <div id="upgrade-addon-feedback" class="d-none mb-3"></div>

                <div id="upgrade-addon-catalog" class="row g-3">
                    <div class="col-12">
                        <div class="upgrade-state-empty small text-muted">
                            <div class="spinner-border spinner-border-sm me-2"></div>
                            Memuat katalog add-on...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card upgrade-stage-card">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                    <div>
                        <div class="upgrade-kicker mb-1">Step By Step</div>
                        <h4 class="card-title mb-1">Ajukan Perubahan Paket</h4>
                        <p class="text-muted mb-0">Pilih aksi, tentukan paket target, cek preview biaya, lalu ajukan untuk approval admin platform.</p>
                    </div>
                    <span class="badge bg-primary-subtle text-primary">Upgrade</span>
                </div>

                <div class="row g-4 align-items-start">
                    <div class="col-12 col-xl-8">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Aksi</label>
                                <select id="upgrade-action" class="form-select">
                                    <option value="upgrade">Upgrade / Ganti Paket</option>
                                    <option value="downgrade">Downgrade</option>
                                    <option value="cancel">Cancel Subscription</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Paket Target</label>
                                <select id="upgrade-target-package" class="form-select"></select>
                            </div>
                            <div class="col-12">
                                <div class="d-flex align-items-center justify-content-between mb-2 gap-2">
                                    <div>
                                        <div class="form-label mb-1">Katalog Paket</div>
                                        <div class="small text-muted">Klik kartu paket untuk memilih target. Katalog akan otomatis diprioritaskan ke paket yang mendukung fitur yang sedang terblokir.</div>
                                    </div>
                                    <span class="badge bg-light text-dark" id="upgrade-package-count-badge">0 paket</span>
                                </div>
                                <div id="upgrade-package-catalog" class="row g-3"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Catatan (opsional)</label>
                                <textarea id="upgrade-notes" class="form-control" rows="2" maxlength="500"
                                          placeholder="Alasan upgrade / informasi tambahan"></textarea>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary" id="upgrade-preview-btn">
                                    <i class="ti ti-eye me-1"></i>Preview
                                </button>
                                <button type="button" class="btn btn-primary upgrade-primary-action" id="upgrade-submit-btn">
                                    <i class="ti ti-send me-1"></i>Ajukan Upgrade
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-4">
                        <div class="card border bg-light-subtle shadow-none upgrade-preview-card mb-3">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h5 class="mb-0">Ringkasan Pilihan</h5>
                                    <span class="badge bg-primary-subtle text-primary">Live</span>
                                </div>
                                <div id="upgrade-selection-summary" class="small text-muted upgrade-state-empty">
                                    Pilih aksi dan paket target untuk melihat ringkasan perubahan.
                                </div>
                            </div>
                        </div>

                        <div id="upgrade-preview-pane" class="card border shadow-none d-none upgrade-preview-card mb-3">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h5 class="mb-0">Preview Perubahan</h5>
                                    <span class="badge bg-success-subtle text-success">Siap diajukan</span>
                                </div>
                                <div id="upgrade-preview-content"></div>
                            </div>
                        </div>

                        <div id="upgrade-status-pane" class="mb-3"></div>
                    </div>
                </div>

                <div class="mt-4">
                    <h5 class="mb-2">Riwayat Pengajuan Saya</h5>
                    <div id="upgrade-request-list" class="border rounded p-3 small text-muted">Memuat data...</div>
                </div>

                @if ($isPrimarySuperAdminCodeOne)
                    <div class="mt-4">
                        <h5 class="mb-2">Pengajuan Upgrade Baru (Admin Code 1)</h5>
                        <div id="upgrade-admin-queue" class="border rounded p-3 small text-muted">Memuat data...</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Modal konfirmasi risiko aktivasi awal --}}
<div class="modal fade" id="modalEarlyActivate" tabindex="-1" aria-labelledby="modalEarlyActivateLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning-subtle">
                <h5 class="modal-title fw-bold" id="modalEarlyActivateLabel">
                    <i class="ti ti-alert-triangle me-2 text-warning"></i>Konfirmasi Aktivasi Lebih Awal
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="fw-semibold mb-2">Anda akan mengaktifkan perubahan paket <strong>lebih awal dari jadwal yang ditetapkan</strong>.</p>
                <p class="text-muted small mb-3">
                    Paket akan langsung diganti ke <span id="modal-early-target-name" class="fw-semibold text-dark">—</span>
                    dan invoice baru akan langsung diterbitkan. Periode berlangganan sebelumnya
                    <strong>tidak akan direfund</strong>.
                </p>

                <div class="alert alert-warning py-2 mb-3">
                    <div class="fw-semibold mb-2"><i class="ti ti-circle-x me-1"></i>Risiko yang perlu dipahami:</div>
                    <ul class="mb-0 small ps-3">
                        <li>Sisa masa aktif paket lama <strong>hangus</strong> dan tidak akan direfund.</li>
                        <li>Akses ke fitur eksklusif paket lama <strong>langsung dicabut</strong> begitu invoice baru dibayar.</li>
                        <li>Invoice baru akan segera diterbitkan — Anda wajib membayarnya untuk mengaktifkan paket baru.</li>
                        <li>Tindakan ini <strong>tidak dapat dibatalkan</strong> setelah dikonfirmasi.</li>
                    </ul>
                </div>

                <div class="alert alert-danger py-2 mb-3 small">
                    <i class="ti ti-info-circle me-1"></i>
                    Dengan mengklik <strong>"Ya, Aktifkan Sekarang"</strong>, Anda menyatakan bahwa segala risiko yang timbul akibat aktivasi lebih awal ini sepenuhnya menjadi tanggung jawab Anda, dan platform tidak bertanggung jawab atas kerugian apapun terkait sisa masa aktif paket sebelumnya.
                </div>

                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="earlyActivateRiskCheck">
                    <label class="form-check-label small fw-semibold" for="earlyActivateRiskCheck">
                        Saya memahami dan menyetujui seluruh risiko di atas. Ini adalah keputusan saya sendiri.
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal, Jangan Aktifkan</button>
                <button type="button" class="btn btn-warning" id="modal-early-activate-confirm-btn" disabled>
                    <i class="ti ti-bolt me-1"></i>Ya, Aktifkan Sekarang
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    // Early activate modal wiring — injected here so Bootstrap is already loaded
    var riskCheck = document.getElementById('earlyActivateRiskCheck');
    var confirmBtn = document.getElementById('modal-early-activate-confirm-btn');
    if (riskCheck && confirmBtn) {
        riskCheck.addEventListener('change', function () {
            confirmBtn.disabled = !riskCheck.checked;
        });
    }
    var modalEl = document.getElementById('modalEarlyActivate');
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', function () {
            if (riskCheck) riskCheck.checked = false;
            if (confirmBtn) confirmBtn.disabled = true;
        });
    }
})();
</script>
@endpush
