<!DOCTYPE html>
<html lang="id">
<head>
	@php
		$companyName = App\Support\WebsiteSettings::businessCompanyName();
		$faviconUrl = App\Support\WebsiteSettings::brandingUrl('favicon', url('build/img/favicon.png'));
		$darkLogoUrl = App\Support\WebsiteSettings::brandingUrl('dark_logo', URL::asset('build/img/image111.png'));
	@endphp
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
	<meta name="robots" content="index, follow">
	<title>{{ $companyName }} - @yield('title', 'Dokumen Legal')</title>
	<link rel="shortcut icon" type="image/x-icon" href="{{ $faviconUrl }}">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }
		body {
			font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
			background: #FAFAF8;
			color: #1A1D24;
			-webkit-font-smoothing: antialiased;
			line-height: 1.7;
		}
		.site-header {
			position: sticky;
			top: 0;
			z-index: 50;
			background: rgba(250,250,248,0.92);
			backdrop-filter: blur(16px);
			border-bottom: 1px solid rgba(0,0,0,0.06);
		}
		.site-header .inner {
			max-width: 800px;
			margin: 0 auto;
			padding: 16px 24px;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 16px;
		}
		.site-header .brand {
			display: flex;
			align-items: center;
			gap: 12px;
			text-decoration: none;
			color: inherit;
		}
		.site-header .brand img { height: 36px; width: auto; }
		.site-header .brand-text { font-size: 15px; font-weight: 700; letter-spacing: -0.02em; }
		.site-header .brand-small { font-size: 11px; color: #5b6474; font-weight: 500; }
		.site-header .nav-links { display: flex; gap: 8px; align-items: center; }
		.site-header .nav-links a {
			font-size: 13px;
			font-weight: 500;
			color: #5b6474;
			text-decoration: none;
			padding: 6px 14px;
			border-radius: 6px;
			transition: all 0.2s;
		}
		.site-header .nav-links a:hover { color: #FF6600; background: rgba(255,102,0,0.06); }
		.site-header .nav-links .btn-primary {
			background: #FF6600;
			color: #fff;
			padding: 7px 18px;
			border-radius: 6px;
			font-weight: 600;
		}
		.site-header .nav-links .btn-primary:hover { background: #E05300; color: #fff; }
		.page-wrapper {
			max-width: 800px;
			margin: 0 auto;
			padding: 48px 24px 80px;
		}
		.legal-badge {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 4px 12px;
			font-size: 11px;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.06em;
			color: #FF6600;
			background: rgba(255,102,0,0.06);
			border: 1px solid rgba(255,102,0,0.12);
			margin-bottom: 16px;
		}
		h1 {
			font-family: 'Plus Jakarta Sans', sans-serif;
			font-size: 32px;
			font-weight: 800;
			letter-spacing: -0.03em;
			line-height: 1.2;
			color: #1A1D24;
			margin-bottom: 8px;
		}
		.lead { font-size: 15px; color: #5b6474; margin-bottom: 24px; max-width: 600px; line-height: 1.6; }
		.meta { font-size: 12px; color: #8a9099; margin-bottom: 32px; }
		.section { margin-bottom: 40px; }
		.section h2 {
			font-family: 'Plus Jakarta Sans', sans-serif;
			font-size: 20px;
			font-weight: 700;
			letter-spacing: -0.02em;
			color: #1A1D24;
			margin-bottom: 12px;
			padding-bottom: 8px;
			border-bottom: 2px solid rgba(255,102,0,0.1);
		}
		.section h3 {
			font-size: 15px;
			font-weight: 600;
			color: #1A1D24;
			margin-bottom: 8px;
			margin-top: 16px;
		}
		.section p { font-size: 14px; color: #334155; margin-bottom: 12px; line-height: 1.7; }
		.section ul { list-style: none; padding: 0; margin-bottom: 12px; }
		.section ul li {
			font-size: 14px;
			color: #334155;
			padding: 4px 0 4px 20px;
			position: relative;
			line-height: 1.6;
		}
		.section ul li::before {
			content: '';
			position: absolute;
			left: 4px;
			top: 12px;
			width: 6px;
			height: 6px;
			background: #FF6600;
			opacity: 0.4;
		}
		.section ul li strong { color: #1A1D24; }
		.section a { color: #FF6600; text-decoration: underline; text-underline-offset: 2px; }
		.section a:hover { color: #E05300; }
		.table-wrap {
			overflow-x: auto;
			margin-bottom: 16px;
			border: 1px solid #e5e7eb;
		}
		.table-wrap table {
			width: 100%;
			border-collapse: collapse;
			font-size: 13px;
		}
		.table-wrap th {
			background: #f8fafc;
			font-weight: 600;
			color: #1A1D24;
			text-align: left;
			padding: 10px 14px;
			border-bottom: 2px solid #e5e7eb;
			font-size: 12px;
			text-transform: uppercase;
			letter-spacing: 0.04em;
		}
		.table-wrap td {
			padding: 10px 14px;
			border-bottom: 1px solid #f0f0f0;
			color: #334155;
		}
		.table-wrap tr:last-child td { border-bottom: none; }
		.table-wrap tr:hover td { background: #fafafa; }
		.footnote { font-size: 12px; color: #8a9099; margin-top: -8px; margin-bottom: 16px; line-height: 1.6; }
		.site-footer {
			border-top: 1px solid rgba(0,0,0,0.06);
			background: #fff;
		}
		.site-footer .inner {
			max-width: 800px;
			margin: 0 auto;
			padding: 24px;
			display: flex;
			justify-content: space-between;
			align-items: center;
			font-size: 12px;
			color: #8a9099;
		}
		@media (max-width: 640px) {
			.page-wrapper { padding: 32px 16px 60px; }
			h1 { font-size: 26px; }
			.site-header .inner { flex-wrap: wrap; }
			.site-header .brand-small { display: none; }
			.site-header .nav-links a { font-size: 12px; padding: 4px 10px; }
		}
	</style>
</head>
<body>
	<header class="site-header">
		<div class="inner">
			<a href="{{ route('landing') }}" class="brand">
				<img src="{{ $darkLogoUrl }}" alt="{{ $companyName }}">
				<div>
					<div class="brand-text">{{ $companyName }}</div>
					<div class="brand-small">Dokumen legal</div>
				</div>
			</a>
			<nav class="nav-links">
				<a href="{{ route('privacy-policy') }}">Kebijakan Privasi</a>
				<a href="{{ route('terms-condition') }}">Syarat & Ketentuan</a>
				<a href="{{ route('landing') }}" class="btn-primary">Kembali</a>
			</nav>
		</div>
	</header>

	<main class="page-wrapper">
		@yield('content')
	</main>

	<footer class="site-footer">
		<div class="inner">
			<span>&copy; {{ now()->year }} {{ $companyName }}</span>
			<span>Dokumen legal publik</span>
		</div>
	</footer>
</body>
</html>
