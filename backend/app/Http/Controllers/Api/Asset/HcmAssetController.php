<?php

namespace App\Http\Controllers\Api\Asset;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetAttachment;
use App\Models\EmployeeProfile;
use App\Services\AssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HcmAssetController extends Controller
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

        $validated = $request->validate([
            'status' => ['nullable', 'in:available,assigned,maintenance,retired'],
            'condition' => ['nullable', 'in:good,damaged,lost'],
            'categoryId' => ['nullable', 'integer', Rule::exists('asset_categories', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'q' => ['nullable', 'string', 'max:120'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Asset::query()
            ->withTrashed()
            ->with(['category', 'currentAssignment.employeeProfile.user'])
            ->where('company_id', $companyId);

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['condition'])) {
            $query->where('condition', $validated['condition']);
        }
        if (! empty($validated['categoryId'])) {
            $query->where('asset_category_id', $validated['categoryId']);
        }
        if (! empty($validated['q'])) {
            $q = trim((string) $validated['q']);
            $query->where(function ($builder) use ($q): void {
                $builder->where('asset_code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('brand', 'like', "%{$q}%")
                    ->orWhere('model', 'like', "%{$q}%")
                    ->orWhere('serial_number', 'like', "%{$q}%");
            });
        }

        $rows = $query->orderByDesc('updated_at')->paginate((int) ($validated['perPage'] ?? 20));

        return response()->json([
            'success' => true,
            'data' => $rows->getCollection()->map(fn (Asset $asset) => $this->formatAsset($asset))->values(),
            'meta' => [
                'currentPage' => $rows->currentPage(),
                'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
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

        $validated = $this->validateAssetPayload($request, false, $companyId);
        $asset = $this->assetService->createAsset($companyId, $validated, (int) $request->user()->id);

        return response()->json(['success' => true, 'data' => $this->formatAsset($asset)], 201);
    }

    public function show(Request $request, Asset $asset): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'asset.view')) {
            return $response;
        }

        if (! $this->assetBelongsToActiveCompany($request, $asset)) {
            return $this->errorResponse('NOT_FOUND', 'Asset not found.', 404);
        }

        return response()->json(['success' => true, 'data' => $this->formatAsset($asset->load(['category', 'assignments.employeeProfile.user', 'attachments', 'logs']))]);
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'asset.manage')) {
            return $response;
        }

        $companyId = $this->activeCompanyId($request);

        if (! $this->assetBelongsToActiveCompany($request, $asset)) {
            return $this->errorResponse('NOT_FOUND', 'Asset not found.', 404);
        }

        $validated = $this->validateAssetPayload($request, true, $companyId);
        $updated = $this->assetService->updateAsset($asset, $validated, (int) $request->user()->id);

        return response()->json(['success' => true, 'data' => $this->formatAsset($updated)]);
    }

    public function destroy(Request $request, Asset $asset): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'asset.manage')) {
            return $response;
        }

        if (! $this->assetBelongsToActiveCompany($request, $asset)) {
            return $this->errorResponse('NOT_FOUND', 'Asset not found.', 404);
        }

        $this->assetService->retireAsset($asset, (int) $request->user()->id);

        return response()->json(['success' => true]);
    }

    public function assign(Request $request, Asset $asset): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'asset.manage')) {
            return $response;
        }

        if (! $this->assetBelongsToActiveCompany($request, $asset)) {
            return $this->errorResponse('NOT_FOUND', 'Asset not found.', 404);
        }

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employee_profiles,id'],
            'assigned_date' => ['nullable', 'date'],
            'condition_at_assign' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $employee = EmployeeProfile::query()
            ->where('company_id', $asset->company_id)
            ->whereKey((int) $validated['employee_id'])
            ->firstOrFail();

        $assignment = $this->assetService->assignAsset($asset, $employee, $validated, (int) $request->user()->id);

        return response()->json(['success' => true, 'data' => $this->formatAssignment($assignment)], 201);
    }

    public function returnAsset(Request $request, Asset $asset): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'asset.manage')) {
            return $response;
        }

        if (! $this->assetBelongsToActiveCompany($request, $asset)) {
            return $this->errorResponse('NOT_FOUND', 'Asset not found.', 404);
        }

        $validated = $request->validate([
            'returned_date' => ['nullable', 'date'],
            'condition_at_return' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $assignment = $this->assetService->returnAsset($asset, $validated, (int) $request->user()->id);

        return response()->json(['success' => true, 'data' => $this->formatAssignment($assignment)]);
    }

    public function reportIssue(Request $request, Asset $asset): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'asset.manage')) {
            return $response;
        }

        if (! $this->assetBelongsToActiveCompany($request, $asset)) {
            return $this->errorResponse('NOT_FOUND', 'Asset not found.', 404);
        }

        $validated = $request->validate([
            'issue_type' => ['required', 'in:damaged,lost,maintenance'],
            'priority' => ['nullable', 'in:low,medium,high,urgent'],
            'description' => ['nullable', 'string', 'max:10000'],
        ]);

        $ticket = $this->assetService->reportIssue($asset, $request->user(), $validated);

        return response()->json(['success' => true, 'data' => ['ticketId' => $ticket->id, 'ticketCode' => $ticket->code]], 201);
    }

    public function attach(Request $request, Asset $asset): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'asset.manage')) {
            return $response;
        }

        if (! $this->assetBelongsToActiveCompany($request, $asset)) {
            return $this->errorResponse('NOT_FOUND', 'Asset not found.', 404);
        }

        if (! $this->assetService->companyHasFeature((int) $asset->company_id, AssetService::FEATURE_ASSET_ATTACHMENTS)) {
            return $this->errorResponse('FEATURE_DISABLED', 'Asset attachments are not enabled for this company.', 403);
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $attachment = $this->assetService->uploadAttachment($asset, $validated['file'], (int) $request->user()->id);

        return response()->json(['success' => true, 'data' => $this->formatAttachment($attachment)], 201);
    }

    private function validateAssetPayload(Request $request, bool $isUpdate, ?int $companyId): array
    {
        $rules = [
            'asset_category_id' => [
                $isUpdate ? 'sometimes' : 'required',
                'integer',
                Rule::exists('asset_categories', 'id')->where(fn ($query) => $query->where('company_id', $companyId ?? 0)),
            ],
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:150', Rule::unique('assets')->where(fn ($q) => $q->where('company_id', $companyId ?? 0))->ignore($isUpdate ? $request->route('asset')?->id : null)],
            'purchase_date' => [$isUpdate ? 'sometimes' : 'required', 'date'],
            'purchase_price' => [$isUpdate ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'condition' => ['nullable', 'in:good,damaged,lost'],
            'status' => ['nullable', 'in:available,assigned,maintenance,retired'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'warranty_start_date' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'warranty_end_date' => ['nullable', 'date', 'after_or_equal:warranty_start_date', 'after_or_equal:purchase_date'],
        ];

        $validated = $request->validate($rules);

        return $validated;
    }

    private function assetBelongsToActiveCompany(Request $request, Asset $asset): bool
    {
        $companyId = $this->activeCompanyId($request);

        return $companyId !== null && (int) $asset->company_id === (int) $companyId;
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

    private function formatAsset(Asset $asset): array
    {
        $asset->loadMissing(['category', 'currentAssignment.employeeProfile.user']);

        return [
            'id' => $asset->id,
            'companyId' => $asset->company_id,
            'assetCategoryId' => $asset->asset_category_id,
            'assetCode' => $asset->asset_code,
            'name' => $asset->name,
            'brand' => $asset->brand,
            'model' => $asset->model,
            'serialNumber' => $asset->serial_number,
            'purchaseDate' => $asset->purchase_date?->toDateString(),
            'purchasePrice' => $asset->purchase_price,
            'condition' => $asset->condition,
            'status' => $asset->status,
            'location' => $asset->location,
            'notes' => $asset->notes,
            'warrantyStartDate' => $asset->warranty_start_date?->toDateString(),
            'warrantyEndDate' => $asset->warranty_end_date?->toDateString(),
            'category' => $asset->relationLoaded('category') && $asset->category ? [
                'id' => $asset->category->id,
                'name' => $asset->category->name,
                'code' => $asset->category->code,
            ] : null,
            'currentAssignment' => $asset->relationLoaded('currentAssignment') && $asset->currentAssignment ? $this->formatAssignment($asset->currentAssignment) : null,
            'attachments' => $asset->relationLoaded('attachments')
                ? $asset->attachments->map(fn (AssetAttachment $attachment) => $this->formatAttachment($attachment))->values()->all()
                : null,
            'attachmentsCount' => $asset->attachments()->count(),
            'logsCount' => $asset->logs()->count(),
            'createdAt' => $asset->created_at?->toIso8601String(),
            'updatedAt' => $asset->updated_at?->toIso8601String(),
        ];
    }

    private function formatAssignment(AssetAssignment $assignment): array
    {
        $assignment->loadMissing(['employeeProfile.user', 'asset']);

        return [
            'id' => $assignment->id,
            'companyId' => $assignment->company_id,
            'assetId' => $assignment->asset_id,
            'employeeId' => $assignment->employee_id,
            'employeeName' => $assignment->relationLoaded('employeeProfile') && $assignment->employeeProfile
                ? ($assignment->employeeProfile->relationLoaded('user') && $assignment->employeeProfile->user
                    ? $assignment->employeeProfile->user->name
                    : ($assignment->employeeProfile->name ?? null))
                : null,
            'employee' => $assignment->relationLoaded('employeeProfile') && $assignment->employeeProfile ? [
                'id' => $assignment->employeeProfile->id,
                'name' => $assignment->employeeProfile->name,
                'user' => $assignment->employeeProfile->relationLoaded('user') && $assignment->employeeProfile->user ? [
                    'id' => $assignment->employeeProfile->user->id,
                    'name' => $assignment->employeeProfile->user->name,
                    'email' => $assignment->employeeProfile->user->email,
                ] : null,
            ] : null,
            'assignedDate' => $assignment->assigned_date?->toIso8601String(),
            'returnedDate' => $assignment->returned_date?->toIso8601String(),
            'conditionAtAssign' => $assignment->condition_at_assign,
            'conditionAtReturn' => $assignment->condition_at_return,
            'notes' => $assignment->notes,
            'isActive' => $assignment->active_token === 'active',
        ];
    }

    private function formatAttachment(AssetAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'assetId' => $attachment->asset_id,
            'filePath' => $attachment->file_path,
            'fileType' => $attachment->file_type,
            'disk' => $attachment->disk,
            'originalName' => $attachment->original_name,
            'sizeBytes' => $attachment->size_bytes,
        ];
    }
}
