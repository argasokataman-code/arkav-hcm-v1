<?php

namespace App\Http\Controllers\Api\Asset;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Services\AssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HcmAssetCategoryController extends Controller
{
    use ChecksPermissions;

    public function __construct(private readonly AssetService $assetService) {}

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'asset.view')) {
            return $response;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if (! $this->assetService->companyHasFeature($companyId, AssetService::FEATURE_ASSET_MANAGEMENT)) {
            return $this->errorResponse('FEATURE_DISABLED', 'Asset Management is not enabled for this company.', 403);
        }

        $rows = AssetCategory::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get()
            ->map(fn (AssetCategory $category) => $this->formatCategory($category))
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'asset.manage')) {
            return $response;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_CONTEXT_REQUIRED', 'Active company context is required.', 422);
        }

        if (! $this->assetService->companyHasFeature($companyId, AssetService::FEATURE_ASSET_MANAGEMENT)) {
            return $this->errorResponse('FEATURE_DISABLED', 'Asset Management is not enabled for this company.', 403);
        }

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:150', Rule::unique('asset_categories')->where(fn ($q) => $q->where('company_id', $companyId))],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = AssetCategory::query()->create([
            'company_id' => $companyId,
            'code' => strtoupper(Str::slug($validated['code'] ?? $validated['name'], '_')),
            'name' => trim((string) $validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return response()->json(['success' => true, 'data' => $this->formatCategory($category)], 201);
    }

    public function update(Request $request, AssetCategory $category): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'asset.manage')) {
            return $response;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId || (int) $category->company_id !== (int) $companyId) {
            return $this->errorResponse('NOT_FOUND', 'Asset category not found.', 404);
        }

        if (! $this->assetService->companyHasFeature($companyId, AssetService::FEATURE_ASSET_MANAGEMENT)) {
            return $this->errorResponse('FEATURE_DISABLED', 'Asset Management is not enabled for this company.', 403);
        }

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:80'],
            'name' => ['sometimes', 'required', 'string', 'max:150', Rule::unique('asset_categories')->where(fn ($q) => $q->where('company_id', $companyId))->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('code', $validated)) {
            $category->code = strtoupper(Str::slug((string) $validated['code'], '_'));
        }
        if (array_key_exists('name', $validated)) {
            $category->name = trim((string) $validated['name']);
        }
        if (array_key_exists('description', $validated)) {
            $category->description = $validated['description'] !== null ? trim((string) $validated['description']) : null;
        }
        if (array_key_exists('is_active', $validated)) {
            $category->is_active = (bool) $validated['is_active'];
        }

        $category->save();

        return response()->json(['success' => true, 'data' => $this->formatCategory($category)]);
    }

    public function destroy(Request $request, AssetCategory $category): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'asset.manage')) {
            return $response;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId || (int) $category->company_id !== (int) $companyId) {
            return $this->errorResponse('NOT_FOUND', 'Asset category not found.', 404);
        }

        if (! $this->assetService->companyHasFeature($companyId, AssetService::FEATURE_ASSET_MANAGEMENT)) {
            return $this->errorResponse('FEATURE_DISABLED', 'Asset Management is not enabled for this company.', 403);
        }

        if ($category->assets()->exists()) {
            return $this->errorResponse('CATEGORY_IN_USE', 'Asset category cannot be deleted while it has assets.', 422);
        }

        $category->delete();

        return response()->json(['success' => true]);
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    private function errorResponse(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }

    private function formatCategory(AssetCategory $category): array
    {
        return [
            'id' => $category->id,
            'companyId' => $category->company_id,
            'code' => $category->code,
            'name' => $category->name,
            'description' => $category->description,
            'isActive' => (bool) $category->is_active,
            'assetsCount' => $category->assets()->count(),
            'createdAt' => $category->created_at?->toIso8601String(),
            'updatedAt' => $category->updated_at?->toIso8601String(),
        ];
    }
}
