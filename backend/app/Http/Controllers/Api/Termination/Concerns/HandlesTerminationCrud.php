<?php

namespace App\Http\Controllers\Api\Termination\Concerns;

use App\DataClasses\WorkflowAuditEvent;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\HcmTermination;
use App\Models\HcmTerminationChecklistItem;
use App\Models\User;
use App\Services\AssetService;
use App\Services\Hcm\PkwtCompensationService;
use App\Services\Hcm\TerminationSettlementCalculationService;
use App\Services\Hcm\TerminationWorkflowValidator;
use App\Notifications\TerminationApprovalRequestedNotification;
use App\Services\ApprovalConfigService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HandlesTerminationCrud
{
    public function __construct(
        private readonly AssetService $assetService,
        private readonly PkwtCompensationService $pkwtCompensationService,
        private readonly TerminationSettlementCalculationService $settlementCalculator,
        private readonly TerminationWorkflowValidator $workflowValidator,
        private readonly ApprovalConfigService $approvalConfigService,
    ) {}

    // =========================================================================
    // Slice C — Checklist Item Management (structured DB items)
    // =========================================================================

    /**
     * POST /v1/hcm/terminations/{id}/checklist-items
     */
    public function createChecklistItem(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'termination.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->resolveActiveCompanyId($request);
        if ($activeCompanyId === null) {
            return $this->tenantContextError();
        }

        // Anomaly #6 — always scope through company-scoped parent
        $termination = HcmTermination::query()
            ->where('company_id', $activeCompanyId)
            ->findOrFail($id);

        if (in_array($termination->status, ['finalized', 'cancelled'], true)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'TERMINATION_LOCKED', 'message' => 'Cannot add checklist items to a finalized or cancelled termination.'],
            ], 422);
        }

        $v = $request->validate([
            'label'               => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'ownerName'           => ['nullable', 'string', 'max:100'],
            'dueDate'             => ['nullable', 'date'],
            'mandatory'           => ['boolean'],
        ]);

        $item = HcmTerminationChecklistItem::query()->create([
            'termination_id'    => $termination->id,
            'label'             => trim($v['label']),
            'description'       => $this->cleanNullableString($v['description'] ?? null),
            'owner_name'        => $this->cleanNullableString($v['ownerName'] ?? null),
            'due_date'          => $v['dueDate'] ?? null,
            'mandatory'         => (bool) ($v['mandatory'] ?? false),
            'status'            => 'open',
        ]);

        return response()->json(['success' => true, 'data' => $this->checklistItemPayload($item)], 201);
    }

    /**
     * GET /v1/hcm/terminations/{id}/checklist-items
     */
    public function listChecklistItems(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'termination.view')) {
            return $forbidden;
        }

        $activeCompanyId = $this->resolveActiveCompanyId($request);
        if ($activeCompanyId === null) {
            return $this->tenantContextError();
        }

        $termination = HcmTermination::query()
            ->where('company_id', $activeCompanyId)
            ->findOrFail($id);

        $items = HcmTerminationChecklistItem::query()
            ->where('termination_id', $termination->id)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $items->map(fn (HcmTerminationChecklistItem $item) => $this->checklistItemPayload($item))->values(),
        ]);
    }

    /**
     * PATCH /v1/hcm/terminations/{id}/checklist-items/{itemId}
     */
    public function updateChecklistItem(Request $request, int $id, int $itemId): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'termination.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->resolveActiveCompanyId($request);
        if ($activeCompanyId === null) {
            return $this->tenantContextError();
        }

        // Scope through company-scoped termination (Anomaly #6)
        $termination = HcmTermination::query()
            ->where('company_id', $activeCompanyId)
            ->findOrFail($id);

        $item = HcmTerminationChecklistItem::query()
            ->where('termination_id', $termination->id)
            ->findOrFail($itemId);

        $v = $request->validate([
            'label'       => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'ownerName'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'dueDate'     => ['sometimes', 'required', 'date'],
            'mandatory'   => ['sometimes', 'boolean'],
            'status'      => ['sometimes', 'string', 'in:'.implode(',', HcmTerminationChecklistItem::STATUSES)],
        ]);

        $updateData = [];
        if (array_key_exists('label', $v))       { $updateData['label']       = trim($v['label']); }
        if (array_key_exists('description', $v)) { $updateData['description'] = $this->cleanNullableString($v['description']); }
        if (array_key_exists('ownerName', $v))   { $updateData['owner_name']  = $this->cleanNullableString($v['ownerName']); }
        if (array_key_exists('dueDate', $v))     { $updateData['due_date']    = $v['dueDate']; }
        if (array_key_exists('mandatory', $v))   { $updateData['mandatory']   = (bool) $v['mandatory']; }
        if (array_key_exists('status', $v))      { $updateData['status']      = $v['status']; }

        if ($updateData !== []) {
            $item->update($updateData);
        }

        return response()->json(['success' => true, 'data' => $this->checklistItemPayload($item->fresh())]);
    }

    /**
     * PATCH /v1/hcm/terminations/{id}/checklist-items/{itemId}/complete
     */
    public function completeChecklistItem(Request $request, int $id, int $itemId): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'termination.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->resolveActiveCompanyId($request);
        if ($activeCompanyId === null) {
            return $this->tenantContextError();
        }

        $termination = HcmTermination::query()
            ->where('company_id', $activeCompanyId)
            ->findOrFail($id);

        $item = HcmTerminationChecklistItem::query()
            ->where('termination_id', $termination->id)
            ->findOrFail($itemId);

        $v = $request->validate([
            'completionEvidence' => ['nullable', 'string', 'max:2000'],
        ]);

        $item->update([
            'status'             => 'completed',
            'completed_by'       => (int) $request->user()->id,
            'completed_at'       => now(),
            'completion_evidence' => $this->cleanNullableString($v['completionEvidence'] ?? null),
        ]);

        return response()->json(['success' => true, 'data' => $this->checklistItemPayload($item->fresh())]);
    }

    /**
     * DELETE /v1/hcm/terminations/{id}/checklist-items/{itemId}
     * Soft-delete only — audit trail preserved (Anomaly #3).
     */
    public function deleteChecklistItem(Request $request, int $id, int $itemId): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'termination.manage')) {
            return $forbidden;
        }

        $activeCompanyId = $this->resolveActiveCompanyId($request);
        if ($activeCompanyId === null) {
            return $this->tenantContextError();
        }

        $termination = HcmTermination::query()
            ->where('company_id', $activeCompanyId)
            ->findOrFail($id);

        $item = HcmTerminationChecklistItem::query()
            ->where('termination_id', $termination->id)
            ->findOrFail($itemId);

        if ($item->status === 'completed') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'DELETE_FORBIDDEN_COMPLETED', 'message' => 'Cannot delete a completed checklist item.'],
            ], 422);
        }

        $item->delete(); // SoftDelete — Anomaly #3

        return response()->json(['success' => true]);
    }

    /**
     * Serialize a checklist item for API responses.
     *
     * @return array<string, mixed>
     */
    private function checklistItemPayload(HcmTerminationChecklistItem $item): array
    {
        return [
            'id'                 => $item->id,
            'uuid'               => $item->uuid,
            'terminationId'      => $item->termination_id,
            'label'              => $item->label,
            'description'        => $item->description,
            'ownerName'          => $item->owner_name,
            'dueDate'            => $item->due_date?->toDateString(),
            'mandatory'          => (bool) $item->mandatory,
            'status'             => $item->status,
            'completedBy'        => $item->completed_by,
            'completedAt'        => $item->completed_at?->toIso8601String(),
            'completionEvidence' => $item->completion_evidence,
            'createdAt'          => $item->created_at?->toIso8601String(),
        ];
    }

    /**
     * Extract and validate active company id from request.
     */
    private function resolveActiveCompanyId(Request $request): ?int
    {
        $id = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        return $id > 0 ? $id : null;
    }

    /**
     * Standard 422 response for missing tenant context.
     */
    private function tenantContextError(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'TENANT_CONTEXT_REQUIRED', 'message' => 'Active company context is required.'],
        ], 422);
    }

    private function terminationForbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'AUTH_FORBIDDEN',
                'message' => 'Forbidden.',
            ],
        ], 403);
    }

    public function index(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'termination.view')) {
            return $forbidden;
        }

        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $v = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = HcmTermination::query()
            ->where('company_id', $activeCompanyId)
            ->with([
                'user:id,name,email',
                'settlementPayrollPeriodRef:id,period_year,period_month,status',
                'workflowReviewedBy:id,name,email',
                'workflowApprovedBy:id,name,email',
                'workflowFinalizedBy:id,name,email',
            ])
            ->orderByDesc('termination_date')
            ->orderByDesc('id');

        if (! empty($v['q'])) {
            $q = trim((string) $v['q']);
            $query->where(function ($b) use ($q): void {
                $b->where('department', 'like', '%'.$q.'%')
                    ->orWhere('termination_type', 'like', '%'.$q.'%')
                    ->orWhere('termination_reason_code', 'like', '%'.$q.'%')
                    ->orWhere('legal_basis_code', 'like', '%'.$q.'%')
                    ->orWhere('reason', 'like', '%'.$q.'%')
                    ->orWhere('status', 'like', '%'.$q.'%')
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%'));
            });
        }
        if (! empty($v['dateFrom'])) {
            $query->whereDate('termination_date', '>=', $v['dateFrom']);
        }
        if (! empty($v['dateTo'])) {
            $query->whereDate('termination_date', '<=', $v['dateTo']);
        }

        $rows = $query->paginate((int) ($v['perPage'] ?? 20));

        return response()->json([
            'success' => true,
            'data' => $rows->getCollection()->map(fn (HcmTermination $t) => $this->payload($t))->values(),
            'meta' => [
                'currentPage' => $rows->currentPage(),
                'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $t = HcmTermination::query()
            ->where('company_id', $activeCompanyId)
            ->with([
                'user:id,name,email',
                'settlementPayrollPeriodRef:id,period_year,period_month,status',
                'workflowReviewedBy:id,name,email',
                'workflowApprovedBy:id,name,email',
                'workflowFinalizedBy:id,name,email',
            ])
            ->find($id);
        if (! $t) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TERMINATION_NOT_FOUND',
                    'message' => 'Termination not found.',
                ],
            ], 404);
        }

        $auth = $request->user();
        if (! $this->canManageTermination($request) && (int) $auth->id !== (int) $t->user_id) {
            return $this->terminationForbidden();
        }

        return response()->json([
            'success' => true,
            'data' => $this->payload($t),
        ]);
    }

    public function terminationsForUser(Request $request, int $userId): JsonResponse
    {
        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $auth = $request->user();
        if (! $this->canManageTermination($request) && (int) $auth->id !== (int) $userId) {
            return $this->terminationForbidden();
        }

        User::query()->findOrFail($userId);

        $v = $request->validate([
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $rows = HcmTermination::query()
            ->where('company_id', $activeCompanyId)
            ->with([
                'user:id,name,email',
                'settlementPayrollPeriodRef:id,period_year,period_month,status',
                'workflowReviewedBy:id,name,email',
                'workflowApprovedBy:id,name,email',
                'workflowFinalizedBy:id,name,email',
            ])
            ->where('user_id', $userId)
            ->orderByDesc('termination_date')
            ->orderByDesc('id')
            ->paginate((int) ($v['perPage'] ?? 20));

        return response()->json([
            'success' => true,
            'data' => $rows->getCollection()->map(fn (HcmTermination $t) => $this->payload($t))->values(),
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
        if ($forbidden = $this->ensurePermission($request, 'termination.manage')) {
            return $forbidden;
        }

        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $v = $request->validate([
            'userId' => ['required', 'uuid', 'exists:users,uuid'],
            'department' => ['nullable', 'string', 'max:150'],
            'terminationType' => ['required', 'string', 'max:150'],
            'terminationReasonCode' => ['nullable', 'string', 'max:64', 'in:'.implode(',', HcmTermination::TERMINATION_REASON_CODES)],
            'legalBasisCode' => ['nullable', 'string', 'max:64', 'in:'.implode(',', HcmTermination::LEGAL_BASIS_CODES)],
            'workflowStage' => ['nullable', 'string', 'max:64', 'in:'.implode(',', HcmTermination::WORKFLOW_STAGES)],
            'reason' => ['required', 'string', 'max:2000'],
            'noticeDate' => ['required', 'date'],
            'terminationDate' => ['required', 'date', 'after_or_equal:noticeDate'],
            'status' => ['nullable', 'string', 'in:'.implode(',', self::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'settlementPayrollPeriod' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'finalSalaryAmount' => ['nullable', 'numeric', 'min:0'],
            'finalAllowanceAmount' => ['nullable', 'numeric', 'min:0'],
            'finalDeductionAmount' => ['nullable', 'numeric', 'min:0'],
            'assetReturnNotes' => ['nullable', 'string', 'max:2000'],
            'clearanceNotes' => ['nullable', 'string', 'max:2000'],
            'settlementBreakdown' => ['nullable', 'array'],
            'clearanceItems' => ['nullable', 'array'],
            'nonAssetChecklist' => ['nullable', 'array'],
        ]);

        if ($finalizedError = $this->validateFinalizedFields($v)) {
            return $finalizedError;
        }

        $workflowStage = $this->resolveWorkflowStage(
            $v['workflowStage'] ?? null,
            $v['status'] ?? null,
        );

        $resolvedUserId = $this->resolveUserIdFromUuid((string) $v['userId'], $activeCompanyId);
        if ($resolvedUserId === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The selected user id is invalid for the active company.',
                ],
            ], 422);
        }

        $snapshotPayload = $this->buildSettlementSnapshotPayload(
            $activeCompanyId,
            $resolvedUserId,
            (string) $v['terminationDate'],
            $v
        );

        $policyPayload = $this->resolvePolicyProfilePayload(
            $v['terminationReasonCode'] ?? null,
            $v['legalBasisCode'] ?? null,
        );
        $workflowPayload = $this->buildWorkflowPayloadOnCreate($workflowStage, (int) $request->user()->id);

        $t = HcmTermination::query()->create([
            'company_id' => $activeCompanyId,
            'user_id' => $resolvedUserId,
            'department' => isset($v['department']) ? trim((string) $v['department']) : null,
            'termination_type' => trim((string) $v['terminationType']),
            'termination_reason_code' => $this->cleanNullableString($v['terminationReasonCode'] ?? null),
            'legal_basis_code' => $this->cleanNullableString($v['legalBasisCode'] ?? null),
            ...$policyPayload,
            ...$workflowPayload,
            'reason' => trim((string) $v['reason']),
            'notice_date' => $v['noticeDate'],
            'termination_date' => $v['terminationDate'],
            'status' => $this->statusFromWorkflowStage($workflowStage),
            'notes' => isset($v['notes']) ? trim((string) $v['notes']) : null,
            ...$snapshotPayload,
        ]);

        // Notify configured approvers when termination enters draft_review stage
        if ($workflowStage === 'draft_review') {
            $approvers = $this->approvalConfigService->resolveApproversToNotify($activeCompanyId, 'termination');
            foreach ($approvers as $approver) {
                $approver->notify(new TerminationApprovalRequestedNotification($t));
            }
        }

        return response()->json(['success' => true, 'data' => ['id' => $t->id]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'termination.manage')) {
            return $forbidden;
        }

        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $t = HcmTermination::query()
            ->where('company_id', $activeCompanyId)
            ->findOrFail($id);

        $v = $request->validate([
            'userId' => ['sometimes', 'required', 'uuid', 'exists:users,uuid'],
            'department' => ['sometimes', 'nullable', 'string', 'max:150'],
            'terminationType' => ['sometimes', 'required', 'string', 'max:150'],
            'terminationReasonCode' => ['sometimes', 'nullable', 'string', 'max:64', 'in:'.implode(',', HcmTermination::TERMINATION_REASON_CODES)],
            'legalBasisCode' => ['sometimes', 'nullable', 'string', 'max:64', 'in:'.implode(',', HcmTermination::LEGAL_BASIS_CODES)],
            'workflowStage' => ['sometimes', 'nullable', 'string', 'max:64', 'in:'.implode(',', HcmTermination::WORKFLOW_STAGES)],
            'workflowVersion' => ['sometimes', 'nullable', 'integer', 'min:0'], // Slice B — optimistic lock
            'reason' => ['sometimes', 'required', 'string', 'max:2000'],
            'noticeDate' => ['sometimes', 'required', 'date'],
            'terminationDate' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'required', 'string', 'in:'.implode(',', self::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'settlementPayrollPeriod' => ['sometimes', 'nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'finalSalaryAmount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'finalAllowanceAmount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'finalDeductionAmount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'assetReturnNotes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'clearanceNotes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'settlementBreakdown' => ['sometimes', 'nullable', 'array'],
            'clearanceItems' => ['sometimes', 'nullable', 'array'],
            'nonAssetChecklist' => ['sometimes', 'nullable', 'array'],
            'manualLeavePayoutConfirmed' => ['sometimes', 'boolean'], // Slice A — Anomaly #4 override
        ]);

        $effectiveValues = array_merge([
            'status' => $t->status,
            'workflowStage' => $t->workflow_stage,
            'settlementPayrollPeriod' => $t->settlement_payroll_period,
            'finalSalaryAmount' => $t->final_salary_amount,
            'finalAllowanceAmount' => $t->final_allowance_amount,
            'finalDeductionAmount' => $t->final_deduction_amount,
            'assetReturnNotes' => $t->asset_return_notes,
            'clearanceNotes' => $t->clearance_notes,
            'settlementBreakdown' => $t->settlement_breakdown,
            'clearanceItems' => $t->clearance_items,
            'nonAssetChecklist' => $t->non_asset_checklist,
        ], $v);

        if ($finalizedError = $this->validateFinalizedFields($effectiveValues, $t)) {
            return $finalizedError;
        }

        // Slice B — Optimistic lock: check workflow_version if provided (Anomaly #2)
        if (array_key_exists('workflowVersion', $v)) {
            $versionConflict = $this->workflowValidator->validateVersion($t, isset($v['workflowVersion']) ? (int) $v['workflowVersion'] : null);
            if ($versionConflict !== null) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'WORKFLOW_VERSION_CONFLICT',
                        'message' => $versionConflict,
                    ],
                ], 409);
            }
        }

        if (array_key_exists('workflowStage', $v) || array_key_exists('status', $v)) {
            $requestedWorkflowStage = $this->resolveWorkflowStage(
                $v['workflowStage'] ?? null,
                $v['status'] ?? null,
            );
            if (! $this->isWorkflowTransitionAllowed($t->workflow_stage, $requestedWorkflowStage)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Workflow stage transition is not allowed.',
                    ],
                ], 422);
            }
        }

        if (isset($v['noticeDate'], $v['terminationDate'])) {
            if (strtotime((string) $v['terminationDate']) < strtotime((string) $v['noticeDate'])) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The termination date must be on or after the notice date.',
                    ],
                ], 422);
            }
        } elseif (isset($v['terminationDate'])) {
            if ($t->notice_date && $t->notice_date->gt(Carbon::parse($v['terminationDate']))) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The termination date must be on or after the notice date.',
                    ],
                ], 422);
            }
        } elseif (isset($v['noticeDate'])) {
            if ($t->termination_date && Carbon::parse($v['noticeDate'])->gt($t->termination_date)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The termination date must be on or after the notice date.',
                    ],
                ], 422);
            }
        }

        $payload = [];
        if (array_key_exists('userId', $v)) {
            $resolvedUserId = $this->resolveUserIdFromUuid((string) $v['userId'], $activeCompanyId);
            if ($resolvedUserId === null) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'The selected user id is invalid for the active company.',
                    ],
                ], 422);
            }
            $payload['user_id'] = $resolvedUserId;
        }
        if (array_key_exists('department', $v)) {
            $payload['department'] = $v['department'] !== null ? trim((string) $v['department']) : null;
        }
        if (array_key_exists('terminationType', $v)) {
            $payload['termination_type'] = trim((string) $v['terminationType']);
        }
        if (array_key_exists('terminationReasonCode', $v)) {
            $payload['termination_reason_code'] = $this->cleanNullableString($v['terminationReasonCode']);
        }
        if (array_key_exists('legalBasisCode', $v)) {
            $payload['legal_basis_code'] = $this->cleanNullableString($v['legalBasisCode']);
        }
        if (array_key_exists('terminationReasonCode', $v) || array_key_exists('legalBasisCode', $v)) {
            $payload = array_merge($payload, $this->resolvePolicyProfilePayload(
                $v['terminationReasonCode'] ?? $t->termination_reason_code,
                $v['legalBasisCode'] ?? $t->legal_basis_code,
            ));
        }
        if (array_key_exists('workflowStage', $v) || array_key_exists('status', $v)) {
            $nextWorkflowStage = $this->resolveWorkflowStage(
                $v['workflowStage'] ?? null,
                $v['status'] ?? null,
            );
            $payload['workflow_stage'] = $nextWorkflowStage;
            $payload['status'] = $this->statusFromWorkflowStage($nextWorkflowStage);
            $payload = array_merge($payload, $this->buildWorkflowPayloadOnUpdate(
                $t,
                $nextWorkflowStage,
                (int) $request->user()->id,
            ));

            // Slice B — Append audit event to workflow_history + increment version (Anomaly #2)
            $currentStage = $t->workflow_stage ?: $this->workflowStageFromStatus($t->status);
            if ($currentStage !== $nextWorkflowStage) {
                $actor = $request->user();
                $auditEvent = WorkflowAuditEvent::make(
                    previousStage: $currentStage,
                    newStage:      $nextWorkflowStage,
                    action:        $this->workflowValidator->stageToAction($nextWorkflowStage),
                    actor:         $actor,
                    note:          $this->cleanNullableString($v['notes'] ?? null),
                );
                $payload['workflow_history'] = $this->workflowValidator->appendHistory($t, $auditEvent);
                $payload['workflow_version'] = ((int) ($t->workflow_version ?? 0)) + 1;
            }
        }
        if (array_key_exists('reason', $v)) {
            $payload['reason'] = trim((string) $v['reason']);
        }
        if (array_key_exists('noticeDate', $v)) {
            $payload['notice_date'] = $v['noticeDate'];
        }
        if (array_key_exists('terminationDate', $v)) {
            $payload['termination_date'] = $v['terminationDate'];
        }
        if (array_key_exists('status', $v)) {
            $payload['status'] = $v['status'];
        }
        if (array_key_exists('notes', $v)) {
            $payload['notes'] = $v['notes'] !== null ? trim((string) $v['notes']) : null;
        }

        $snapshotFields = [
            'status',
            'workflowStage',
            'terminationDate',
            'settlementPayrollPeriod',
            'finalSalaryAmount',
            'finalAllowanceAmount',
            'finalDeductionAmount',
            'assetReturnNotes',
            'clearanceNotes',
            'settlementBreakdown',
            'clearanceItems',
            'nonAssetChecklist',
        ];

        $shouldRefreshSnapshot = $payload['status'] ?? $t->status;
        $shouldRefreshSnapshot = $shouldRefreshSnapshot === 'finalized'
            || count(array_intersect(array_keys($v), $snapshotFields)) > 0;

        if ($shouldRefreshSnapshot) {
            $resolvedUserId = (int) ($payload['user_id'] ?? $t->user_id);
            $terminationDate = (string) ($payload['termination_date'] ?? $t->termination_date?->toDateString() ?? now()->toDateString());
            $payload = array_merge($payload, $this->buildSettlementSnapshotPayload(
                $activeCompanyId,
                $resolvedUserId,
                $terminationDate,
                $effectiveValues
            ));
        } elseif (array_key_exists('settlementBreakdown', $v)) {
            $payload['settlement_breakdown'] = $this->normalizeNullableArray($v['settlementBreakdown']);
        } elseif (array_key_exists('clearanceItems', $v)) {
            $payload['clearance_items'] = $this->normalizeNullableArray($v['clearanceItems']);
        }

        if ($payload !== []) {
            // Anomaly #2 — wrap save in transaction when workflow_history is being mutated
            // to prevent concurrent writes from corrupting the JSON history array.
            if (array_key_exists('workflow_history', $payload)) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($t, $payload): void {
                    // Re-fetch with write lock so concurrent requests must queue behind this write.
                    $locked = HcmTermination::lockForUpdate()->findOrFail($t->id);
                    $locked->update($payload);
                });
            } else {
                $t->update($payload);
            }
        }

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'termination.manage')) {
            return $forbidden;
        }

        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        // Anomaly #3: block delete for approved/finalized records — fetch first, then guard
        $termination = HcmTermination::query()
            ->where('company_id', $activeCompanyId)
            ->whereKey($id)
            ->first();

        if (! $termination) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Termination record not found.']], 404);
        }

        if (in_array($termination->status, ['approved', 'finalized'], true)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DELETE_FORBIDDEN_STATUS',
                    'message' => 'Cannot delete a termination record with status "'.$termination->status.'". Only draft or pending records may be deleted.',
                ],
            ], 403);
        }

        $termination->delete();

        return response()->json(['success' => true]);
    }

}
