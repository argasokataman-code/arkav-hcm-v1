<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
	@php
		$companyName = \\App\\Support\\WebsiteSettings::businessCompanyName();
		$faviconUrl = \\App\\Support\\WebsiteSettings::brandingUrl('favicon', url('build/img/favicon.png'));
		$darkLogoUrl = \\App\\Support\\WebsiteSettings::brandingUrl('dark_logo', URL::asset('build/img/image111.png'));
	@endphp
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
	<meta name="description" content="Empowering your Human Capital Organization">
	<meta name="robots" content="noindex, nofollow">
	<title>{{ $companyName }} - @yield('title', 'Dokumen Legal')</title>
	<link rel="shortcut icon" type="image/x-icon" href="{{ $faviconUrl }}">
	<link rel="stylesheet" href="{{ url('build/css/bootstrap.min.css') }}">
	<link rel="stylesheet" href="{{ url('build/plugins/icons/feather/feather.css') }}">
	<link rel="stylesheet" href="{{ url('build/plugins/icons/bootstrap/bootstrap-icons.min.css') }}">
	<link rel="stylesheet" href="{{ url('build/plugins/tabler-icons/tabler-icons.css') }}">
	<link rel="stylesheet" href="{{ url('build/css/style.css') }}">
	@stack('styles')
</head>
<body class="bg-light">
	<div class="min-vh-100 d-flex flex-column" data-page-shell="guest-legal">
		<header class="border-bottom bg-white shadow-sm">
			<div class="container py-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
				<a href="{{ route('landing') }}" class="d-inline-flex align-items-center gap-3 text-decoration-none text-dark">
					<img src="{{ $darkLogoUrl }}" alt="{{ $companyName }}" style="height: 42px; width: auto;">
					<span>
						<span class="d-block fw-semibold">{{ $companyName }}</span>
						<span class="d-block small text-muted">Dokumen legal untuk calon customer dan tenant aktif</span>
					</span>
				</a>
				<div class="d-flex flex-wrap align-items-center gap-2">
					<a href="{{ route('privacy-policy') }}" class="btn btn-link text-decoration-none px-2">Kebijakan Privasi</a>
					<a href="{{ route('terms-condition') }}" class="btn btn-link text-decoration-none px-2">Syarat &amp; Ketentuan</a>
					<a href="{{ route('login') }}" class="btn btn-outline-primary">Masuk</a>
				</div>
			</div>
		</header>

		<main class="flex-grow-1 py-5">
			<div class="container">
				<div class="row justify-content-center">
					<div class="col-12 col-xl-10">
						<div class="card border-0 shadow-sm">
							<div class="card-body p-4 p-lg-5">
								@yield('content')
							</div>
						</div>
					</div>
				</div>
			</div>
		</main>

		<footer class="border-top bg-white">
			<div class="container py-3 d-flex flex-column flex-md-row justify-content-between gap-2 small text-muted">
				<span>&copy; {{ now()->year }} {{ $companyName }}</span>
				<span>Dokumen legal publik ARCAV HCM</span>
			</div>
		</footer>
	</div>
	<script src="{{ URL::asset('build/js/vendor/bootstrap.bundle.min.js') }}"></script>
	@stack('scripts')
</body>
</html>