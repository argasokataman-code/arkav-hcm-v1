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

trait HandlesPackageAddons
{    public function addons(Request $request): JsonResponse
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
    public function storeAddon(Request $request): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
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
                    'code'    => 'FEATURE_CODE_NAMESPACE_CONFLICT',
                    'message' => 'Add-on code "' . $validated['code'] . '" already exists in package feature catalog. Use a dedicated add-on SKU code to avoid baseline/add-on double entries.',
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
        if (!$this->isHcmAdmin($request)) {
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
        if (!$this->isHcmAdmin($request)) {
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
