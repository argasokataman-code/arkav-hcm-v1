<!DOCTYPE html>

@if (Route::is(['layout-horizontal']))
	<html lang="en" data-layout="horizontal">
@elseif (Route::is(['layout-detached']))
	<html lang="en" data-layout="detached">
@elseif (Route::is(['layout-modern']))
	<html lang="en" data-layout="modern">
@elseif (Route::is(['layout-horizontal-overlay']))
	<html lang="en"  data-layout="horizontal-overlay">
@elseif (Route::is(['layout-two-column']))
	<html lang="en"  data-layout="twocolumn">
@elseif (Route::is(['layout-hovered']))
	<html lang="en" data-layout="layout-hovered">
@elseif (Route::is(['layout-box']))
	<html lang="en" data-layout="default" data-width="box">
@elseif (Route::is(['layout-horizontal-single']))
	<html lang="en"  data-layout="horizontal-single">
@elseif (Route::is(['layout-horizontal-box']))
	<html lang="en"  data-layout="horizontal-box">
@elseif (Route::is(['layout-horizontal-sidemenu']))
	<html lang="en"  data-layout="horizontal-sidemenu">
@elseif (Route::is(['layout-vertical-transparent']))
	<html lang="en" data-layout="transparent">
@elseif (Route::is(['layout-without-header']))
	<html lang="en" data-layout="without-header">
@elseif (Route::is(['layout-dark']))
	<html lang="en" data-theme="dark">
@else
	<html lang="en">
@endif

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
	<meta name="description" content="Empowering your Human Capital Organization">
	<meta name="keywords" content="admin, estimates, bootstrap, business, html5, responsive, Projects">
	<meta name="author" content="Arkav - Human Capital Management">
	<meta name="robots" content="noindex, nofollow">
	<title>Arkav - Human Capital Management</title>


    @include('layout.partials.head')
</head>

@if (!Route::is(['login',
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
'lock-screen',
'error-404',
'error-500',
'coming-soon',
'under-maintenance',
'under-construction',
'success',
'success-2',
'success-3'

]))
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

@if (Route::is(['layout-horizontal', 'layout-horizontal-overlay', 'layout-horizontal-single', 'layout-horizontal-box']))
	<body class="menu-horizontal">
@endif

@if (Route::is(['layout-hovered']))
	<body class="mini-sidebar expand-menu">
@endif

@if (Route::is(['layout-box']))
	<body class="mini-sidebar layout-box-mode">
@endif

@if (Route::is(['layout-vertical-transparent']))
	<body class="data-layout-transparent">
@endif

@if (Route::is(['layout-rtl']))
	<body class="layout-mode-rtl">
@endif


<!-- Main Wrapper -->
<div class="main-wrapper">

    @if (!Route::is(['login',
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
'lock-screen',
'error-404',
'error-500',
'coming-soon',
'under-maintenance',
'under-construction',
'success',
'success-2',
'success-3',
'two-step-verification',
'two-step-verification-2',
'two-step-verification-3'
	]))
        @include('layout.partials.header')
        @include('layout.partials.sidebar')
        @include('hcm.partials.hcm-confirm-delete-modal')
		@endif

    @yield('content')

</div>
<!-- /Main Wrapper -->
@include('layout.partials.footerarkav')
@include('layout.partials.footer-scripts')
</body>

</html>
