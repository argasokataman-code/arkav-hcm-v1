<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageAddon;
use App\Models\PackageFeature;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PackageController extends Controller
{
    /**
     * GET /v1/saas/packages
     * List all packages (public endpoint, no auth required)
     */
    public function index(Request $request): JsonResponse
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

        $query = Package::query();
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
            'color' => $pkg->color,
            'sortOrder' => $pkg->sort_order,
            'activeSubscriptionsCount' => (int) ($pkg->active_subscriptions_count ?? 0),
            'totalSubscriptionsCount' => (int) ($pkg->total_subscriptions_count ?? 0),
            'features' => $pkg->features->map(fn($f) => [
                'id' => $f->id,
                'code' => $f->feature_code,
                'name' => $f->feature_name,
                'limit' => $f->limit,
                'isIncluded' => $f->isIncluded(),
                'isUnlimited' => $f->isUnlimited(),
            ])->toArray(),
            'createdAt' => $pkg->created_at->toIso8601String(),
        ])->values()->toArray();

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'total' => $packages->total(),
                'per_page' => $packages->perPage(),
                'current_page' => $packages->currentPage(),
                'last_page' => $packages->lastPage(),
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

        $query = PackageAddon::query();
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        } elseif ($status === '' || $status === null) {
            $query->where('status', 'active');
        }

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('unit_name', 'like', '%'.$search.'%');
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
    public function show(Package $package): JsonResponse
    {
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
                'color' => $package->color,
                'sortOrder' => $package->sort_order,
                'activeSubscriptionsCount' => (int) ($package->active_subscriptions_count ?? 0),
                'totalSubscriptionsCount' => (int) ($package->total_subscriptions_count ?? 0),
                'features' => $package->features->map(fn($f) => [
                    'id' => $f->id,
                    'code' => $f->feature_code,
                    'name' => $f->feature_name,
                    'limit' => $f->limit,
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
    public function getFeatures(Package $package): JsonResponse
    {
        $features = $package->features()->get();

        return response()->json([
            'success' => true,
            'data' => $features->map(fn($f) => [
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
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'feature_code' => 'required|string|max:50',
            'feature_name' => 'required|string|max:100',
            'limit' => 'nullable|integer',
        ]);

        $feature = $package->features()->create($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $feature->id,
                'code' => $feature->feature_code,
                'name' => $feature->feature_name,
                'limit' => $feature->limit,
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
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'feature_name' => 'sometimes|string|max:100',
            'limit' => 'nullable|integer',
        ]);

        $feature->update($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $feature->id,
                'code' => $feature->feature_code,
                'name' => $feature->feature_name,
                'limit' => $feature->limit,
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
        if (!$this->isHcmAdmin($request)) {
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
     * POST /v1/saas/package-addons
     * Create new package add-on (super admin only)
     */
    public function storeAddon(Request $request): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $validated = $request->validate([
            'code' => 'required|string|unique:package_addons|max:100',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'price_per_unit' => 'required|numeric|min:0',
            'unit_name' => 'required|string|max:100',
            'status' => 'sometimes|in:active,inactive',
        ]);

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
        if (!$this->isHcmAdmin($request)) {
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

        $validated = $request->validate([
            'code' => 'sometimes|string|unique:package_addons,code,' . $addonModel->id . '|max:100',
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'price_per_unit' => 'sometimes|numeric|min:0',
            'unit_name' => 'sometimes|string|max:100',
            'status' => 'sometimes|in:active,inactive',
        ]);

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
        if (!$this->isHcmAdmin($request)) {
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
        if (Str::isUuid($identifier)) {
            return $query->where('uuid', $identifier)->first();
        }

        if (ctype_digit($identifier)) {
            return $query->whereKey((int) $identifier)->first();
        }

        return null;
    }
}
