<?php

namespace App\Http\Controllers\Api\Saas\Concerns;

use App\Models\FeatureClassification;
use App\Models\Package;
use App\Models\PackageFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HandlesPackageFeatures
{
    public function featureCatalog(): JsonResponse
    {
        $runtimeCatalog = $this->featureCatalogRuntimeService->build();
        $catalogGroups = collect($runtimeCatalog['groups'] ?? []);
        $tierMapping = $this->buildFeatureTierMapping(
            $catalogGroups,
            $runtimeCatalog['mvp_feature_codes'] ?? []
        );

        $groups = $catalogGroups
            ->filter(fn (mixed $group): bool => is_array($group))
            ->map(fn (array $group): array => $this->normalizeFeatureCatalogGroup($group, $tierMapping['mvp_lookup']))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $groups->all(),
            'meta' => [
                'mvp_feature_codes' => $tierMapping['mvp_feature_codes'],
                'addon_feature_codes' => $tierMapping['addon_feature_codes'],
                'total_feature_codes' => count($tierMapping['all_feature_codes']),
                // expose backend's authoritative addon source so frontend can honor centralized policy
                'addon_source' => config('saas_package_feature_catalog.addon_source', 'db'),
                // include any DB overrides for UI consumption (feature_code => tier)
                'feature_classification_overrides' => $this->readFeatureClassificationOverrides(),
            ],
        ]);
    }

    private function readFeatureClassificationOverrides(): array
    {
        try {
            $rows = FeatureClassification::all(['feature_code', 'tier']);

            return $rows->mapWithKeys(fn ($r) => [trim($r->feature_code) => trim($r->tier)])->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * GET /v1/saas/packages/feature-catalog/healthcheck
     * Return diagnostics comparing route/docs/catalog feature discovery.
     */
    public function featureCatalogHealthcheck(Request $request): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $health = $this->featureCatalogRuntimeService->healthcheck();

        return response()->json([
            'success' => true,
            'data' => $health,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /v1/saas/packages/check-compliance
     * Return package compliance snapshot for selected feature codes.
     */
    public function checkCompliance(Request $request): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $rawFeatureCodes = $request->query('feature_codes', []);
        if (is_string($rawFeatureCodes)) {
            $rawFeatureCodes = explode(',', $rawFeatureCodes);
        }

        if (! is_array($rawFeatureCodes)) {
            $rawFeatureCodes = [];
        }

        $snapshot = $this->featureCatalogRuntimeService->checkPackageCompliance($rawFeatureCodes);

        return response()->json([
            'success' => true,
            'data' => $snapshot,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /v1/saas/packages
     * List all packages (public endpoint, no auth required)
     */
    public function getFeatures(Package $package): JsonResponse
    {
        $features = $package->features()->get();

        return response()->json([
            'success' => true,
            'data' => $features->map(fn ($f) => [
                'id' => $f->id,
                'code' => $f->feature_code,
                'name' => $f->feature_name,
                'limit' => $f->limit,
                'isIncluded' => $f->isIncluded(),
                'isUnlimited' => $f->isUnlimited(),
            ])->toArray(),
        ]);
    }

    /**
     * POST /v1/saas/packages/{id}/features
     * Add feature to package
     */
    public function addFeature(Request $request, Package $package): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'feature_code' => 'required|string|max:50',
            'feature_name' => 'required|string|max:100',
            'limit' => 'nullable|integer',
            'tier' => 'nullable|string|in:core,addon',
        ]);

        $feature = $package->features()->create($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $feature->id,
                'code' => $feature->feature_code,
                'name' => $feature->feature_name,
                'limit' => $feature->limit,
                'tier' => $feature->tier ?? 'core',
                'isIncluded' => $feature->isIncluded(),
                'isUnlimited' => $feature->isUnlimited(),
            ],
        ], 201);
    }

    /**
     * PUT /v1/saas/packages/features/{id}
     * Update feature limit
     */
    public function updateFeature(Request $request, PackageFeature $feature): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'feature_name' => 'sometimes|string|max:100',
            'limit' => 'nullable|integer',
            'tier' => 'nullable|string|in:core,addon',
        ]);

        $feature->update($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $feature->id,
                'code' => $feature->feature_code,
                'name' => $feature->feature_name,
                'limit' => $feature->limit,
                'tier' => $feature->tier ?? 'core',
                'isIncluded' => $feature->isIncluded(),
                'isUnlimited' => $feature->isUnlimited(),
            ],
        ]);
    }

    /**
     * DELETE /v1/saas/packages/features/{id}
     * Remove feature from package
     */
    public function deleteFeature(Request $request, PackageFeature $feature): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $feature->delete();

        return response()->json([
            'success' => true,
            'message' => 'Feature removed successfully.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $group
     * @param  array<string, bool>  $mvpLookup
     * @return array<string, mixed>
     */
    private function normalizeFeatureCatalogGroup(array $group, array $mvpLookup): array
    {
        $module = trim((string) ($group['module'] ?? 'custom'));
        $features = collect($group['features'] ?? [])
            ->filter(fn (mixed $feature): bool => is_array($feature))
            ->map(fn (array $feature): array => $this->normalizeFeatureCatalogItem($feature, $mvpLookup))
            ->filter(fn (array $feature): bool => $feature['code'] !== '')
            ->values()
            ->all();

        return [
            'module' => $module !== '' ? $module : 'custom',
            'title' => trim((string) ($group['title'] ?? 'Custom Features')),
            'description' => trim((string) ($group['description'] ?? '')),
            'features' => $features,
        ];
    }

    /**
     * @param  array<string, mixed>  $feature
     * @param  array<string, bool>  $mvpLookup
     * @return array<string, mixed>
     */
    private function normalizeFeatureCatalogItem(array $feature, array $mvpLookup): array
    {
        $code = trim((string) ($feature['code'] ?? ''));
        $name = trim((string) ($feature['name'] ?? ''));
        $isMvp = $code !== '' && isset($mvpLookup[$code]);

        return [
            'code' => $code,
            'name' => $name !== '' ? $name : Str::headline($code),
            'description' => trim((string) ($feature['description'] ?? '')),
            'tier' => $isMvp ? 'mvp' : 'addon',
            'requiresLimit' => (bool) ($feature['requiresLimit'] ?? false),
            'limitLabel' => isset($feature['limitLabel']) ? trim((string) $feature['limitLabel']) : null,
            'limitPlaceholder' => isset($feature['limitPlaceholder']) ? trim((string) $feature['limitPlaceholder']) : null,
            'limitSuffix' => isset($feature['limitSuffix']) ? trim((string) $feature['limitSuffix']) : null,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $catalogGroups
     * @param  array<int, string>  $runtimeMvpFeatureCodes
     * @return array{
     *   all_feature_codes: array<int, string>,
     *   mvp_feature_codes: array<int, string>,
     *   addon_feature_codes: array<int, string>,
     *   mvp_lookup: array<string, bool>
     * }
     */
    private function buildFeatureTierMapping($catalogGroups, array $runtimeMvpFeatureCodes = []): array
    {
        $allFeatureCodes = $catalogGroups
            ->filter(fn (mixed $group): bool => is_array($group))
            ->flatMap(function (array $group) {
                return collect($group['features'] ?? [])
                    ->filter(fn (mixed $feature): bool => is_array($feature))
                    ->map(fn (array $feature): string => trim((string) ($feature['code'] ?? '')))
                    ->filter(fn (string $code): bool => $code !== '');
            })
            ->unique()
            ->values()
            ->all();

        $allFeatureLookup = array_fill_keys($allFeatureCodes, true);
        $mvpSource = $runtimeMvpFeatureCodes !== []
            ? $runtimeMvpFeatureCodes
            : config('saas_package_feature_catalog.mvp_feature_codes', []);

        $mvpFeatureCodes = collect($mvpSource)
            ->map(fn (mixed $code): string => trim((string) $code))
            ->filter(fn (string $code): bool => $code !== '' && isset($allFeatureLookup[$code]))
            ->unique()
            ->values()
            ->all();

        $mvpLookup = array_fill_keys($mvpFeatureCodes, true);
        $addonFeatureCodes = collect($allFeatureCodes)
            ->filter(fn (string $code): bool => ! isset($mvpLookup[$code]))
            ->values()
            ->all();

        return [
            'all_feature_codes' => $allFeatureCodes,
            'mvp_feature_codes' => $mvpFeatureCodes,
            'addon_feature_codes' => $addonFeatureCodes,
            'mvp_lookup' => $mvpLookup,
        ];
    }

    /**
     * POST /v1/saas/package-addons
     * Create new package add-on (super admin only)
     */
    private function isReservedFeatureCode(string $code): bool
    {
        $normalized = trim($code);
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, $this->reservedFeatureCodes(), true);
    }

    /**
     * @return array<int, string>
     */
    private function reservedFeatureCodes(): array
    {
        $runtimeCodes = collect($this->featureCatalogRuntimeService->build()['all_feature_codes'] ?? [])
            ->map(fn (mixed $code): string => trim((string) $code))
            ->filter(fn (string $code): bool => $code !== '');

        $persistedCodes = DB::table('package_features')
            ->select('feature_code')
            ->distinct()
            ->pluck('feature_code')
            ->map(fn (mixed $code): string => trim((string) $code))
            ->filter(fn (string $code): bool => $code !== '');

        return $runtimeCodes
            ->merge($persistedCodes)
            ->unique()
            ->values()
            ->all();
    }
}
