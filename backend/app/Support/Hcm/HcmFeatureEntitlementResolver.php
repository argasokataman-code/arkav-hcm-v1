<?php

namespace App\Support\Hcm;

use App\Models\Subscription;

final class HcmFeatureEntitlementResolver
{
    /**
     * @var array<int, array<string, bool>>
     */
    private static array $featureMapCacheByCompany = [];

    /**
     * @return array<string, bool>
     */
    public static function activeFeatureMapForCompany(?int $companyId): array
    {
        if ($companyId === null || $companyId <= 0) {
            return [];
        }

        if (isset(self::$featureMapCacheByCompany[$companyId])) {
            return self::$featureMapCacheByCompany[$companyId];
        }

        $subscription = Subscription::query()
            ->with(['package.features'])
            ->where('company_id', $companyId)
            ->whereIn('status', ['active', 'trial'])
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at')
            ->first();

        $featureCodes = $subscription?->package?->features
            ?->pluck('feature_code')
            ->filter()
            ->unique()
            ->values()
            ->all() ?? [];

        $featureMap = [];
        foreach ($featureCodes as $featureCode) {
            $featureMap[strtolower(trim((string) $featureCode))] = true;
        }

        return self::$featureMapCacheByCompany[$companyId] = $featureMap;
    }

    /**
     * @param  array<int, string>  $featureCodes
     */
    public static function companyHasAnyFeature(?int $companyId, array $featureCodes): bool
    {
        $activeMap = self::activeFeatureMapForCompany($companyId);
        if ($activeMap === []) {
            return false;
        }

        foreach ($featureCodes as $featureCode) {
            $normalized = strtolower(trim((string) $featureCode));
            if ($normalized !== '' && isset($activeMap[$normalized])) {
                return true;
            }
        }

        return false;
    }

    public static function isPermissionAllowedForCompany(string $permissionCode, ?int $companyId): bool
    {
        $normalizedPermission = strtolower(trim($permissionCode));
        if ($normalizedPermission === '') {
            return false;
        }

        $alwaysAllowed = array_map(
            static fn (string $code): string => strtolower(trim($code)),
            config('hcm_feature_permission_contract.always_allowed_permissions', [])
        );

        if (in_array($normalizedPermission, $alwaysAllowed, true)) {
            return true;
        }

        /** @var array<string, array<string, array<int, string>>> $rules */
        $rules = config('hcm_feature_permission_contract.permission_rules', []);
        $rule = $rules[$normalizedPermission] ?? null;

        // Backward compatible: if permission is not mapped yet, do not block it.
        if (! is_array($rule)) {
            return true;
        }

        $anyOf = array_map(
            static fn (string $code): string => strtolower(trim($code)),
            $rule['any_of'] ?? []
        );

        if ($anyOf === []) {
            return true;
        }

        return self::companyHasAnyFeature($companyId, $anyOf);
    }

    /**
     * @param  array<int, string>  $permissionCodes
     * @return array<int, string>
     */
    public static function filterPermissionCodesForCompany(array $permissionCodes, ?int $companyId): array
    {
        $out = [];
        foreach ($permissionCodes as $code) {
            $normalized = strtolower(trim((string) $code));
            if ($normalized === '') {
                continue;
            }

            if (self::isPermissionAllowedForCompany($normalized, $companyId)) {
                $out[] = $normalized;
            }
        }

        return array_values(array_unique($out));
    }

    public static function clearCompanyCache(?int $companyId = null): void
    {
        if ($companyId === null || $companyId <= 0) {
            self::$featureMapCacheByCompany = [];

            return;
        }

        unset(self::$featureMapCacheByCompany[$companyId]);
    }
}
