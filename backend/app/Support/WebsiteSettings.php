<?php

namespace App\Support;

use App\Models\Setting;

class WebsiteSettings
{
    private const PREFIX_DEFAULTS = [
        'employee' => 'EMP-',
        'clients' => 'CLI-',
        'invoice' => 'INV-',
        'tickets' => 'TIC-',
        'candidate' => 'CND-',
        'job' => 'JOB-',
        'referral' => 'REF-',
        'contract' => 'CNT-',
        'department' => 'DPT-',
        'leave' => 'LVE-',
        'assets' => 'AST-',
    ];

    private const BUSINESS_DEFAULTS = [
        'company_name' => 'Arkav - Human Capital Management',
        'email' => null,
        'phone' => null,
        'fax' => null,
        'website' => null,
        'address' => null,
        'country' => null,
        'state' => null,
        'city' => null,
        'postal_code' => null,
    ];

    private const LOCALIZATION_DEFAULTS = [
        'language_switcher' => '0',
        'date_format' => 'd M Y',
        'time_format' => '24',
        'financial_year' => null,
        'fy_start_month' => null,
        'currency_code' => 'IDR',
        'currency_symbol' => 'Rp',
        'currency_position' => 'prefix',
        'decimal_separator' => ',',
        'thousand_separator' => '.',
        'countries_restriction' => 'allow_all',
        'allowed_files' => 'jpg,jpeg,png',
        'max_file_size_mb' => '5000',
    ];

    private const BRANDING_FIELDS = [
        'white_logo',
        'dark_logo',
        'white_mini_logo',
        'dark_mini_logo',
        'favicon',
        'apple_icon',
    ];

    private const DEFAULT_LOCALE = 'en';
    private const DEFAULT_TIMEZONE = 'UTC';

    public static function prefixEmployee(): string
    {
        return self::prefix('employee');
    }

    public static function prefixInvoice(): string
    {
        return self::prefix('invoice');
    }

    public static function prefixAssets(): string
    {
        return self::prefix('assets');
    }

    public static function prefix(string $key): string
    {
        $normalizedKey = strtolower(trim($key));
        $fallback = self::PREFIX_DEFAULTS[$normalizedKey] ?? 'GEN-';

        return self::normalizedPrefix(Setting::get('prefix_'.$normalizedKey, $fallback), $fallback);
    }

    /**
     * @return array<string, string>
     */
    public static function allPrefixSettings(): array
    {
        $result = [];

        foreach (self::PREFIX_DEFAULTS as $key => $fallback) {
            $result['prefix_'.$key] = self::normalizedPrefix(Setting::get('prefix_'.$key, $fallback), $fallback);
        }

        return $result;
    }

    public static function businessCompanyName(): string
    {
        return self::businessValue('company_name', (string) self::BUSINESS_DEFAULTS['company_name']) ?? (string) self::BUSINESS_DEFAULTS['company_name'];
    }

    public static function businessEmail(): ?string
    {
        return self::businessValue('email');
    }

    public static function businessPhone(): ?string
    {
        return self::businessValue('phone');
    }

    /**
     * @return array<string, string|null>
     */
    public static function allBusinessSettings(): array
    {
        $result = [];

        foreach (self::BUSINESS_DEFAULTS as $key => $fallback) {
            $result['business_'.$key] = self::businessValue($key, $fallback);
        }

        return $result;
    }

    /**
     * @return array<string, string|null>
     */
    public static function allBusinessBrandingPaths(): array
    {
        $result = [];

        foreach (self::BRANDING_FIELDS as $field) {
            $result['business_'.$field.'_path'] = self::brandingPath($field);
        }

        return $result;
    }

    public static function brandingPath(string $field): ?string
    {
        $normalized = strtolower(trim($field));
        if (!in_array($normalized, self::BRANDING_FIELDS, true)) {
            return null;
        }

        $stored = Setting::get('business_'.$normalized.'_path');
        if (!is_string($stored)) {
            return null;
        }

        $stored = trim($stored);

        return $stored !== '' ? $stored : null;
    }

    public static function brandingUrl(string $field, string $fallbackUrl): string
    {
        $path = self::brandingPath($field);

        return $path ? asset('storage/'.$path) : $fallbackUrl;
    }

    public static function localizationLanguage(): string
    {
        $fallback = (string) config('app.locale', self::DEFAULT_LOCALE);
        $locale = (string) (Setting::get('localization_language', $fallback) ?? $fallback);
        $locale = trim($locale);

        return $locale !== '' ? $locale : $fallback;
    }

    public static function localizationTimezone(): string
    {
        $fallback = (string) config('app.timezone', self::DEFAULT_TIMEZONE);

        $timezone = (string) (
            Setting::get('localization_timezone', Setting::get('locale_timezone', $fallback))
            ?? $fallback
        );
        $timezone = trim($timezone);

        return $timezone !== '' ? $timezone : $fallback;
    }

    public static function localizationDateFormat(): string
    {
        $format = (string) (Setting::get('localization_date_format', (string) self::LOCALIZATION_DEFAULTS['date_format']) ?? (string) self::LOCALIZATION_DEFAULTS['date_format']);
        $format = trim($format);

        return $format !== '' ? $format : (string) self::LOCALIZATION_DEFAULTS['date_format'];
    }

    public static function localizationTimeFormat(): string
    {
        $format = (string) (Setting::get('localization_time_format', (string) self::LOCALIZATION_DEFAULTS['time_format']) ?? (string) self::LOCALIZATION_DEFAULTS['time_format']);
        $format = trim($format);

        return $format !== '' ? $format : (string) self::LOCALIZATION_DEFAULTS['time_format'];
    }

    /**
     * @return array<string, string>
     */
    public static function allLocalizationSettings(): array
    {
        $result = [
            'localization_language' => self::localizationLanguage(),
            'localization_timezone' => self::localizationTimezone(),
            'locale_timezone' => self::localizationTimezone(),
            'localization_date_format' => self::localizationDateFormat(),
            'localization_time_format' => self::localizationTimeFormat(),
        ];

        foreach (self::LOCALIZATION_DEFAULTS as $key => $fallback) {
            if (in_array($key, ['date_format', 'time_format'], true)) {
                continue;
            }

            $value = Setting::get('localization_'.$key, $fallback);
            $value = is_scalar($value) || $value === null ? $value : $fallback;
            $result['localization_'.$key] = $value === null ? '' : trim((string) $value);
        }

        return $result;
    }

    private static function normalizedPrefix(mixed $value, string $fallback): string
    {
        $prefix = strtoupper(trim((string) ($value ?? $fallback)));

        return $prefix !== '' ? $prefix : $fallback;
    }

    private static function businessValue(string $key, mixed $fallback = null): ?string
    {
        $value = Setting::get('business_'.$key, $fallback);
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }
}