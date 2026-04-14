<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
	<meta name="description" content="Empowering your Human Capital Organization">
	<meta name="robots" content="noindex, nofollow">
	@php
		$companyName = \App\Support\WebsiteSettings::businessCompanyName();
		$faviconUrl = \App\Support\WebsiteSettings::brandingUrl('favicon', url('build/img/favicon.png'));
	@endphp
	<title>{{ $companyName }}</title>
	<link rel="shortcut icon" type="image/x-icon" href="{{ $faviconUrl }}">
	<link rel="stylesheet" href="{{ url('build/css/bootstrap.min.css') }}">
	<link rel="stylesheet" href="{{ url('build/plugins/icons/feather/feather.css') }}">
	<link rel="stylesheet" href="{{ url('build/plugins/tabler-icons/tabler-icons.css') }}">
	<link rel="stylesheet" href="{{ url('build/css/style.css') }}">
</head>
<body class="bg-linear-gradiant">
<div class="main-wrapper min-vh-100 d-flex flex-column">
	@yield('content')
</div>
</body>
</html>
