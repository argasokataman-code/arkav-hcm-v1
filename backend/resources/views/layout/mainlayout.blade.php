<!DOCTYPE html>

@php
	$authUser = request()->user() ?: auth()->user();
	$primarySuperAdminEmail = strtolower(trim((string) config('hcm.admin_email', 'qa.login@example.com')));
	$authUserEmail = strtolower(trim((string) ($authUser->email ?? '')));
	$isPrimarySuperAdmin = $authUser && $authUserEmail === $primarySuperAdminEmail;
	$isHcmAdmin = (bool) ($authUser?->isHcmAdmin());
	$activeCompany = request()->attributes->get('activeCompany');
	$activeCompanySubscription = $activeCompany instanceof \App\Models\Company
		? $activeCompany->activeSubscription()
		: null;
	$latestCompanySubscription = $activeCompany instanceof \App\Models\Company
		? $activeCompany->latestSubscription()->first()
		: null;
	$displayCompanySubscription = $activeCompanySubscription ?: $latestCompanySubscription;
	$activePackage = $activeCompanySubscription?->package;
	$canUseTemplateLayouts = (bool) $isPrimarySuperAdmin;
	$isGlobalHcmAdmin = (bool) ($authUser?->isGlobalHcmAdmin());
	$hasPayrollFeature = (bool) ($activePackage?->hasFeature('payroll') ?? false) || $isGlobalHcmAdmin;
	$hasPerformanceFeature = (bool) ($activePackage?->hasFeature('performance') ?? false) || $isGlobalHcmAdmin;
	$hasAssetManagementFeature = (bool) ($activePackage?->hasFeature('asset_management') ?? false) || $isGlobalHcmAdmin;
	$companyCode = (string) ($activeCompany->code ?? '');
	$isPendingPaymentLockShell = request()->routeIs('subscription')
		&& (($latestCompanySubscription?->status ?? null) === 'pending_payment');

	$authRouteNames = [
		'login',
		'login-2',
		'login-3',
		'register',
		'register-2',
		'register-3',
		'forgot-password',
		'forgot-password-2',
		'forgot-password-3',
		'reset-password',
		'reset-password-2',
		'reset-password-3',
		'email-verification',
		'email-verification-2',
		'email-verification-3',
		'two-step-verification',
		'two-step-verification-2',
		'two-step-verification-3',
		'lock-screen',
		'success',
		'success-2',
		'success-3',
		'error-404',
		'error-500',
		'coming-soon',
		'under-maintenance',
		'under-construction',
	];
	$isAuthPage = request()->routeIs($authRouteNames) || request()->is('/') || request()->is('login');
@endphp

@if ($canUseTemplateLayouts && Route::is(['layout-horizontal']))
	<html lang="en" data-layout="horizontal">
@elseif ($canUseTemplateLayouts && Route::is(['layout-detached']))
	<html lang="en" data-layout="detached">
@elseif ($canUseTemplateLayouts && Route::is(['layout-modern']))
	<html lang="en" data-layout="modern">
@elseif ($canUseTemplateLayouts && Route::is(['layout-horizontal-overlay']))
	<html lang="en"  data-layout="horizontal-overlay">
@elseif ($canUseTemplateLayouts && Route::is(['layout-two-column']))
	<html lang="en"  data-layout="twocolumn">
@elseif ($canUseTemplateLayouts && Route::is(['layout-hovered']))
	<html lang="en" data-layout="layout-hovered">
@elseif ($canUseTemplateLayouts && Route::is(['layout-box']))
	<html lang="en" data-layout="default" data-width="box">
@elseif ($canUseTemplateLayouts && Route::is(['layout-horizontal-single']))
	<html lang="en"  data-layout="horizontal-single">
@elseif ($canUseTemplateLayouts && Route::is(['layout-horizontal-box']))
	<html lang="en"  data-layout="horizontal-box">
