<?php

$configuredSiteKey = (string) env('TURNSTILE_SITE_KEY', '');
$configuredSecretKey = (string) env('TURNSTILE_SECRET_KEY', '');
$appEnv = (string) env('APP_ENV', 'production');
$hideTestNotice = (bool) env('TURNSTILE_HIDE_TEST_NOTICE', false);

$requestHost = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
if ($requestHost === '') {
    $requestHost = (string) parse_url((string) env('APP_URL', ''), PHP_URL_HOST);
}

$requestHost = strtolower(trim(preg_replace('/:\d+$/', '', $requestHost) ?? ''));
$isLocalTurnstileHost = in_array($requestHost, ['localhost', '127.0.0.1', '0.0.0.0'], true);

$localSiteKey = (string) env('TURNSTILE_LOCAL_SITE_KEY', '1x00000000000000000000AA');
$localSecretKey = (string) env('TURNSTILE_LOCAL_SECRET_KEY', '1x0000000000000000000000000000000AA');

$visibleTestSiteKeys = [
    '1x00000000000000000000AA',
    '2x00000000000000000000AB',
    '3x00000000000000000000FF',
];

$allTestSiteKeys = [
    ...$visibleTestSiteKeys,
    '1x00000000000000000000BB',
    '2x00000000000000000000BB',
];

$effectiveSiteKey = $configuredSiteKey;
$effectiveSecretKey = $configuredSecretKey;

if ($appEnv !== 'production' && $isLocalTurnstileHost && $localSiteKey !== '' && $localSecretKey !== '') {
    $effectiveSiteKey = $localSiteKey;
    $effectiveSecretKey = $localSecretKey;
} elseif ($appEnv !== 'production' && $configuredSiteKey === '3x00000000000000000000FF') {
    // The interactive visible test key is unreliable in local onboarding flows here.
    // Normalize to the standard visible-pass test key so the widget iframe renders.
    $effectiveSiteKey = '1x00000000000000000000AA';
    $effectiveSecretKey = '1x0000000000000000000000000000000AA';
}

return [
    /**
     * Cloudflare Turnstile (captcha) for public onboarding/register flows.
     *
     * Docs: https://developers.cloudflare.com/turnstile/get-started/server-side-validation
     */
    'enabled' => (bool) env('TURNSTILE_ENABLED', false),
    'site_key' => $effectiveSiteKey,
    'hide_test_notice' => $hideTestNotice && in_array($effectiveSiteKey, $allTestSiteKeys, true),
    'secret_key' => $effectiveSecretKey,
    'verify_url' => (string) env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
];
