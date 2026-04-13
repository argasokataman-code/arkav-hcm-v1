<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageAddon;
use App\Models\PackageFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    /**
     * GET /v1/saas/packages
     * List all packages (public endpoint, no auth required)
     */
    public function index(): JsonResponse
    {
        $packages = Package::active()
            ->orderBy('sort_order')
            ->with('features')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $packages->map(fn($pkg) => [
                'id' => $pkg->id,
                'code' => $pkg->code,
                'name' => $pkg->name,
                'description' => $pkg->description,
                'monthlyPrice' => (float)$pkg->monthly_price,
                'yearlyPrice' => (float)$pkg->yearly_price,
                'billingUnit' => $pkg->billing_unit,
                'color' => $pkg->color,
                'sortOrder' => $pkg->sort_order,
                'features' => $pkg->features->map(fn($f) => [
                    'id' => $f->id,
                    'code' => $f->feature_code,
                    'name' => $f->feature_name,
                    'limit' => $f->limit,
                    'isIncluded' => $f->isIncluded(),
                    'isUnlimited' => $f->isUnlimited(),
                ])->toArray(),
                'createdAt' => $pkg->created_at->toIso8601String(),
            ])->toArray(),
        ]);
    }

    /**
     * GET /v1/saas/packages/{id}
     * Get package details with features
     */
    public function show(Package $package): JsonResponse
    {
        $package->load('features');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $package->id,
                'code' => $package->code,
                'name' => $package->name,
                'description' => $package->description,
                'monthlyPrice' => (float)$package->monthly_price,
                'yearlyPrice' => (float)$package->yearly_price,
                'billingUnit' => $package->billing_unit,
                'status' => $package->status,
                'color' => $package->color,
                'sortOrder' => $package->sort_order,
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
            'code' => 'required|string|unique:packages|max:50',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'monthly_price' => 'required|numeric|min:0',
            'yearly_price' => 'required|numeric|min:0',
            'billing_unit' => 'required|in:user,company,flat',
            'color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $package = Package::create($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $package->id,
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
            'code' => 'sometimes|string|unique:packages,code,' . $package->id . '|max:50',
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
                'id' => $package->id,
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
     * Check if user is HCM admin
     */
    private function isHcmAdmin(Request $request): bool
    {
        $user = $request->user();
        if (!$user) return false;

        $adminEmail = config('hcm.admin_email', 'qa.login@example.com');
        if ($user->email === $adminEmail) return true;

        $adminKeywords = ['admin', 'hr', 'lead', 'supervisor', 'owner'];
        $designation = strtolower($user->designation ?? '');
        $team = strtolower($user->team ?? '');

        foreach ($adminKeywords as $keyword) {
            if (str_contains($designation, $keyword) || str_contains($team, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