@elseif ($canUseTemplateLayouts && Route::is(['layout-horizontal-sidemenu']))
	<html lang="en"  data-layout="horizontal-sidemenu">
@elseif ($canUseTemplateLayouts && Route::is(['layout-vertical-transparent']))
	<html lang="en" data-layout="transparent">
@elseif ($canUseTemplateLayouts && Route::is(['layout-without-header']))
	<html lang="en" data-layout="without-header">
@elseif ($canUseTemplateLayouts && Route::is(['layout-dark']))
	<html lang="en" data-theme="dark">
@else
	<html lang="en">
@endif

<head>
	@php
		$companyName = \App\Support\WebsiteSettings::businessCompanyName();
	@endphp
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
	<meta name="description" content="Empowering your Human Capital Organization">
	<meta name="keywords" content="admin, estimates, bootstrap, business, html5, responsive, Projects">
	<meta name="author" content="{{ $companyName }}">
	<meta name="robots" content="noindex, nofollow">
	<title>{{ $companyName }}</title>


    @include('layout.partials.head')
</head>

@if (! $isAuthPage && ! $isPendingPaymentLockShell)
<body>
@endif

@if (Route::is(['login','login-2','register','register-2',
'forgot-password',
'forgot-password-2',
'reset-password',
'reset-password-2',
'email-verification',
'email-verification-2',
'two-step-verification',
'two-step-verification-2',
'success',
'success-2'
]))
<body class="bg-white">
@endif

@if (Route::is(['login-3','register-3','forgot-password-3','reset-password-3',
'email-verification-3',
'two-step-verification-3',
'lock-screen',
'error-404',
'error-500',
'under-maintenance',
'under-construction']))
	<body class="bg-linear-gradiant">
@endif

@if (Route::is(['coming-soon']))
	<body class="bg-linear-gradiant d-flex align-items-center justify-content-center">
@endif

@if ($canUseTemplateLayouts && Route::is(['layout-horizontal', 'layout-horizontal-overlay', 'layout-horizontal-single', 'layout-horizontal-box']))
	<body class="menu-horizontal">
@endif

@if ($canUseTemplateLayouts && Route::is(['layout-hovered']))
	<body class="mini-sidebar expand-menu">
@endif

@if ($canUseTemplateLayouts && Route::is(['layout-box']))
	<body class="mini-sidebar layout-box-mode">
@endif

@if ($canUseTemplateLayouts && Route::is(['layout-vertical-transparent']))
	<body class="data-layout-transparent">
@endif

@if ($canUseTemplateLayouts && Route::is(['layout-rtl']))
	<body class="layout-mode-rtl">
@endif


<!-- Main Wrapper -->
<div class="main-wrapper"
	data-role-scope="{{ $isHcmAdmin ? 'hcm-admin' : 'employee' }}"
	data-primary-super-admin="{{ $isPrimarySuperAdmin ? '1' : '0' }}"
	data-template-layouts-enabled="{{ $canUseTemplateLayouts ? '1' : '0' }}"
	data-company-code="{{ $companyCode }}"
	data-subscription-status="{{ $displayCompanySubscription?->status ?? '' }}"
	data-subscription-plan="{{ $displayCompanySubscription?->plan_code ?? '' }}"
	data-feature-payroll="{{ $hasPayrollFeature ? '1' : '0' }}"
	data-feature-performance="{{ $hasPerformanceFeature ? '1' : '0' }}"
	data-feature-asset-management="{{ $hasAssetManagementFeature ? '1' : '0' }}"
>

	@if (! $isAuthPage && ! $isPendingPaymentLockShell)
        @include('layout.partials.header')
        @include('layout.partials.sidebar')
        @include('hcm.partials.hcm-confirm-delete-modal')
		@include('layout.partials.upgrade-required-modal')
		@endif

    @yield('content')

</div>
<!-- /Main Wrapper -->
@include('layout.partials.footerarkav')
@include('layout.partials.footer-scripts')
</body>

</html>
