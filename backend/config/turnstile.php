<?php

return [
    /**
     * Cloudflare Turnstile (captcha) for public onboarding/register flows.
     *
     * Docs: https://developers.cloudflare.com/turnstile/get-started/server-side-validation
     */
    'enabled' => (bool) env('TURNSTILE_ENABLED', false),
    'site_key' => (string) env('TURNSTILE_SITE_KEY', ''),
    'secret_key' => (string) env('TURNSTILE_SECRET_KEY', ''),
    'verify_url' => (string) env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
];

