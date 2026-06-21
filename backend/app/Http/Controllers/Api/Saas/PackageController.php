<?php

namespace App\Http\Controllers\Api\Saas;

use App\Http\Controllers\Controller;
use App\Models\FeatureClassification;
use App\Models\Package;
use App\Models\PackageAddon;
use App\Models\PackageFeature;
use App\Services\PackageFeatureCatalogRuntimeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PackageController extends Controller
{
    public function __construct(
        private readonly PackageFeatureCatalogRuntimeService $featureCatalogRuntimeService
    ) {}

    /**
     * GET /v1/saas/packages/feature-catalog
     * Return backend-driven feature catalog for packages UI.
     */
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
    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->get('status', 'active');
        $search = trim((string) $request->get('search', ''));
        $isGlobalAdmin = $this->isHcmAdmin($request);
        $perPage = (int) $request->get('per_page', 15);
        if ($perPage < 1) {
            $perPage = 15;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $query = Package::query();
        if (! $isGlobalAdmin) {
            $query->where('is_global_admin_only', false);
        }
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        } elseif ($status === '' || $status === null) {
            $query->where('status', 'active');
        }

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        $packages = $query
            ->orderBy('sort_order')
            ->orderBy('code')
            ->with('features')
            ->withCount([
                'subscriptions as active_subscriptions_count' => function (Builder $subQuery): void {
                    $subQuery->whereIn('status', ['active', 'trial']);
                },
                'subscriptions as total_subscriptions_count',
                'availableAddons as purchasable_addons_count',
            ])
            ->paginate($perPage);

        $items = collect($packages->items())->map(fn ($pkg) => [
            'id' => $pkg->uuid,
            'code' => $pkg->code,
            'name' => $pkg->name,
            'description' => $pkg->description,
            'monthlyPrice' => (float) $pkg->monthly_price,
            'yearlyPrice' => (float) $pkg->yearly_price,
            'billingUnit' => $pkg->billing_unit,
            'status' => $pkg->status,
            'isGlobalAdminOnly' => (bool) $pkg->is_global_admin_only,
            'color' => $pkg->color,
            'sortOrder' => $pkg->sort_order,
            'activeSubscriptionsCount' => (int) ($pkg->active_subscriptions_count ?? 0),
            'totalSubscriptionsCount' => (int) ($pkg->total_subscriptions_count ?? 0),
            'purchasableAddonsCount' => (int) ($pkg->purchasable_addons_count ?? 0),
            'features' => $pkg->features->map(fn ($f) => [
                'id' => $f->id,
                'code' => $f->feature_code,
                'name' => $f->feature_name,
                'limit' => $f->limit,
                'tier' => $f->tier ?? 'core',
                'isIncluded' => $f->isIncluded(),
                'isUnlimited' => $f->isUnlimited(),
            ])->toArray(),
            'createdAt' => $pkg->created_at->toIso8601String(),
        ])->values()->toArray();

        // Tier map: { feature_code: 'mvp'|'addon' } from DB classifications.
        // Loaded once per request and appended to meta so the UI can colour-code
        // Core vs Addon badges without a separate featureCatalog call.
        $tierByCode = FeatureClassification::all(['feature_code', 'tier'])
            ->pluck('tier', 'feature_code')
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'total' => $packages->total(),
                'per_page' => $packages->perPage(),
                'current_page' => $packages->currentPage(),
                'last_page' => $packages->lastPage(),
            ],
            'meta' => [
                'tier_by_code' => $tierByCode,
            ],
        ]);
    }

    /**
     * GET /v1/saas/package-addons
     * List all add-ons (public endpoint, no auth required beyond api.token)
     */
    public function addons(Request $request): JsonResponse
    {
        $status = (string) $request->get('status', 'active');
        $search = trim((string) $request->get('search', ''));
        $perPage = (int) $request->get('per_page', 15);
        if ($perPage < 1) {
            $perPage = 15;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        // When package_uuid is provided, only return add-ons assigned to that
        // package by the global admin (via package_addon_assignments table).
        $packageUuid = trim((string) $request->get('package_uuid', ''));

        $query = PackageAddon::query();
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        } elseif ($status === '' || $status === null) {
            $query->where('status', 'active');
        }

        if ($packageUuid !== '') {
            $query->whereExists(function ($sub) use ($packageUuid) {
                $sub->selectRaw('1')
                    ->from('package_addon_assignments')
                    ->whereColumn('package_addon_assignments.package_addon_id', 'package_addons.id')
                    ->where('package_addon_assignments.package_uuid', $packageUuid);
            });
        }

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        $addons = $query
            ->orderBy('code')
            ->orderBy('id')
            ->paginate($perPage);

        $items = collect($addons->items())->map(fn (PackageAddon $addon) => $this->formatAddon($addon))->values()->toArray();

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'total' => $addons->total(),
                'per_page' => $addons->perPage(),
                'current_page' => $addons->currentPage(),
                'last_page' => $addons->lastPage(),
            ],
        ]);
    }

    /**
     * GET /v1/saas/package-addons/{addon}
     * Get add-on details.
     */
    public function showAddon(Request $request, string $addon): JsonResponse
    {
        $addonModel = $this->resolveAddonByIdentifier($addon);
        if (! $addonModel) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Package addon not found.'],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatAddon($addonModel, true),
        ]);
    }

    /**
     * GET /v1/saas/packages/{id}
     * Get package details with features
     */
    public function show(Request $request, Package $package): JsonResponse
    {
        if ((bool) $package->is_global_admin_only && ! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Package not found.'],
            ], 404);
        }

        $package->load('features')
            ->loadCount([
                'subscriptions as active_subscriptions_count' => function (Builder $subQuery): void {
                    $subQuery->whereIn('status', ['active', 'trial']);
                },
                'subscriptions as total_subscriptions_count',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $package->uuid,
                'code' => $package->code,
                'name' => $package->name,
                'description' => $package->description,
                'monthlyPrice' => (float) $package->monthly_price,
                'yearlyPrice' => (float) $package->yearly_price,
                'billingUnit' => $package->billing_unit,
                'status' => $package->status,
                'isGlobalAdminOnly' => (bool) $package->is_global_admin_only,
                'color' => $package->color,
                'sortOrder' => $package->sort_order,
                'activeSubscriptionsCount' => (int) ($package->active_subscriptions_count ?? 0),
                'totalSubscriptionsCount' => (int) ($package->total_subscriptions_count ?? 0),
                'features' => $package->features->map(fn ($f) => [
                    'id' => $f->id,
                    'code' => $f->feature_code,
                    'name' => $f->feature_name,
                    'limit' => $f->limit,
                    'tier' => $f->tier ?? 'core',
                    'isIncluded' => $f->isIncluded(),
                    'isUnlimited' => $f->isUnlimited(),
                ])->toArray(),
                'createdAt' => $package->created_at->toIso8601String(),
                'updatedAt' => $package->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /v1/saas/packages
     * Create new package (super admin only)
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', Rule::unique('packages', 'code'), 'max:50'],
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'monthly_price' => 'required|numeric|min:0',
            'yearly_price' => 'required|numeric|min:0',
            'billing_unit' => 'required|in:user,company,flat',
            'status' => 'sometimes|in:active,inactive,archived',
            'is_global_admin_only' => 'sometimes|boolean',
            'color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $package = Package::create($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $package->uuid,
                'code' => $package->code,
                'name' => $package->name,
                'description' => $package->description,
                'monthlyPrice' => (float) $package->monthly_price,
                'yearlyPrice' => (float) $package->yearly_price,
                'billingUnit' => $package->billing_unit,
                'isGlobalAdminOnly' => (bool) $package->is_global_admin_only,
                'color' => $package->color,
                'sortOrder' => $package->sort_order,
                'createdAt' => $package->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * PUT /v1/saas/packages/{id}
     * Update package (super admin only)
     */
    public function update(Request $request, Package $package): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'code' => ['sometimes', 'string', Rule::unique('packages', 'code')->ignore($package->uuid, 'uuid'), 'max:50'],
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'monthly_price' => 'sometimes|numeric|min:0',
            'yearly_price' => 'sometimes|numeric|min:0',
            'billing_unit' => 'sometimes|in:user,company,flat',
            'status' => 'sometimes|in:active,inactive,archived',
            'is_global_admin_only' => 'sometimes|boolean',
            'color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $package->update($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $package->uuid,
                'code' => $package->code,
                'name' => $package->name,
                'description' => $package->description,
                'monthlyPrice' => (float) $package->monthly_price,
                'yearlyPrice' => (float) $package->yearly_price,
                'billingUnit' => $package->billing_unit,
                'status' => $package->status,
                'isGlobalAdminOnly' => (bool) $package->is_global_admin_only,
                'color' => $package->color,
                'sortOrder' => $package->sort_order,
                'updatedAt' => $package->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * DELETE /v1/saas/packages/{id}
     * Delete package (super admin only)
     */
    public function destroy(Request $request, Package $package): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        if ($package->subscriptions()->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PACKAGE_IN_USE',
                    'message' => 'Package cannot be deleted while subscription history still references it.',
                ],
            ], 422);
        }

        $package->delete();

        return response()->json([
            'success' => true,
            'message' => 'Package deleted successfully.',
        ]);
    }

    /**
     * GET /v1/saas/packages/{id}/features
     * Get features for a package
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
    public function storeAddon(Request $request): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        // If addons are managed externally (runtime), prevent create operations
        if (config('saas_package_feature_catalog.addon_source') === 'runtime') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ADDONS_MANAGED_EXTERNALLY',
                    'message' => 'Add-ons are managed via runtime/centralized feature catalog and cannot be created via this endpoint.',
                ],
            ], 403);
        }

        $validated = $request->validate([
            'code' => 'required|string|unique:package_addons|max:100',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'price_per_unit' => 'required|numeric|min:0',
            'unit_name' => 'nullable|string|max:100',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $validated['unit_name'] = $validated['unit_name'] ?? 'tenant / month';

        if ($this->isReservedFeatureCode((string) $validated['code'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FEATURE_CODE_NAMESPACE_CONFLICT',
                    'message' => 'Add-on code "'.$validated['code'].'" already exists in package feature catalog. Use a dedicated add-on SKU code to avoid baseline/add-on double entries.',
                ],
            ], 422);
        }

        $addon = PackageAddon::create($validated);

        return response()->json([
            'success' => true,
            'data' => $this->formatAddon($addon, true),
        ], 201);
    }

    /**
     * PUT /v1/saas/package-addons/{addon}
     * Update package add-on (super admin only)
     */
    public function updateAddon(Request $request, string $addon): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        // If addons are managed externally (runtime), prevent update operations
        if (config('saas_package_feature_catalog.addon_source') === 'runtime') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ADDONS_MANAGED_EXTERNALLY',
                    'message' => 'Add-ons are managed via runtime/centralized feature catalog and cannot be updated via this endpoint.',
                ],
            ], 403);
        }

        $addonModel = $this->resolveAddonByIdentifier($addon);
        if (! $addonModel) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Package addon not found.'],
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'price_per_unit' => 'sometimes|numeric|min:0',
            'unit_name' => 'sometimes|string|max:100',
            'status' => 'sometimes|in:active,inactive',
        ]);

        // code is intentionally excluded — it is a feature identifier bundled
        // into package_features and used by the runtime access-gate. Changing it
        // would silently revoke access for tenants on packages that include it.

        $addonModel->update($validated);

        return response()->json([
            'success' => true,
            'data' => $this->formatAddon($addonModel, true),
        ]);
    }

    /**
     * DELETE /v1/saas/package-addons/{addon}
     * Delete package add-on (super admin only)
     */
    public function destroyAddon(Request $request, string $addon): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        // If addons are managed externally (runtime), prevent delete operations
        if (config('saas_package_feature_catalog.addon_source') === 'runtime') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ADDONS_MANAGED_EXTERNALLY',
                    'message' => 'Add-ons are managed via runtime/centralized feature catalog and cannot be deleted via this endpoint.',
                ],
            ], 403);
        }

        $addonModel = $this->resolveAddonByIdentifier($addon);
        if (! $addonModel) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Package addon not found.'],
            ], 404);
        }

        $addonModel->delete();

        return response()->json([
            'success' => true,
            'message' => 'Package add-on deleted successfully.',
        ]);
    }

    /**
     * Check if user is HCM admin
     */
    private function isHcmAdmin(Request $request): bool
    {
        $user = $request->user();

        return $user ? $user->isGlobalHcmAdmin() : false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Package Add-on Assignments
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /v1/saas/packages/{package}/addon-assignments
     * List all add-ons assigned to a package.
     */
    public function getAddonAssignments(Request $request, Package $package): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $assigned = $package->availableAddons()->orderBy('code')->get();
        $items = $assigned->map(fn (PackageAddon $addon) => $this->formatAddon($addon))->values()->toArray();

        return response()->json(['success' => true, 'data' => $items]);
    }

    /**
     * POST /v1/saas/packages/{package}/addon-assignments
     * Assign an add-on to a package. Body: { "addon_id": <id|uuid|code> }
     */
    public function addAddonAssignment(Request $request, Package $package): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate(['addon_id' => 'required|string']);
        $addon = $this->resolveAddonByIdentifier((string) $validated['addon_id']);

        if (! $addon) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Package addon not found.'],
            ], 404);
        }

        // syncWithoutDetaching ensures idempotent — no error if already assigned
        $package->availableAddons()->syncWithoutDetaching([$addon->id]);

        return response()->json([
            'success' => true,
            'message' => 'Add-on assigned to package.',
            'data' => $this->formatAddon($addon),
        ]);
    }

    /**
     * DELETE /v1/saas/packages/{package}/addon-assignments/{addon}
     * Remove an add-on assignment from a package.
     */
    public function removeAddonAssignment(Request $request, Package $package, string $addon): JsonResponse
    {
        if (! $this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $addonModel = $this->resolveAddonByIdentifier($addon);

        if (! $addonModel) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Package addon not found.'],
            ], 404);
        }

        $package->availableAddons()->detach($addonModel->id);

        return response()->json([
            'success' => true,
            'message' => 'Add-on assignment removed from package.',
        ]);
    }

    /**
     * Any feature code namespace is reserved for package feature catalog.
     * Add-on SKU code must not collide with these codes.
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

    private function formatAddon(PackageAddon $addon, bool $includeUpdatedAt = false): array
    {
        $data = [
            'id' => $addon->id,
            'code' => $addon->code,
            'name' => $addon->name,
            'description' => $addon->description,
            'pricePerUnit' => (float) $addon->price_per_unit,
            'unitName' => $addon->unit_name,
            'status' => $addon->status,
            'createdAt' => $addon->created_at?->toIso8601String(),
        ];

        if ($includeUpdatedAt) {
            $data['updatedAt'] = $addon->updated_at?->toIso8601String();
        }

        return $data;
    }

    private function resolveAddonByIdentifier(string $identifier): ?PackageAddon
    {
        $query = PackageAddon::query();
        $normalized = trim((string) $identifier);
        if ($normalized === '') {
            return null;
        }

        // UUID lookup
        if (Str::isUuid($normalized)) {
            return $query->where('uuid', $normalized)->first();
        }

        // Numeric id lookup
        if (ctype_digit($normalized)) {
            return $query->whereKey((int) $normalized)->first();
        }

        // Fallback: allow lookup by addon `code` (friendly identifier)
        // This makes endpoints like /v1/saas/package-addons/faq resolve correctly
        return $query->where('code', $normalized)->first();
    }
}
