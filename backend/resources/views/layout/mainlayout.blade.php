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
	$accountLifecycleStatus = (string) ($latestCompanySubscription?->status ?? $activeCompany?->status ?? '');
	$isPendingPaymentLockShell = request()->routeIs('subscription')
		&& (($latestCompanySubscription?->status ?? null) === 'pending_payment');

	$isAccessLockedAccount = ! $isGlobalHcmAdmin
		&& ($accountLifecycleStatus === 'suspended');
	$accountLockTitle = $accountLifecycleStatus === 'suspended'
		? 'Perusahaan Disuspend'
		: 'Perusahaan Nonaktif';
	$accountLockHeading = $accountLifecycleStatus === 'suspended'
		? 'Akses perusahaan sedang disuspend'
		: 'Akses perusahaan sedang nonaktif';
	$accountLockMessage = $accountLifecycleStatus === 'suspended'
		? 'Akses ke aplikasi dibatasi karena perusahaan sedang dalam status suspend.'
		: 'Akses ke aplikasi tidak dapat dilanjutkan sampai proses renewal atau aktivasi ulang diselesaikan.';
	$accountLockHelpText = $accountLifecycleStatus === 'suspended'
		? 'Hubungi admin platform untuk proses review dan reaktivasi.'
		: 'Hubungi admin platform untuk tindak lanjut renewal atau aktivasi ulang.';

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

	@if (! $isAuthPage && ! $isPendingPaymentLockShell && ! $isAccessLockedAccount)
        @include('layout.partials.header')
        @include('layout.partials.sidebar')
        @include('hcm.partials.hcm-confirm-delete-modal')
		@include('layout.partials.upgrade-required-modal')
		@endif

	@if ($isAccessLockedAccount)
	{{-- Access lock modal — distinguishes inactive billing delinquency from suspended enforcement --}}
	<div class="modal fade" id="suspendedAccountModal" tabindex="-1" aria-labelledby="suspendedAccountModalLabel" aria-modal="true" role="dialog" data-bs-backdrop="static" data-bs-keyboard="false">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content border-0 shadow-xl" style="border-radius: 12px; overflow: hidden;">
				<div class="modal-header bg-danger text-white border-0 py-3" style="background: linear-gradient(135deg, #dc3545 0%, #bb2d3b 100%); padding: 1.5rem !important;">
					<h5 class="modal-title fw-bold fs-5" id="suspendedAccountModalLabel" style="letter-spacing: 0.3px;">
						<i class="ti ti-shield-lock me-2" style="font-size: 1.3rem;"></i>{{ $accountLockTitle }}
					</h5>
				</div>
				<div class="modal-body text-center" style="padding: 2.5rem 2rem; background: #fafafa;">
					<div class="mb-4">
						<div class="d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px; background: #ffe0e0; border-radius: 50%; margin: 0 auto;">
							<i class="ti ti-user-off text-danger" style="font-size: 2.5rem;"></i>
						</div>
					</div>
					<h4 class="fw-bold text-danger mb-2" style="font-size: 1.3rem; letter-spacing: 0.2px;">{{ $accountLockHeading }}</h4>
					<p class="text-muted mb-2" style="font-size: 0.95rem; line-height: 1.6;">{{ $accountLockMessage }}</p>
					<p class="text-muted mb-4" style="font-size: 0.95rem; line-height: 1.6;">{{ $accountLockHelpText }}</p>
					
					{{-- Company Info Card --}}
					<div class="card border-0 mb-4" style="background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-radius: 8px;">
						<div class="card-body" style="padding: 1.25rem;">
							<div class="row g-3">
								<div class="col-6 text-start">
									<div style="font-size: 0.75rem; font-weight: 600; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;">Company Code</div>
									<div style="font-size: 1.1rem; font-weight: 700; color: #212529; font-family: 'Courier New', monospace;">{{ $companyCode }}</div>
								</div>
								<div class="col-6 text-start">
									<div style="font-size: 0.75rem; font-weight: 600; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;">Status</div>
									<span class="badge bg-danger" style="font-size: 0.85rem; padding: 0.5rem 0.75rem; font-weight: 600; letter-spacing: 0.3px;">{{ ucfirst(str_replace('_', ' ', $latestCompanySubscription?->status ?? 'N/A')) }}</span>
								</div>
							</div>
						</div>
					</div>

					<div class="alert alert-warning d-flex align-items-center gap-2" role="alert" style="border-radius: 6px; border: 1px solid #ffc107; background: #fffbf0; margin-bottom: 0;">
						<i class="ti ti-alert-circle" style="font-size: 1.2rem; flex-shrink: 0;"></i>
						<small class="text-warning-emphasis" style="font-size: 0.85rem;">Hubungi tim support untuk informasi lebih lanjut</small>
					</div>
				</div>
				<div class="modal-footer justify-content-center border-0 py-3" style="padding: 1.5rem 2rem; background: #f8f9fa;">
					<a href="{{ route('signout') }}" class="btn btn-danger px-5 py-2 fw-600" style="border-radius: 6px; font-weight: 600; letter-spacing: 0.3px;">
						<i class="ti ti-logout me-2"></i>Logout
					</a>
				</div>
			</div>
		</div>
	</div>

	{{-- Backdrop overlay styling --}}
	<style>
		#suspendedAccountModal.show ~ .modal-backdrop {
			background-color: rgba(0, 0, 0, 0.6);
			backdrop-filter: blur(2px);
		}
		.modal-backdrop.show {
			background-color: rgba(0, 0, 0, 0.6);
			backdrop-filter: blur(2px);
		}
		#suspendedAccountModal .modal-content {
			box-shadow: 0 10px 40px rgba(220, 53, 69, 0.25) !important;
		}
	</style>

	<script>
		document.addEventListener('DOMContentLoaded', function () {
			var el = document.getElementById('suspendedAccountModal');
			if (el && typeof bootstrap !== 'undefined') {
				new bootstrap.Modal(el, { backdrop: 'static', keyboard: false }).show();
			}
		});
	</script>
	@endif

    @yield('content')

</div>
<!-- /Main Wrapper -->
@include('layout.partials.footerarkav')
@include('layout.partials.footer-scripts')
</body>

</html>
