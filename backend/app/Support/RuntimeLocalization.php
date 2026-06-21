<?php

namespace App\Support;

class RuntimeLocalization
{
    /**
     * @var array<string, bool>|null
     */
    private static ?array $timezoneMap = null;

    public static function apply(): void
    {
        $currentTimezone = (string) config('app.timezone', 'UTC');
        $currentLocale = (string) config('app.locale', 'en');

        $timezone = self::resolveTimezone($currentTimezone);
        $locale = self::resolveLocale($currentLocale);

        config(['app.timezone' => $timezone]);
        @date_default_timezone_set($timezone);

        if ($locale !== '') {
            app()->setLocale($locale);
        }
    }

    private static function resolveTimezone(string $fallback): string
    {
        try {
            $candidate = trim(WebsiteSettings::localizationTimezone());
        } catch (\Throwable) {
            $candidate = '';
        }

        if ($candidate === '' || ! self::isValidTimezone($candidate)) {
            return self::isValidTimezone($fallback) ? $fallback : 'UTC';
        }

        return $candidate;
    }

    private static function resolveLocale(string $fallback): string
    {
        try {
            $candidate = trim(WebsiteSettings::localizationLanguage());
        } catch (\Throwable) {
            $candidate = '';
        }

        return $candidate !== '' ? $candidate : $fallback;
    }

    private static function isValidTimezone(string $timezone): bool
    {
        if (self::$timezoneMap === null) {
            self::$timezoneMap = array_fill_keys(timezone_identifiers_list(), true);
        }

        return isset(self::$timezoneMap[$timezone]);
    }
}
