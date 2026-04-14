<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetLog;
use App\Models\HcmManualActivity;
use App\Models\HcmPayrollRun;
use App\Models\HcmUserRoleAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HcmActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()?->isHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'Only HCM admin can access activity feed.',
                ],
            ], 403);
        }

        $company = $request->attributes->get('activeCompany');
        $companyId = (int) ($company?->id ?? 0);
        if ($companyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_COMPANY_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:all,asset,user_access,payroll,manual'],
            'sourceType' => ['nullable', 'string', 'in:all,system,manual'],
            'statusType' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:150'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $type = (string) ($validated['type'] ?? 'all');
        $sourceType = (string) ($validated['sourceType'] ?? 'all');
        $statusType = trim(strtolower((string) ($validated['statusType'] ?? 'all')));
        $search = trim((string) ($validated['q'] ?? ''));
        $page = max(1, (int) ($validated['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($validated['perPage'] ?? 20)));

        $items = collect();

        if ($type === 'all' || $type === 'asset') {
            $assetQuery = AssetLog::query()
                ->with(['asset:id,name,asset_code', 'performer:id,name'])
                ->where('company_id', $companyId)
                ->latest('created_at')
                ->limit(120);

            if ($search !== '') {
                $assetQuery->where(function ($q) use ($search): void {
                    $q->where('description', 'like', '%'.$search.'%')
                        ->orWhere('action', 'like', '%'.$search.'%');
                });
            }

            $assetItems = $assetQuery->get()->map(function (AssetLog $log): array {
                $createdAt = $log->created_at;
                $normalizedAction = strtolower((string) $log->action);

                return [
                    'id' => 'asset-'.$log->id,
                    'title' => $log->description,
                    'activityType' => 'asset',
                    'activityTypeLabel' => 'Asset',
                    'sourceType' => 'system',
                    'sourceTypeLabel' => 'System',
                    'statusType' => $normalizedAction !== '' ? $normalizedAction : 'updated',
                    'statusTypeLabel' => $normalizedAction !== '' ? ucfirst(str_replace('_', ' ', $normalizedAction)) : 'Updated',
                    'dueDate' => null,
                    'ownerName' => $log->performer?->name ?: 'System',
                    'canEdit' => false,
                    'canDelete' => false,
                    'readOnlyReason' => 'System-generated activity. Read-only.',
                    'createdAt' => $createdAt?->toIso8601String(),
                    'createdAtTs' => $createdAt?->timestamp ?? 0,
                ];
            });

            $items = $items->concat($assetItems);
        }

        if ($type === 'all' || $type === 'user_access') {
            $roleQuery = HcmUserRoleAudit::query()
                ->with(['actorUser:id,name', 'targetUser:id,name', 'role:id,name'])
                ->where('company_id', $companyId)
                ->latest('created_at')
                ->limit(120);

            if ($search !== '') {
                $roleQuery->where(function ($q) use ($search): void {
                    $q->where('action', 'like', '%'.$search.'%')
                        ->orWhere('notes', 'like', '%'.$search.'%');
                });
            }

            $roleItems = $roleQuery->get()->map(function (HcmUserRoleAudit $audit): array {
                $createdAt = $audit->created_at;
                $targetName = $audit->targetUser?->name ?: 'User';
                $roleName = $audit->role?->name ?: 'Role';
                $normalizedAction = strtolower((string) $audit->action);
                $baseTitle = match ($audit->action) {
                    'assigned' => sprintf('%s assigned to %s', $roleName, $targetName),
                    'revoked' => sprintf('%s revoked from %s', $roleName, $targetName),
                    default => sprintf('User access updated for %s', $targetName),
                };

                return [
                    'id' => 'user-access-'.$audit->id,
                    'title' => $audit->notes ? trim($audit->notes) : $baseTitle,
                    'activityType' => 'user_access',
                    'activityTypeLabel' => 'User Access',
                    'sourceType' => 'system',
                    'sourceTypeLabel' => 'System',
                    'statusType' => $normalizedAction !== '' ? $normalizedAction : 'updated',
                    'statusTypeLabel' => $normalizedAction !== '' ? ucfirst(str_replace('_', ' ', $normalizedAction)) : 'Updated',
                    'dueDate' => null,
                    'ownerName' => $audit->actorUser?->name ?: 'System',
                    'canEdit' => false,
                    'canDelete' => false,
                    'readOnlyReason' => 'System-generated activity. Read-only.',
                    'createdAt' => $createdAt?->toIso8601String(),
                    'createdAtTs' => $createdAt?->timestamp ?? 0,
                ];
            });

            $items = $items->concat($roleItems);
        }

        if ($type === 'all' || $type === 'payroll') {
            $runQuery = HcmPayrollRun::query()
                ->with(['period:id,period_year,period_month', 'finalizedBy:id,name'])
                ->where('company_id', $companyId)
                ->latest('created_at')
                ->limit(120);

            if ($search !== '') {
                $runQuery->where('purpose', 'like', '%'.$search.'%');
            }

            $payrollItems = $runQuery->get()->map(function (HcmPayrollRun $run): array {
                $createdAt = $run->finalized_at ?: $run->calculated_at ?: $run->created_at;
                $purposeLabel = match ($run->purpose) {
                    HcmPayrollRun::PURPOSE_THR => 'THR',
                    HcmPayrollRun::PURPOSE_PKWT_COMPENSATION => 'PKWT Compensation',
                    default => 'Monthly Payroll',
                };

                $title = $run->status === HcmPayrollRun::STATUS_FINALIZED
                    ? sprintf('%s finalized', $purposeLabel)
                    : sprintf('%s calculated (draft)', $purposeLabel);

                $statusType = strtolower((string) $run->status);
                if ($statusType === HcmPayrollRun::STATUS_DRAFT && $run->calculated_at !== null) {
                    $statusType = 'calculated';
                }

                $statusTypeLabel = match ($statusType) {
                    'finalized' => 'Finalized',
                    'calculated' => 'Calculated',
                    'void' => 'Void',
                    default => ucfirst(str_replace('_', ' ', $statusType !== '' ? $statusType : 'draft')),
                };

                return [
                    'id' => 'payroll-'.$run->id,
                    'title' => $title,
                    'activityType' => 'payroll',
                    'activityTypeLabel' => 'Payroll',
                    'sourceType' => 'system',
                    'sourceTypeLabel' => 'System',
                    'statusType' => $statusType !== '' ? $statusType : 'draft',
                    'statusTypeLabel' => $statusTypeLabel,
                    'dueDate' => $run->period
                        ? sprintf('%04d-%02d-%02d', (int) $run->period->period_year, (int) $run->period->period_month, 1)
                        : null,
                    'ownerName' => $run->finalizedBy?->name ?: 'System',
                    'canEdit' => false,
                    'canDelete' => false,
                    'readOnlyReason' => 'System-generated activity. Read-only.',
                    'createdAt' => $createdAt?->toIso8601String(),
                    'createdAtTs' => $createdAt?->timestamp ?? 0,
                ];
            });

            $items = $items->concat($payrollItems);
        }

        if ($type === 'all' || $type === 'manual') {
            $manualQuery = HcmManualActivity::query()
                ->with(['creator:id,name'])
                ->where('company_id', $companyId)
                ->latest('created_at')
                ->limit(200);

            if ($search !== '') {
                $manualQuery->where(function ($q) use ($search): void {
                    $q->where('title', 'like', '%'.$search.'%')
                        ->orWhere('activity_kind', 'like', '%'.$search.'%')
                        ->orWhere('status', 'like', '%'.$search.'%');
                });
            }

            $manualItems = $manualQuery->get()->map(function (HcmManualActivity $manual): array {
                $createdAt = $manual->created_at;
                $status = strtolower((string) $manual->status);
                $kind = strtolower((string) $manual->activity_kind);

                return [
                    'id' => 'manual-'.$manual->id,
                    'manualActivityId' => (int) $manual->id,
                    'title' => $manual->title,
                    'activityType' => 'manual',
                    'activityTypeLabel' => 'Manual',
                    'activityKind' => $kind !== '' ? $kind : 'task',
                    'sourceType' => 'manual',
                    'sourceTypeLabel' => 'Manual',
                    'statusType' => $status !== '' ? $status : 'planned',
                    'statusTypeLabel' => ucfirst(str_replace('_', ' ', $status !== '' ? $status : 'planned')),
                    'dueDate' => $manual->due_date?->format('Y-m-d'),
                    'ownerName' => $manual->creator?->name ?: 'System',
                    'canEdit' => true,
                    'canDelete' => true,
                    'readOnlyReason' => null,
                    'createdAt' => $createdAt?->toIso8601String(),
                    'createdAtTs' => $createdAt?->timestamp ?? 0,
                ];
            });

            $items = $items->concat($manualItems);
        }

        $filtered = $items->filter(function (array $item) use ($sourceType, $statusType): bool {
            $sourceMatches = $sourceType === 'all' || ($item['sourceType'] ?? '') === $sourceType;
            $statusMatches = $statusType === '' || $statusType === 'all' || ($item['statusType'] ?? '') === $statusType;

            return $sourceMatches && $statusMatches;
        })->values();

        $sorted = $filtered->sortByDesc('createdAtTs')->values();
        $total = $sorted->count();
        $rows = $sorted->forPage($page, $perPage)->values()->map(function (array $item): array {
            unset($item['createdAtTs']);

            return $item;
        })->all();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ]);
    }

    public function storeManual(Request $request): JsonResponse
    {
        if (! $request->user()?->isHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'Only HCM admin can manage manual activities.',
                ],
            ], 403);
        }

        $companyId = (int) ($request->attributes->get('activeCompany')?->id ?? 0);
        if ($companyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_COMPANY_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'activityKind' => ['required', 'string', 'in:task,call,email,meeting,note'],
            'statusType' => ['required', 'string', 'in:planned,in_progress,completed,cancelled'],
            'dueDate' => ['nullable', 'date'],
        ]);

        $manual = HcmManualActivity::query()->create([
            'company_id' => $companyId,
            'title' => trim((string) $validated['title']),
            'activity_kind' => trim((string) $validated['activityKind']),
            'status' => trim((string) $validated['statusType']),
            'due_date' => $validated['dueDate'] ?? null,
            'created_by_user_id' => (int) ($request->user()?->id ?? 0) ?: null,
            'updated_by_user_id' => (int) ($request->user()?->id ?? 0) ?: null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (int) $manual->id,
            ],
            'message' => 'Manual activity created.',
        ], 201);
    }

    public function updateManual(Request $request, int $id): JsonResponse
    {
        if (! $request->user()?->isHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'Only HCM admin can manage manual activities.',
                ],
            ], 403);
        }

        $companyId = (int) ($request->attributes->get('activeCompany')?->id ?? 0);
        $manual = HcmManualActivity::query()
            ->where('company_id', $companyId)
            ->find($id);

        if (! $manual) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACTIVITY_NOT_FOUND',
                    'message' => 'Manual activity not found.',
                ],
            ], 404);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'activityKind' => ['required', 'string', 'in:task,call,email,meeting,note'],
            'statusType' => ['required', 'string', 'in:planned,in_progress,completed,cancelled'],
            'dueDate' => ['nullable', 'date'],
        ]);

        $manual->update([
            'title' => trim((string) $validated['title']),
            'activity_kind' => trim((string) $validated['activityKind']),
            'status' => trim((string) $validated['statusType']),
            'due_date' => $validated['dueDate'] ?? null,
            'updated_by_user_id' => (int) ($request->user()?->id ?? 0) ?: null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Manual activity updated.',
        ]);
    }

    public function destroyManual(Request $request, int $id): JsonResponse
    {
        if (! $request->user()?->isHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'Only HCM admin can manage manual activities.',
                ],
            ], 403);
        }

        $companyId = (int) ($request->attributes->get('activeCompany')?->id ?? 0);
        $manual = HcmManualActivity::query()
            ->where('company_id', $companyId)
            ->find($id);

        if (! $manual) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACTIVITY_NOT_FOUND',
                    'message' => 'Manual activity not found.',
                ],
            ], 404);
        }

        $manual->delete();

        return response()->json([
            'success' => true,
            'message' => 'Manual activity deleted.',
        ]);
    }
}
