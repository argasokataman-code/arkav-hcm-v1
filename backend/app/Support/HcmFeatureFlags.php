<?php

namespace App\Support;

use App\Models\CompanySetting;

/**
 * Resolver feature flag HCM per-tenant.
 *
 * Urutan prioritas:
 *   1. `company_settings.value` untuk (company_id, key) — bila baris ada.
 *   2. `config('hcm.{key}')` sebagai default global.
 *   3. Argumen `$default` bila config tidak menyediakan nilai.
 */
final class HcmFeatureFlags
{
    /**
     * @template T
     *
     * @param  T  $default
     * @return T|mixed
     */
    public static function forCompany(?int $companyId, string $key, mixed $default = null): mixed
    {
        // Config value (global) takes precedence over the caller-supplied
        // default — default hanya dipakai bila config tidak menyediakan nilai.
        $configValue = config('hcm.'.$key, null);
        $resolvedDefault = $configValue ?? $default;

        if ($companyId === null || $companyId <= 0) {
            return $resolvedDefault;
        }

        $row = CompanySetting::query()
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->first();

        if ($row === null) {
            return $resolvedDefault;
        }

        return self::castValue($row->value, $row->type ?? null, $resolvedDefault);
    }

    private static function castValue(mixed $raw, ?string $type, mixed $default): mixed
    {
        if ($raw === null) {
            return $default;
        }

        if (is_bool($default) || $type === 'bool' || $type === 'boolean') {
            if (is_bool($raw)) {
                return $raw;
            }
            $str = strtolower(trim((string) $raw));

            return in_array($str, ['1', 'true', 'yes', 'on'], true);
        }

        if (is_int($default) || $type === 'int' || $type === 'integer') {
            return (int) $raw;
        }

        if (is_float($default) || $type === 'float' || $type === 'double') {
            return (float) $raw;
        }

        return (string) $raw;
    }
}
