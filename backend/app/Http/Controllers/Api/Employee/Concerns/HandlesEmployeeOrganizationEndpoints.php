<?php

namespace App\Http\Controllers\Api\Employee\Concerns;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Policy;
use App\Services\Media\Exceptions\InvalidMediaException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait HandlesEmployeeOrganizationEndpoints
{
    public function departments(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:200'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $perPage = (int) ($validated['perPage'] ?? 20);
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? null;
        $activeCompanyId = $this->activeCompanyId($request);

        $query = Department::query();
        $this->applyTenantScope($query, $activeCompanyId);

        $paginator = $query
            ->withCount('designations')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->when($status, function ($query) use ($status) {
                $query->where('is_active', $status === 'active');
            })
            ->orderBy('name')
            ->paginate($perPage);

        $rows = $paginator->getCollection()
            ->map(function (Department $department) {
                return [
                    'id' => $department->id,
                    'code' => $department->code,
                    'name' => $department->name,
                    'designationCount' => $department->designations_count,
                    'isActive' => (bool) $department->is_active,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('departments', 'code')->where(function ($query) use ($activeCompanyId): void {
                    if ($activeCompanyId) {
                        $query->where('company_id', $activeCompanyId);
                    }
                }),
            ],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $department = Department::query()->create([
            'company_id' => $activeCompanyId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $this->slugCode($validated['name']),
            'is_active' => (bool) ($validated['isActive'] ?? true),
        ]);

        return response()->json(['success' => true, 'data' => $department], 201);
    }

    public function exportDepartments(Request $request)
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required to export departments.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'format' => ['nullable', Rule::in(['xlsx', 'csv', 'pdf'])],
        ]);

        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? null;

        $rows = Department::query()
            ->where('company_id', $activeCompanyId)
            ->withCount('designations')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->when($status, function ($query) use ($status) {
                $query->where('is_active', $status === 'active');
            })
            ->orderBy('name')
            ->get()
            ->map(static fn (Department $department): array => [
                (string) $department->name,
                (string) $department->code,
                (int) $department->designations_count,
                $department->is_active ? 'Active' : 'Inactive',
            ])
            ->values()
            ->all();

        $format = $this->normalizeExportFormat($request);
        $this->logExportAuditTrail($request, 'export_departments', $format, count($rows));

        return $this->exportTabular('departments', $format, ['Name', 'Code', 'Designations Linked', 'Status'], $rows);
    }

    public function updateDepartment(Request $request, string $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        $departmentQuery = Department::query();
        $this->applyIdentifierScope($departmentQuery, $id);
        $this->applyTenantScope($departmentQuery, $activeCompanyId);
        $department = $departmentQuery->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('departments', 'code')
                    ->ignore($department->id)
                    ->where(function ($query) use ($activeCompanyId): void {
                        if ($activeCompanyId) {
                            $query->where('company_id', $activeCompanyId);
                        }
                    }),
            ],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $department->update([
            'company_id' => $department->company_id ?: $activeCompanyId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $this->slugCode($validated['name']),
            'is_active' => (bool) ($validated['isActive'] ?? true),
        ]);

        return response()->json(['success' => true, 'data' => $department]);
    }

    public function designations(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:200'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $perPage = (int) ($validated['perPage'] ?? 20);
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? null;
        $departmentId = $validated['departmentId'] ?? null;
        $activeCompanyId = $this->activeCompanyId($request);

        $query = Designation::query();
        $this->applyTenantScope($query, $activeCompanyId);

        $paginator = $query
            ->with('department:id,name')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->when($status, function ($query) use ($status) {
                $query->where('is_active', $status === 'active');
            })
            ->when($departmentId, function ($query) use ($departmentId) {
                $query->where('department_id', (int) $departmentId);
            })
            ->orderBy('name')
            ->paginate($perPage);

        $rows = $paginator->getCollection()
            ->map(function (Designation $designation) {
                return [
                    'id' => $designation->id,
                    'code' => $designation->code,
                    'name' => $designation->name,
                    'departmentId' => $designation->department_id,
                    'department' => optional($designation->department)->name ?? 'Unassigned',
                    'employeeCount' => 0,
                    'isActive' => (bool) $designation->is_active,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function storeDesignation(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('designations', 'code')->where(function ($query) use ($activeCompanyId): void {
                    if ($activeCompanyId) {
                        $query->where('company_id', $activeCompanyId);
                    }
                }),
            ],
            'departmentId' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(function ($query) use ($activeCompanyId): void {
                    if ($activeCompanyId) {
                        $query->where(function ($inner) use ($activeCompanyId): void {
                            $inner->where('company_id', $activeCompanyId)->orWhereNull('company_id');
                        });
                    }
                }),
            ],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $designation = Designation::query()->create([
            'company_id' => $activeCompanyId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $this->slugCode($validated['name']),
            'department_id' => $validated['departmentId'] ?? null,
            'is_active' => (bool) ($validated['isActive'] ?? true),
        ]);

        return response()->json(['success' => true, 'data' => $designation], 201);
    }

    public function exportDesignations(Request $request)
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required to export designations.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
            'format' => ['nullable', Rule::in(['xlsx', 'csv', 'pdf'])],
        ]);

        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? null;
        $departmentId = $validated['departmentId'] ?? null;

        if ($departmentId && ! Department::query()->whereKey((int) $departmentId)->where('company_id', $activeCompanyId)->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DEPARTMENT_NOT_FOUND',
                    'message' => 'Department not found in active company context.',
                ],
            ], 422);
        }

        $rows = Designation::query()
            ->where('company_id', $activeCompanyId)
            ->with('department:id,name')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->when($status, function ($query) use ($status) {
                $query->where('is_active', $status === 'active');
            })
            ->when($departmentId, function ($query) use ($departmentId) {
                $query->where('department_id', (int) $departmentId);
            })
            ->orderBy('name')
            ->get()
            ->map(static fn (Designation $designation): array => [
                (string) $designation->name,
                (string) ($designation->department?->name ?: 'Unassigned'),
                (string) $designation->code,
                $designation->is_active ? 'Active' : 'Inactive',
            ])
            ->values()
            ->all();

        $format = $this->normalizeExportFormat($request);
        $this->logExportAuditTrail($request, 'export_designations', $format, count($rows));

        return $this->exportTabular('designations', $format, ['Name', 'Department', 'Code', 'Status'], $rows);
    }

    public function updateDesignation(Request $request, string $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        $designationQuery = Designation::query();
        $this->applyIdentifierScope($designationQuery, $id);
        $this->applyTenantScope($designationQuery, $activeCompanyId);
        $designation = $designationQuery->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('designations', 'code')
                    ->ignore($designation->id)
                    ->where(function ($query) use ($activeCompanyId): void {
                        if ($activeCompanyId) {
                            $query->where('company_id', $activeCompanyId);
                        }
                    }),
            ],
            'departmentId' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(function ($query) use ($activeCompanyId): void {
                    if ($activeCompanyId) {
                        $query->where(function ($inner) use ($activeCompanyId): void {
                            $inner->where('company_id', $activeCompanyId)->orWhereNull('company_id');
                        });
                    }
                }),
            ],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $designation->update([
            'company_id' => $designation->company_id ?: $activeCompanyId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? $this->slugCode($validated['name']),
            'department_id' => $validated['departmentId'] ?? null,
            'is_active' => (bool) ($validated['isActive'] ?? true),
        ]);

        return response()->json(['success' => true, 'data' => $designation]);
    }

    public function policies(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required to list policies.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:200'],
            'search' => ['nullable', 'string', 'max:100'],
            'departmentId' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $perPage = (int) ($validated['perPage'] ?? 20);
        $search = $validated['search'] ?? null;
        $departmentId = $validated['departmentId'] ?? null;

        $hasPolicyCompanyColumn = $this->tableHasColumn('policies', 'company_id');

        $policyQuery = Policy::query()
            ->with('department:id,name')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            })
            ->when($departmentId, function ($query) use ($departmentId) {
                $query->where('department_id', (int) $departmentId);
            })
            ->orderByDesc('id');

        if ($hasPolicyCompanyColumn) {
            $policyQuery->where('company_id', $activeCompanyId);
        }

        $paginator = $policyQuery->paginate($perPage);

        $rows = $paginator->getCollection()
            ->map(function (Policy $policy) {
                return [
                    'id' => $policy->id,
                    'name' => $policy->name,
                    'departmentId' => $policy->department_id,
                    'department' => optional($policy->department)->name ?? 'All Department',
                    'description' => $policy->description,
                    'effectiveDate' => optional($policy->effective_date)->toDateString(),
                    'createdDate' => optional($policy->effective_date)->toDateString() ?? optional($policy->created_at)->toDateString(),
                    'attachmentUrl' => $this->policyAttachmentUrl($policy->attachment_path),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function storePolicy(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required to create policies.',
                ],
            ], 422);
        }

        $this->mergePolicyMultipartFields($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'departmentId' => ['nullable', 'integer', 'exists:departments,uuid'],
            'effectiveDate' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'max:12288', 'mimetypes:application/pdf,image/jpeg,image/png,image/gif,image/webp'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'department_id' => $validated['departmentId'] ?? null,
            'effective_date' => $validated['effectiveDate'] ?? now()->toDateString(),
        ];

        if ($this->tableHasColumn('policies', 'company_id')) {
            $payload['company_id'] = $activeCompanyId;
        }

        $policy = Policy::query()->create($payload);

        if ($request->hasFile('attachment')) {
            try {
                $stored = $this->policyAttachmentStorage->store($request->file('attachment'), $policy->id);
                $policy->update(['attachment_path' => $stored->path]);
            } catch (InvalidMediaException $exception) {
                $policy->delete();

                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }
        }

        $policy->refresh();

        return response()->json(['success' => true, 'data' => $policy], 201);
    }

    public function exportPolicies(Request $request)
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'departmentId' => ['nullable', 'integer', 'exists:departments,uuid'],
            'format' => ['nullable', Rule::in(['xlsx', 'csv', 'pdf'])],
        ]);

        $search = $validated['search'] ?? null;
        $departmentId = $validated['departmentId'] ?? null;

        $policyQuery = Policy::query()->with('department:id,name');
        if ($this->tableHasColumn('policies', 'company_id')) {
            $activeCompanyId = $this->activeCompanyId($request);
            if (! $activeCompanyId) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TENANT_CONTEXT_REQUIRED',
                        'message' => 'Active company context is required to export policies.',
                    ],
                ], 422);
            }

            $policyQuery->where('company_id', $activeCompanyId);
        }

        $rows = $policyQuery
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            })
            ->when($departmentId, function ($query) use ($departmentId) {
                $query->where('department_id', (int) $departmentId);
            })
            ->orderByDesc('id')
            ->get()
            ->map(static fn (Policy $policy): array => [
                (string) $policy->name,
                (string) ($policy->department?->name ?: 'All Department'),
                (string) $policy->description,
                (string) (optional($policy->effective_date)->toDateString() ?: optional($policy->created_at)->toDateString() ?: ''),
            ])
            ->values()
            ->all();

        $format = $this->normalizeExportFormat($request);
        $this->logExportAuditTrail($request, 'export_policies', $format, count($rows));

        return $this->exportTabular('policies', $format, ['Name', 'Department', 'Description', 'Effective Date'], $rows);
    }

    public function updatePolicy(Request $request, string $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required to update policies.',
                ],
            ], 422);
        }

        $policyQuery = Policy::query();
        if ($this->tableHasColumn('policies', 'company_id')) {
            $policyQuery->where('company_id', $activeCompanyId);
        }
        $this->applyIdentifierScope($policyQuery, $id);
        $policy = $policyQuery->firstOrFail();
        $this->mergePolicyMultipartFields($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'departmentId' => ['nullable', 'integer', 'exists:departments,uuid'],
            'effectiveDate' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'max:12288', 'mimetypes:application/pdf,image/jpeg,image/png,image/gif,image/webp'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'department_id' => $validated['departmentId'] ?? null,
        ];

        if (array_key_exists('effectiveDate', $validated)) {
            $payload['effective_date'] = $validated['effectiveDate'] ?? $policy->effective_date;
        }

        if ($request->hasFile('attachment')) {
            try {
                $stored = $this->policyAttachmentStorage->replace(
                    $policy->attachment_path,
                    $request->file('attachment'),
                    $policy->id,
                );
                $payload['attachment_path'] = $stored->path;
            } catch (InvalidMediaException $exception) {
                return response()->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }
        }

        $policy->update($payload);
        $policy->refresh();

        return response()->json(['success' => true, 'data' => $policy]);
    }

    public function destroyDepartment(Request $request, string $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $departmentQuery = Department::query();
        $this->applyIdentifierScope($departmentQuery, $id);
        $this->applyTenantScope($departmentQuery, $this->activeCompanyId($request));
        $department = $departmentQuery->firstOrFail();
        $department->delete();

        return response()->json(['success' => true]);
    }

    public function destroyDesignation(Request $request, string $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $designationQuery = Designation::query();
        $this->applyIdentifierScope($designationQuery, $id);
        $this->applyTenantScope($designationQuery, $this->activeCompanyId($request));
        $designation = $designationQuery->firstOrFail();
        $designation->delete();

        return response()->json(['success' => true]);
    }

    public function destroyPolicy(Request $request, string $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required to delete policies.',
                ],
            ], 422);
        }

        $policyQuery = Policy::query();
        if ($this->tableHasColumn('policies', 'company_id')) {
            $policyQuery->where('company_id', $activeCompanyId);
        }
        $this->applyIdentifierScope($policyQuery, $id);
        $policy = $policyQuery->firstOrFail();
        $this->mediaFileDeleter->delete($policy->attachment_path);
        $policy->delete();

        return response()->json(['success' => true]);
    }
}
