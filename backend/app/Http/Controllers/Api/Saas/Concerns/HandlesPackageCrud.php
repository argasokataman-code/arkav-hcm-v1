<?php

namespace App\Http\Controllers\Api\Saas\Concerns;

use App\Models\FeatureClassification;
use App\Models\Package;
use App\Models\PackageAddon;
use App\Models\PackageFeature;
use App\Services\PackageFeatureCatalogRuntimeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

trait HandlesPackageCrud
{    public function __construct(
        private readonly PackageFeatureCatalogRuntimeService $featureCatalogRuntimeService
    ) {}

    /**
     * GET /v1/saas/packages/feature-catalog
     * Return backend-driven feature catalog for packages UI.
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

        $items = collect($packages->items())->map(fn($pkg) => [
            'id' => $pkg->uuid,
            'code' => $pkg->code,
            'name' => $pkg->name,
            'description' => $pkg->description,
            'monthlyPrice' => (float)$pkg->monthly_price,
            'yearlyPrice' => (float)$pkg->yearly_price,
            'billingUnit' => $pkg->billing_unit,
            'status' => $pkg->status,
            'isGlobalAdminOnly' => (bool) $pkg->is_global_admin_only,
            'color' => $pkg->color,
            'sortOrder' => $pkg->sort_order,
            'activeSubscriptionsCount' => (int) ($pkg->active_subscriptions_count ?? 0),
            'totalSubscriptionsCount' => (int) ($pkg->total_subscriptions_count ?? 0),
            'purchasableAddonsCount' => (int) ($pkg->purchasable_addons_count ?? 0),
            'features' => $pkg->features->map(fn($f) => [
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
                'monthlyPrice' => (float)$package->monthly_price,
                'yearlyPrice' => (float)$package->yearly_price,
                'billingUnit' => $package->billing_unit,
                'status' => $package->status,
                'isGlobalAdminOnly' => (bool) $package->is_global_admin_only,
                'color' => $package->color,
                'sortOrder' => $package->sort_order,
                'activeSubscriptionsCount' => (int) ($package->active_subscriptions_count ?? 0),
                'totalSubscriptionsCount' => (int) ($package->total_subscriptions_count ?? 0),
                'features' => $package->features->map(fn($f) => [
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
        if (!$this->isHcmAdmin($request)) {
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
                'monthlyPrice' => (float)$package->monthly_price,
                'yearlyPrice' => (float)$package->yearly_price,
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
        if (!$this->isHcmAdmin($request)) {
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
                'monthlyPrice' => (float)$package->monthly_price,
                'yearlyPrice' => (float)$package->yearly_price,
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
        if (!$this->isHcmAdmin($request)) {
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
}
