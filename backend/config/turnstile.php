<?php

$configuredSiteKey = (string) env('TURNSTILE_SITE_KEY', '');
$appEnv = (string) env('APP_ENV', 'production');

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
if ($appEnv === 'staging' && in_array($configuredSiteKey, $visibleTestSiteKeys, true)) {
    // Hide Cloudflare's visible testing badge on staging while keeping dummy-key verification flow active.
    $effectiveSiteKey = '1x00000000000000000000BB';
}

return [
    /**
     * Cloudflare Turnstile (captcha) for public onboarding/register flows.
     *
     * Docs: https://developers.cloudflare.com/turnstile/get-started/server-side-validation
     */
    'enabled' => (bool) env('TURNSTILE_ENABLED', false),
    'site_key' => $effectiveSiteKey,
    'hide_test_notice' => in_array($configuredSiteKey, $allTestSiteKeys, true),
    'secret_key' => (string) env('TURNSTILE_SECRET_KEY', ''),
    'verify_url' => (string) env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
];

