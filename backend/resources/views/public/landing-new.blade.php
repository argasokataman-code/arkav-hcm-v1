<!doctype html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="{{ $landingBootstrap['companyName'] ?? 'Arkav HCM' }} — Platform HR Digital untuk Perusahaan Indonesia. Absensi, cuti, payroll, dan laporan dalam satu platform." />
    <meta name="robots" content="index, follow" />
    <title>{{ $landingBootstrap['companyName'] ?? 'Arkav HCM' }} — Platform HR Digital Terintegrasi</title>
    <link rel="stylesheet" href="{{ url('landing-assets/landing.css') }}?v={{ filemtime(public_path('landing-assets/landing.css')) }}">
    @if ($landingBootstrap['turnstileEnabled'] && $landingBootstrap['turnstileSiteKey'])
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
    @endif
  </head>
  <body>
    <div id="root"></div>
    <script id="landing-app-data" type="application/json">@json($landingBootstrap)</script>
    <script type="module" src="{{ url('landing-assets/landing.js') }}?v={{ filemtime(public_path('landing-assets/landing.js')) }}"></script>
  </body>
</html>
