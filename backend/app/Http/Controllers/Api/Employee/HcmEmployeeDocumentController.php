<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\EmployeeProfile;
use App\Models\HcmEmployeeDocument;
use App\Models\HcmEmployeeDocumentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class HcmEmployeeDocumentController extends Controller
{
    use ChecksPermissions;

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function tenantRequired(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context is required.'],
        ], 422);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'AUTH_FORBIDDEN', 'message' => 'Forbidden.'],
        ], 403);
    }

    private function canManage(Request $request): bool
    {
        return $this->hasPermission($request, 'document_center.manage');
    }

    private function canView(Request $request): bool
    {
        return $this->hasPermission($request, 'document_center.view') || $this->canManage($request);
    }

    private function formatCategory(HcmEmployeeDocumentCategory $cat): array
    {
        return [
            'id'          => $cat->id,
            'uuid'        => $cat->uuid,
            'name'        => $cat->name,
            'description' => $cat->description ?? '',
            'isActive'    => (bool) $cat->is_active,
        ];
    }

    private function formatDocument(HcmEmployeeDocument $doc): array
    {
        $profile = $doc->relationLoaded('employeeProfile') ? $doc->employeeProfile : null;
        $uploader = $doc->relationLoaded('uploadedBy') ? $doc->uploadedBy : null;

        return [
            'id'                 => $doc->id,
            'uuid'               => $doc->uuid,
            'title'              => $doc->title,
            'description'        => $doc->description ?? '',
            'originalName'       => $doc->original_name,
            'mimeType'           => $doc->mime_type ?? '',
            'sizeBytes'          => $doc->size_bytes,
            'visibility'         => $doc->visibility,
            'expiresAt'          => $doc->expires_at?->format('Y-m-d'),
            'createdAt'          => $doc->created_at?->toIso8601String(),
            'category'           => $doc->category_id ? [
                'id'   => $doc->category_id,
                'name' => $doc->relationLoaded('category') ? ($doc->category->name ?? '') : '',
            ] : null,
            'employee'           => $profile ? [
                'id'       => $profile->id,
                'uuid'     => $profile->uuid ?? null,
                'fullName' => trim(($profile->first_name ?? '') . ' ' . ($profile->last_name ?? '')),
            ] : ['id' => $doc->employee_profile_id, 'uuid' => $doc->employee_profile_uuid, 'fullName' => ''],
            'uploadedBy'         => $uploader ? [
                'id'   => $uploader->id,
                'name' => $uploader->name ?? '',
            ] : null,
            'downloadUrl'        => route('api.document-center.download', ['id' => $doc->id]),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Categories
    // ─────────────────────────────────────────────────────────────────────────

    public function categories(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantRequired();
        }

        if (! $this->canView($request)) {
            return $this->forbidden();
        }

        $query = HcmEmployeeDocumentCategory::query()
            ->where('company_id', $companyId)
            ->orderBy('name');

        if (! $this->canManage($request)) {
            $query->where('is_active', true);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->get()->map(fn ($c) => $this->formatCategory($c))->values(),
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantRequired();
        }

        if (! $this->canManage($request)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:200',
                Rule::unique('hcm_employee_document_categories')->where('company_id', $companyId),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'isActive'    => ['nullable', 'boolean'],
        ]);

        $cat = HcmEmployeeDocumentCategory::query()->create([
            'company_id'  => $companyId,
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active'   => $validated['isActive'] ?? true,
        ]);

        return response()->json(['success' => true, 'data' => $this->formatCategory($cat)], 201);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantRequired();
        }

        if (! $this->canManage($request)) {
            return $this->forbidden();
        }

        $cat = HcmEmployeeDocumentCategory::query()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:200',
                Rule::unique('hcm_employee_document_categories')->where('company_id', $companyId)->ignore($cat->id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'isActive'    => ['nullable', 'boolean'],
        ]);

        $cat->fill(array_filter([
            'name'        => $validated['name'] ?? null,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : null,
            'is_active'   => array_key_exists('isActive', $validated) ? $validated['isActive'] : null,
        ], fn ($v) => $v !== null));

        if (array_key_exists('isActive', $validated)) {
            $cat->is_active = (bool) $validated['isActive'];
        }
        if (array_key_exists('name', $validated)) {
            $cat->name = $validated['name'];
        }
        if (array_key_exists('description', $validated)) {
            $cat->description = $validated['description'];
        }

        $cat->save();

        return response()->json(['success' => true, 'data' => $this->formatCategory($cat)]);
    }

    public function destroyCategory(Request $request, int $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantRequired();
        }

        if (! $this->canManage($request)) {
            return $this->forbidden();
        }

        $cat = HcmEmployeeDocumentCategory::query()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        // Reassign orphaned documents to no category
        HcmEmployeeDocument::query()
            ->where('company_id', $companyId)
            ->where('category_id', $cat->id)
            ->update(['category_id' => null, 'category_uuid' => null]);

        $cat->delete();

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Documents
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantRequired();
        }

        $user = $request->user();
        $isManager = $this->canView($request);

        if (! $isManager) {
            // Employee can only see their own visible documents
            $profile = EmployeeProfile::query()
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->first();

            if (! $profile) {
                return response()->json(['success' => true, 'data' => [], 'meta' => ['total' => 0]]);
            }

            $query = HcmEmployeeDocument::query()
                ->with(['category', 'uploadedBy'])
                ->where('company_id', $companyId)
                ->where('employee_profile_id', $profile->id)
                ->where('visibility', 'employee_visible')
                ->orderByDesc('created_at');

            $rows = $query->paginate(20);

            return response()->json([
                'success' => true,
                'data'    => $rows->getCollection()->map(fn ($d) => $this->formatDocument($d))->values(),
                'meta'    => ['currentPage' => $rows->currentPage(), 'lastPage' => $rows->lastPage(), 'total' => $rows->total()],
            ]);
        }

        $validated = $request->validate([
            'employeeProfileId' => ['nullable', 'integer'],
            'categoryId'        => ['nullable', 'integer'],
            'visibility'        => ['nullable', 'in:hr_only,employee_visible'],
            'q'                 => ['nullable', 'string', 'max:120'],
            'perPage'           => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = HcmEmployeeDocument::query()
            ->with(['category', 'employeeProfile.user', 'uploadedBy'])
            ->where('company_id', $companyId);

        if (! empty($validated['employeeProfileId'])) {
            $query->where('employee_profile_id', (int) $validated['employeeProfileId']);
        }
        if (! empty($validated['categoryId'])) {
            $query->where('category_id', (int) $validated['categoryId']);
        }
        if (! empty($validated['visibility'])) {
            $query->where('visibility', $validated['visibility']);
        }
        if (! empty($validated['q'])) {
            $q = trim((string) $validated['q']);
            $query->where(function ($b) use ($q): void {
                $b->where('title', 'like', "%{$q}%")
                    ->orWhere('original_name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $rows = $query->orderByDesc('created_at')->paginate((int) ($validated['perPage'] ?? 20));

        return response()->json([
            'success' => true,
            'data'    => $rows->getCollection()->map(fn ($d) => $this->formatDocument($d))->values(),
            'meta'    => [
                'currentPage' => $rows->currentPage(),
                'lastPage'    => $rows->lastPage(),
                'perPage'     => $rows->perPage(),
                'total'       => $rows->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantRequired();
        }

        if (! $this->canManage($request)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'employeeProfileId' => ['required', 'integer',
                Rule::exists('employee_profiles', 'id')->where('company_id', $companyId),
            ],
            'categoryId'        => ['nullable', 'integer',
                Rule::exists('hcm_employee_document_categories', 'id')->where('company_id', $companyId),
            ],
            'title'             => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:5000'],
            'visibility'        => ['nullable', 'in:hr_only,employee_visible'],
            'expiresAt'         => ['nullable', 'date'],
            'file'              => ['required', 'file', 'max:20480',
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,zip,txt,csv',
            ],
        ]);

        $file = $request->file('file');
        $storedPath = $file->storePublicly(
            sprintf('documents/%s', $companyId),
            'public'
        );

        $doc = HcmEmployeeDocument::query()->create([
            'company_id'         => $companyId,
            'employee_profile_id' => (int) $validated['employeeProfileId'],
            'category_id'        => isset($validated['categoryId']) && $validated['categoryId'] ? (int) $validated['categoryId'] : null,
            'title'              => $validated['title'],
            'description'        => $validated['description'] ?? null,
            'file_path'          => $storedPath,
            'original_name'      => $file->getClientOriginalName(),
            'mime_type'          => $file->getMimeType(),
            'size_bytes'         => (int) $file->getSize(),
            'disk'               => 'public',
            'visibility'         => $validated['visibility'] ?? 'hr_only',
            'expires_at'         => $validated['expiresAt'] ?? null,
            'uploaded_by'        => (int) $request->user()->id,
        ]);

        $doc->load(['category', 'employeeProfile.user', 'uploadedBy']);

        return response()->json(['success' => true, 'data' => $this->formatDocument($doc)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantRequired();
        }

        if (! $this->canManage($request)) {
            return $this->forbidden();
        }

        $doc = HcmEmployeeDocument::query()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $validated = $request->validate([
            'categoryId'  => ['nullable', 'integer',
                Rule::exists('hcm_employee_document_categories', 'id')->where('company_id', $companyId),
            ],
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'visibility'  => ['nullable', 'in:hr_only,employee_visible'],
            'expiresAt'   => ['nullable', 'date'],
        ]);

        if (array_key_exists('categoryId', $validated)) {
            $doc->category_id = $validated['categoryId'] ? (int) $validated['categoryId'] : null;
            $doc->category_uuid = null;
        }
        if (array_key_exists('title', $validated)) {
            $doc->title = $validated['title'];
        }
        if (array_key_exists('description', $validated)) {
            $doc->description = $validated['description'];
        }
        if (array_key_exists('visibility', $validated)) {
            $doc->visibility = $validated['visibility'];
        }
        if (array_key_exists('expiresAt', $validated)) {
            $doc->expires_at = $validated['expiresAt'];
        }

        $doc->save();
        $doc->load(['category', 'employeeProfile.user', 'uploadedBy']);

        return response()->json(['success' => true, 'data' => $this->formatDocument($doc)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantRequired();
        }

        if (! $this->canManage($request)) {
            return $this->forbidden();
        }

        $doc = HcmEmployeeDocument::query()
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        // Delete physical file
        if ($doc->file_path && Storage::disk($doc->disk ?? 'public')->exists($doc->file_path)) {
            Storage::disk($doc->disk ?? 'public')->delete($doc->file_path);
        }

        $doc->delete();

        return response()->json(['success' => true]);
    }

    public function download(Request $request, int $id): mixed
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->tenantRequired();
        }

        $user = $request->user();
        $doc = HcmEmployeeDocument::query()
            ->with('employeeProfile')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $canManage = $this->canManage($request);
        $canView = $this->canView($request);

        if (! $canManage && ! $canView) {
            // Non-HR employee: can only download their own employee_visible docs
            $profile = EmployeeProfile::query()
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)
                ->first();

            if (! $profile || $doc->employee_profile_id !== $profile->id || $doc->visibility !== 'employee_visible') {
                return $this->forbidden();
            }
        }

        $disk = $doc->disk ?? 'public';

        if (! Storage::disk($disk)->exists($doc->file_path)) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'FILE_NOT_FOUND', 'message' => 'File not found on server.'],
            ], 404);
        }

        return Storage::disk($disk)->download($doc->file_path, $doc->original_name);
    }
}
