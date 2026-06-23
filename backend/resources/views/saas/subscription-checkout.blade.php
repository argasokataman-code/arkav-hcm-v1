<?php $page = 'subscription-checkout'; ?>
@extends('layout.mainlayout')
@section('content')

@php
    $checkoutCompany = request()->attributes->get('activeCompany');
    $latestCheckoutSubscription = $checkoutCompany instanceof \App\Models\Company
        ? $checkoutCompany->latestSubscription()->with('package')->first()
        : null;
    $preloadedPendingInvoice = $checkoutCompany instanceof \App\Models\Company
        ? \App\Models\Invoice::query()
            ->where('company_id', $checkoutCompany->id)
            ->where('is_paid', false)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first()
        : null;
    $isPendingPaymentLock = ($latestCheckoutSubscription?->status ?? null) === 'pending_payment';
    $isInactiveContext = in_array(($latestCheckoutSubscription?->status ?? null), ['inactive', 'expired'], true);
    $isTrialContext = ($latestCheckoutSubscription?->status ?? null) === 'trial';
    $hasBlockingPendingInvoice = $preloadedPendingInvoice instanceof \App\Models\Invoice;
    $showCheckoutCreationForms = true; // Always show form — pending invoice will be warned, not blocked
    $showAddonCheckout = $showCheckoutCreationForms && ! $isInactiveContext;
    $isActiveCheckoutOnly = ($latestCheckoutSubscription?->status ?? null) === 'active'
        && ($latestCheckoutSubscription?->ends_at === null || $latestCheckoutSubscription?->ends_at->isFuture());
    $activePackage = $latestCheckoutSubscription?->package;
    $activeBillingCycle = (string) ($latestCheckoutSubscription?->billing_cycle ?? 'monthly');
    $activePackageAmount = $activeBillingCycle === 'yearly'
        ? (float) ($activePackage?->yearly_price ?? 0)
        : (float) ($activePackage?->monthly_price ?? 0);
    $activePackageCycleLabel = $activeBillingCycle === 'yearly' ? '/tahun' : '/bulan';
    $activeStartDate = $latestCheckoutSubscription?->starts_at
        ? $latestCheckoutSubscription->starts_at->timezone('Asia/Jakarta')->format('d M Y')
        : '-';
    $activeEndDate = $latestCheckoutSubscription?->ends_at
        ? $latestCheckoutSubscription->ends_at->timezone('Asia/Jakarta')->format('d M Y')
        : 'Tidak dibatasi';
    $activeAutoRenewLabel = $latestCheckoutSubscription?->auto_renew ? 'Aktif' : 'Nonaktif';
    $activePaidAddons = collect();
    if ($isActiveCheckoutOnly && $checkoutCompany instanceof \App\Models\Company) {
        $activePaidAddons = \App\Models\PurchaseTransaction::query()
            ->with(['packageAddon:id,code,name,price_per_unit,unit_name'])
            ->where('company_id', $checkoutCompany->id)
            ->where('transaction_type', 'addon')
            ->where('status', 'paid')
            ->whereNotNull('package_addon_id')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get()
            ->unique('package_addon_id')
            ->values();
    }

    $checkoutPageTitle = $isActiveCheckoutOnly
        ? 'Langganan Aktif'
        : ($isPendingPaymentLock
            ? 'Selesaikan Pembayaran'
            : ($isInactiveContext
                ? 'Langganan Berakhir — Perbarui Sekarang'
                : 'Pilih Paket Baru'));

    $checkoutBreadcrumbLabel = $isActiveCheckoutOnly
        ? 'Langganan Aktif'
        : ($isPendingPaymentLock
            ? 'Pembayaran'
            : ($isInactiveContext
                ? 'Perbarui Langganan'
                : 'Checkout'));

    $checkoutHeading = $isPendingPaymentLock
        ? 'Aktifkan Langganan'
        : ($isInactiveContext
            ? 'Perbarui Langganan'
            : ($isTrialContext
                ? 'Pilih Paket Berbayar'
                : 'Upgrade Paket'));

    $checkoutSubheading = $isPendingPaymentLock
        ? 'Invoice pendaftaran telah dibuat. Selesaikan pembayaran untuk membuka akses aplikasi.'
        : ($isInactiveContext
            ? 'Langganan Anda telah berakhir. Pilih paket dan lakukan pembayaran untuk mengaktifkan kembali akses aplikasi.'
            : ($isTrialContext
                ? 'Masa trial akan segera berakhir. Pilih paket untuk melanjutkan layanan.'
                : 'Pilih paket baru untuk menambah atau meningkatkan layanan langganan Anda.'));

    $checkoutCtaLabel = $isPendingPaymentLock
        ? 'Buat Invoice Baru'
        : ($isInactiveContext
            ? 'Buat Invoice & Aktifkan Kembali'
            : 'Buat Invoice & Lanjut Bayar');

    $packageHintLabel = $isPendingPaymentLock
        ? 'Paket trial tidak tersedia karena Anda memilih langganan berbayar saat pendaftaran.'
        : 'Paket trial tidak ditampilkan. Halaman ini khusus untuk paket berbayar.';

    $pendingLockSupportText = $isInactiveContext
        ? 'Setelah pembayaran berhasil, akses aplikasi akan aktif kembali secara otomatis.'
        : 'Setelah pembayaran berhasil, akses aplikasi langsung terbuka.';
@endphp

@include('saas.partials._styles')

<div class="page-wrapper">
    @php
        $requestHost = strtolower((string) request()->getHost());
        $forwardedHost = strtolower(trim((string) request()->header('X-Forwarded-Host', '')));
        $appUrlHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $isNgrokRuntime = str_contains($requestHost, 'ngrok')
            || str_contains($forwardedHost, 'ngrok')
            || str_contains($appUrlHost, 'ngrok');

        $checkoutHostedEnabled = $isNgrokRuntime
            ? (bool) config('services.midtrans.server_key')
            : (app()->isLocal() || (bool) config('services.midtrans.server_key') || (bool) config('app.mock_payments_enabled'));
    @endphp
    <div
        class="content"
        data-subscription-checkout-page
        data-checkout-hosted-pay-enabled="{{ $checkoutHostedEnabled ? '1' : '0' }}"
        data-checkout-pending-lock="{{ $isPendingPaymentLock ? '1' : '0' }}"
        data-checkout-active-only="{{ $isActiveCheckoutOnly ? '1' : '0' }}"
        data-checkout-inactive-context="{{ $isInactiveContext ? '1' : '0' }}"
        data-checkout-preloaded-pending-invoice="{{ $hasBlockingPendingInvoice ? '1' : '0' }}"
        data-checkout-creation-locked="0"
    >
        <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
            <div class="my-auto mb-2">
                <h4 class="mb-1">{{ $checkoutPageTitle }}</h4>
                <nav>
                    <ol class="breadcrumb mb-0">
                        @if (! $isPendingPaymentLock)
                            <li class="breadcrumb-item">
                                <a href="{{ url('index') }}"><i class="ti ti-smart-home"></i></a>
                            </li>
                        @endif
                        <li class="breadcrumb-item">Billing</li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $checkoutBreadcrumbLabel }}</li>
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
                        <i class="ti ti-file-invoice me-1"></i> Semua Tagihan
                    </a>
                @endif
            </div>
        </div>

        @if ($isPendingPaymentLock)
            @include('saas.partials._pending')
        @elseif ($isActiveCheckoutOnly)
            @include('saas.partials._active')
        @else
            @include('saas.partials._default')
        @endif
    </div>
</div>

@include('saas.partials._js')
@endsection
