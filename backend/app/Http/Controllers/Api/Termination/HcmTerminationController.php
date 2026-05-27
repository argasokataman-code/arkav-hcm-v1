<?php

namespace App\Http\Controllers\Api\Termination;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
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
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HcmTerminationController extends Controller
{
    use ChecksPermissions;

    private const STATUSES = ['pending', 'approved', 'finalized', 'cancelled'];
    private const POLICY_FORMULA_VERSION = '2026.04.id.v1';

    public function __construct(
        private readonly AssetService $assetService,
        private readonly PkwtCompensationService $pkwtCompensationService,
        private readonly TerminationSettlementCalculationService $settlementCalculator,
        private readonly TerminationWorkflowValidator $workflowValidator,
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

    public function settlementPreviewByUser(Request $request): JsonResponse
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
            'terminationDate' => ['nullable', 'date'],
        ]);

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

        $user = User::query()->findOrFail($resolvedUserId);

        return response()->json([
            'success' => true,
            'data' => $this->buildSettlementPreviewData(
                $activeCompanyId,
                $user,
                isset($v['terminationDate']) ? (string) $v['terminationDate'] : null,
                false
            ),
        ]);
    }

    public function settlementPreview(Request $request, int $id): JsonResponse
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

        $termination = HcmTermination::query()
            ->where('company_id', $activeCompanyId)
            ->with(['user:id,name,email'])
            ->find($id);

        if (! $termination || ! $termination->user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TERMINATION_NOT_FOUND',
                    'message' => 'Termination not found.',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->buildSettlementPreviewData(
                $activeCompanyId,
                $termination->user,
                $termination->termination_date?->toDateString(),
                false
            ),
        ]);
    }

    public function returnClearanceItem(Request $request, int $id, int $assignmentId): JsonResponse
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

        $termination = HcmTermination::query()
            ->where('company_id', $activeCompanyId)
            ->with([
                'user:id,name,email',
                'settlementPayrollPeriodRef:id,period_year,period_month,status',
                'workflowReviewedBy:id,name,email',
                'workflowApprovedBy:id,name,email',
                'workflowFinalizedBy:id,name,email',
            ])
            ->find($id);

        if (! $termination || ! $termination->user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TERMINATION_NOT_FOUND',
                    'message' => 'Termination not found.',
                ],
            ], 404);
        }

        $assignment = AssetAssignment::query()
            ->where('company_id', $activeCompanyId)
            ->whereKey($assignmentId)
            ->where('active_token', 'active')
            ->whereNull('returned_date')
            ->with('asset:id,company_id,condition')
            ->first();

        if (! $assignment || ! $assignment->asset) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TERMINATION_CLEARANCE_ITEM_NOT_FOUND',
                    'message' => 'Clearance item not found or already returned.',
                ],
            ], 404);
        }

        $profile = EmployeeProfile::query()
            ->where('company_id', $activeCompanyId)
            ->where('user_id', $termination->user_id)
            ->first();

        if (! $profile || (int) $assignment->employee_id !== (int) $profile->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TERMINATION_CLEARANCE_ITEM_MISMATCH',
                    'message' => 'Clearance item does not belong to the termination employee.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'returnedDate' => ['nullable', 'date'],
            'conditionAtReturn' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $returnedAssignment = $this->assetService->returnAsset(
            Asset::query()->findOrFail($assignment->asset_id),
            [
                'returned_date' => $validated['returnedDate'] ?? now()->toDateString(),
                'condition_at_return' => $validated['conditionAtReturn'] ?? ($assignment->asset->condition ?? 'good'),
                'notes' => $validated['notes'] ?? 'Returned from termination clearance.',
            ],
            (int) $request->user()->id,
        );

        $snapshotPayload = $this->buildSettlementSnapshotPayload(
            $activeCompanyId,
            (int) $termination->user_id,
            (string) ($termination->termination_date?->toDateString() ?? now()->toDateString()),
            [
                'status' => $termination->status,
                'settlementPayrollPeriod' => $termination->settlement_payroll_period,
                'finalSalaryAmount' => $termination->final_salary_amount,
                'finalAllowanceAmount' => $termination->final_allowance_amount,
                'finalDeductionAmount' => $termination->final_deduction_amount,
                'assetReturnNotes' => null,
                'clearanceNotes' => $termination->clearance_notes,
                'settlementBreakdown' => null,
                'clearanceItems' => null,
            ]
        );

        $termination->update($snapshotPayload);
        $termination->load(['user:id,name,email', 'settlementPayrollPeriodRef:id,period_year,period_month,status']);

        return response()->json([
            'success' => true,
            'data' => [
                'returnedAssignmentId' => $returnedAssignment->id,
                'termination' => $this->payload($termination),
            ],
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

    private function payload(HcmTermination $t): array
    {
        $breakdown = $this->normalizeListForResponse($t->settlement_breakdown);
        $clearanceItems = $this->normalizeListForResponse($t->clearance_items);
        $nonAssetChecklist = $this->normalizeChecklistForResponse($t->non_asset_checklist);
        $hasSettlement = $t->settlement_payroll_period !== null
            || $t->settlement_payroll_period_id !== null
            || $t->final_salary_amount !== null
            || $t->final_allowance_amount !== null
            || $t->final_deduction_amount !== null
            || $t->asset_return_notes !== null
            || $t->clearance_notes !== null
            || $breakdown !== []
            || $clearanceItems !== []
            || $nonAssetChecklist !== [];

        $salary = $this->decimalString($t->final_salary_amount);
        $allowance = $this->decimalString($t->final_allowance_amount);
        $deduction = $this->decimalString($t->final_deduction_amount);
        $period = $t->relationLoaded('settlementPayrollPeriodRef') ? $t->settlementPayrollPeriodRef : null;

        return [
            'id' => $t->id,
            'employee' => $t->user ? ['id' => $t->user->id, 'name' => $t->user->name, 'email' => $t->user->email] : null,
            'department' => $t->department ?? '',
            'terminationType' => $t->termination_type ?? '',
            'terminationReasonCode' => $t->termination_reason_code,
            'legalBasisCode' => $t->legal_basis_code,
            'policyProfileKey' => $t->policy_profile_key,
            'policyFormulaVersion' => $t->policy_formula_version,
            'workflowStage' => $t->workflow_stage ?? $this->workflowStageFromStatus($t->status),
            'workflow' => [
                'stage' => $t->workflow_stage ?? $this->workflowStageFromStatus($t->status),
                'version' => (int) ($t->workflow_version ?? 0), // Slice B — clients echo back for optimistic lock
                'history' => is_array($t->workflow_history) ? $t->workflow_history : [],
                'reviewed' => $this->workflowActorPayload($t->workflowReviewedBy, $t->workflow_reviewed_at),
                'approved' => $this->workflowActorPayload($t->workflowApprovedBy, $t->workflow_approved_at),
                'finalized' => $this->workflowActorPayload($t->workflowFinalizedBy, $t->workflow_finalized_at),
            ],
            'reason' => $t->reason ?? '',
            'noticeDate' => $t->notice_date?->toDateString(),
            'terminationDate' => $t->termination_date?->toDateString(),
            'status' => $t->status ?? 'pending',
            'notes' => $t->notes ?? '',
            'settlement' => $hasSettlement ? [
                'payrollPeriod' => $t->settlement_payroll_period,
                'payrollPeriodId' => $t->settlement_payroll_period_id,
                'payrollPeriodStatus' => $period?->status,
                'finalSalaryAmount' => $salary,
                'finalAllowanceAmount' => $allowance,
                'finalDeductionAmount' => $deduction,
                'finalNetAmount' => $this->calculateNetAmount($salary, $allowance, $deduction),
                'assetReturnNotes' => $t->asset_return_notes,
                'clearanceNotes' => $t->clearance_notes,
                'breakdown' => $breakdown,
                'evidenceSnapshot' => is_array($t->settlement_evidence_snapshot) ? $t->settlement_evidence_snapshot : null, // Slice A
                'leaveBalanceAvailable' => $t->leave_balance_available, // Slice A
                'clearanceItems' => $clearanceItems,
                'clearanceOutstandingCount' => count($clearanceItems),
                'nonAssetChecklist' => $nonAssetChecklist,
                'policyProfile' => $this->policyProfileSummary($t->policy_profile_key),
            ] : null,
            'createdAt' => $t->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{policy_profile_key:?string,policy_formula_version:?string}
     */
    private function resolvePolicyProfilePayload(mixed $reasonCode, mixed $legalBasisCode): array
    {
        $reason = $this->cleanNullableString($reasonCode);
        $legalBasis = $this->cleanNullableString($legalBasisCode);

        if ($reason === null && $legalBasis === null) {
            return [
                'policy_profile_key' => null,
                'policy_formula_version' => null,
            ];
        }

        return [
            'policy_profile_key' => $this->resolvePolicyProfileKey($reason, $legalBasis),
            'policy_formula_version' => self::POLICY_FORMULA_VERSION,
        ];
    }

    private function resolvePolicyProfileKey(?string $reasonCode, ?string $legalBasisCode): string
    {
        if ($reasonCode === 'contract_end' && $legalBasisCode === 'pkwt_contract') {
            return 'pkwt_end_of_contract';
        }
        if ($reasonCode === 'retirement') {
            return 'retirement';
        }
        if (in_array($reasonCode, ['company_efficiency', 'company_closure', 'force_majeure'], true)) {
            return 'company_termination';
        }
        if (in_array($reasonCode, ['misconduct', 'court_order'], true)) {
            return 'disciplinary_or_court';
        }
        if ($reasonCode === 'death') {
            return 'deceased_employee';
        }
        if ($reasonCode === 'long_term_illness') {
            return 'medical_termination';
        }

        return 'general_other';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function policyProfileSummary(?string $policyProfileKey): ?array
    {
        if ($policyProfileKey === null || trim($policyProfileKey) === '') {
            return null;
        }

        $components = [
            'severancePay' => in_array($policyProfileKey, ['retirement', 'company_termination', 'general_other', 'medical_termination'], true),
            'longServiceAward' => in_array($policyProfileKey, ['retirement', 'company_termination', 'general_other', 'medical_termination'], true),
            'rightsCompensation' => true,
            'pkwtCompensation' => $policyProfileKey === 'pkwt_end_of_contract',
        ];

        return [
            'key' => $policyProfileKey,
            'formulaVersion' => self::POLICY_FORMULA_VERSION,
            'components' => $components,
        ];
    }

    private function validateFinalizedFields(array $values, ?HcmTermination $termination = null): ?JsonResponse
    {
        $effectiveStatus = $values['status'] ?? $this->statusFromWorkflowStage($values['workflowStage'] ?? null);
        if ($effectiveStatus !== 'finalized') {
            return null;
        }

        $requiredFields = [
            'clearanceNotes' => 'Clearance notes are required when status is finalized.',
        ];

        foreach ($requiredFields as $field => $message) {
            $value = $values[$field] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => $message,
                    ],
                ], 422);
            }
        }

        $checklist = $this->normalizeChecklistForResponse($values['nonAssetChecklist'] ?? null);
        if ($checklist !== []) {
            $incompleteMandatory = array_values(array_filter($checklist, function (array $item): bool {
                return ($item['mandatory'] ?? false) === true && ($item['status'] ?? 'pending') !== 'completed';
            }));

            if ($incompleteMandatory !== []) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'All mandatory non-asset checklist items must be completed before finalization.',
                    ],
                ], 422);
            }
        }

        // Gap 3 — Check DB checklist items: block finalization if any mandatory item is still open
        if ($termination !== null) {
            $openMandatoryCount = $termination->checklistItems()
                ->where('mandatory', true)
                ->where('status', 'open')
                ->count();
            if ($openMandatoryCount > 0) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'MANDATORY_CHECKLIST_INCOMPLETE',
                        'message' => "Cannot finalize: {$openMandatoryCount} mandatory checklist item(s) are still open.",
                    ],
                ], 422);
            }
        }

        // Slice A — Anomaly #4: if leave balance was unavailable, require explicit admin confirmation
        if (isset($values['leave_balance_available']) && $values['leave_balance_available'] === false) {
            $confirmed = isset($values['manualLeavePayoutConfirmed']) && $values['manualLeavePayoutConfirmed'] === true;
            if (! $confirmed) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'LEAVE_BALANCE_UNCONFIRMED',
                        'message' => 'Leave balance was unavailable during calculation. Set manualLeavePayoutConfirmed=true to confirm manual override before finalization.',
                    ],
                ], 422);
            }
        }

        return null;
    }

    private function buildSettlementSnapshotPayload(int $companyId, int $userId, string $terminationDate, array $values): array
    {
        $status = (string) ($values['status'] ?? 'pending');
        $manualPayload = [
            'settlement_payroll_period_id' => null,
            'settlement_payroll_period' => $this->cleanNullableString($values['settlementPayrollPeriod'] ?? null),
            'final_salary_amount' => $this->normalizeNullableAmount($values['finalSalaryAmount'] ?? null),
            'final_allowance_amount' => $this->normalizeNullableAmount($values['finalAllowanceAmount'] ?? null),
            'final_deduction_amount' => $this->normalizeNullableAmount($values['finalDeductionAmount'] ?? null),
            'asset_return_notes' => $this->cleanNullableString($values['assetReturnNotes'] ?? null),
            'clearance_notes' => $this->cleanNullableString($values['clearanceNotes'] ?? null),
            'settlement_breakdown' => $this->normalizeNullableArray($values['settlementBreakdown'] ?? null),
            'clearance_items' => $this->normalizeNullableArray($values['clearanceItems'] ?? null),
            'non_asset_checklist' => $this->normalizeChecklistForStorage($values['nonAssetChecklist'] ?? null),
        ];

        if ($status !== 'finalized') {
            return $manualPayload;
        }

        $user = User::query()->findOrFail($userId);
        $policyProfileKey = $this->cleanNullableString($values['policyProfileKey'] ?? null)
            ?? $this->resolvePolicyProfileKey(
                $this->cleanNullableString($values['terminationReasonCode'] ?? null),
                $this->cleanNullableString($values['legalBasisCode'] ?? null),
            );

        $preview = $this->buildSettlementPreviewData($companyId, $user, $terminationDate, true);
        $summary = $preview['summary'] ?? [];
        $resolvedPeriod = $preview['resolvedPeriod'] ?? [];
        $clearance = $preview['clearance'] ?? [];

        // Slice A — Enrich settlement breakdown with severance, UPMK, UPH, leave payout
        $enriched = $this->settlementCalculator->calculate(
            companyId:        $companyId,
            userId:           $userId,
            terminationDate:  $terminationDate,
            policyProfileKey: $policyProfileKey,
        );

        // Merge existing prorata items + enriched items (severance, UPMK, UPH, leave)
        // settlement_breakdown stays as a flat array of line items (API backward-compat).
        // Enriched totals/metadata live in settlement_evidence_snapshot.
        $baseItems   = $this->normalizeNullableArray($preview['breakdown'] ?? null) ?? [];
        $mergedItems = array_values(array_merge($baseItems, $enriched->lineItems));

        $evidenceSnapshot = array_merge($enriched->toEvidenceSnapshotArray(), [
            'totalGross'           => number_format($enriched->totalGross, 2, '.', ''),
            'totalDeduction'       => number_format($enriched->totalDeduction, 2, '.', ''),
            'netPayable'           => number_format($enriched->netPayable, 2, '.', ''),
            'calculationMethod'    => $enriched->calculationMethod,
            'policyProfileKey'     => $enriched->policyProfileKey,
            'leaveBalanceAvailable'=> $enriched->leaveBalanceAvailable,
            'leavePayout'          => $enriched->leavePayout !== null
                ? number_format($enriched->leavePayout, 2, '.', '')
                : null,
            'source'               => ($preview['source'] ?? 'termination_policy_prorated').'_plus_enriched',
        ]);

        return [
            'settlement_payroll_period_id' => $resolvedPeriod['id'] ?? null,
            'settlement_payroll_period' => $this->cleanNullableString($values['settlementPayrollPeriod'] ?? null)
                ?? ($resolvedPeriod['label'] ?? null),
            'final_salary_amount' => $this->normalizeNullableAmount($values['finalSalaryAmount'] ?? ($summary['finalSalaryAmount'] ?? null)),
            'final_allowance_amount' => $this->normalizeNullableAmount($values['finalAllowanceAmount'] ?? ($summary['finalAllowanceAmount'] ?? null)),
            'final_deduction_amount' => $this->normalizeNullableAmount($values['finalDeductionAmount'] ?? ($summary['finalDeductionAmount'] ?? null)),
            'asset_return_notes' => $this->cleanNullableString($values['assetReturnNotes'] ?? null)
                ?? $this->cleanNullableString($clearance['summaryNotes'] ?? null),
            'clearance_notes' => $this->cleanNullableString($values['clearanceNotes'] ?? null),
            // settlement_breakdown = flat list of line items (backward-compat)
            'settlement_breakdown' => $this->normalizeNullableArray($values['settlementBreakdown'] ?? null)
                ?? $mergedItems,
            'clearance_items' => $this->normalizeNullableArray($values['clearanceItems'] ?? null)
                ?? $this->normalizeNullableArray($clearance['items'] ?? null),
            'non_asset_checklist' => $this->normalizeChecklistForStorage($values['nonAssetChecklist'] ?? null),
            // Slice A — Evidence snapshot (includes enriched totals) + leave availability flag
            'settlement_evidence_snapshot' => $evidenceSnapshot,
            'leave_balance_available' => $enriched->leaveBalanceAvailable,
        ];
    }

    private function buildSettlementPreviewData(int $companyId, User $user, ?string $terminationDate, bool $allowCreatePeriod): array
    {
        $period = $this->resolveSettlementPeriod($companyId, $terminationDate, $allowCreatePeriod);
        $breakdown = $this->buildSettlementBreakdown($companyId, (int) $user->id, $period['period'] ?? null, $terminationDate);
        $clearance = $this->buildClearanceData($companyId, (int) $user->id);

        return [
            'employee' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'resolvedPeriod' => [
                'id' => $period['id'],
                'periodYear' => $period['periodYear'],
                'periodMonth' => $period['periodMonth'],
                'label' => $period['label'],
                'status' => $period['status'],
                'isExisting' => $period['isExisting'],
            ],
            'summary' => $breakdown['summary'],
            'source' => $breakdown['source'],
            'breakdown' => $breakdown['items'],
            'clearance' => $clearance,
        ];
    }

    /**
     * @return array{period:HcmPayrollPeriod|null,id:int|null,periodYear:int,periodMonth:int,label:string,status:string|null,isExisting:bool}
     */
    private function resolveSettlementPeriod(int $companyId, ?string $terminationDate, bool $allowCreatePeriod): array
    {
        $now = Carbon::now('Asia/Jakarta')->startOfMonth();
        $terminationMonth = $terminationDate
            ? Carbon::parse($terminationDate, 'Asia/Jakarta')->startOfMonth()
            : $now->copy();
        $target = $terminationMonth->lt($now) ? $now : $terminationMonth;

        $period = HcmPayrollPeriod::query()
            ->where('company_id', $companyId)
            ->where('period_year', (int) $target->year)
            ->where('period_month', (int) $target->month)
            ->first();

        if ($period === null && $allowCreatePeriod) {
            $period = HcmPayrollPeriod::query()->firstOrCreate([
                'company_id' => $companyId,
                'period_year' => (int) $target->year,
                'period_month' => (int) $target->month,
            ], [
                'status' => HcmPayrollPeriod::STATUS_OPEN,
            ]);
        }

        return [
            'period' => $period,
            'id' => $period?->id,
            'periodYear' => (int) $target->year,
            'periodMonth' => (int) $target->month,
            'label' => $target->format('Y-m'),
            'status' => $period?->status,
            'isExisting' => $period !== null,
        ];
    }

    /**
     * @return array{source:string,summary:array<string, string|null>,items:list<array<string, mixed>>}
     */
    private function buildSettlementBreakdown(int $companyId, int $userId, ?HcmPayrollPeriod $period, ?string $terminationDate): array
    {
        $run = null;
        $lines = collect();

        if ($period) {
            $run = HcmPayrollRun::query()
                ->where('company_id', $companyId)
                ->where('hcm_payroll_period_id', $period->id)
                ->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY)
                ->where('status', HcmPayrollRun::STATUS_FINALIZED)
                ->orderByDesc('id')
                ->first();

            if ($run === null) {
                $run = HcmPayrollRun::query()
                    ->where('company_id', $companyId)
                    ->where('hcm_payroll_period_id', $period->id)
                    ->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY)
                    ->where('status', HcmPayrollRun::STATUS_DRAFT)
                    ->orderByDesc('id')
                    ->first();
            }

            if ($run) {
                $lines = HcmPayrollLine::query()
                    ->where('hcm_payroll_run_id', $run->id)
                    ->where('user_id', $userId)
                    ->orderBy('sort_order')
                    ->get();
            }
        }

        $profile = EmployeeProfile::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->first();

        $terminationAt = $terminationDate
            ? Carbon::parse($terminationDate, 'Asia/Jakarta')->startOfDay()
            : Carbon::now('Asia/Jakarta')->startOfDay();
        $proration = $this->terminationProration($profile, $terminationAt);
        $baseSalary = (float) ($proration['salaryAmount'] ?? 0);
        $fixedAllowance = (float) ($proration['allowanceAmount'] ?? 0);

        $items = [];
        $items[] = [
            'componentCode' => 'termination_prorated_salary',
            'componentName' => 'Prorated final salary',
            'kind' => 'addition',
            'category' => 'termination_proration',
            'amount' => $this->decimalString($baseSalary),
            'bucket' => 'salary',
            'source' => 'termination_policy_proration',
            'meta' => [
                'workedDays' => $proration['workedDays'],
                'daysInMonth' => $proration['daysInMonth'],
                'terminationDate' => $terminationAt->toDateString(),
                'ratio' => $proration['ratio'],
            ],
        ];

        if ($fixedAllowance > 0) {
            $items[] = [
                'componentCode' => 'termination_prorated_fixed_allowance',
                'componentName' => 'Prorated fixed allowance',
                'kind' => 'addition',
                'category' => 'termination_proration',
                'amount' => $this->decimalString($fixedAllowance),
                'bucket' => 'allowance',
                'source' => 'termination_policy_proration',
                'meta' => [
                    'workedDays' => $proration['workedDays'],
                    'daysInMonth' => $proration['daysInMonth'],
                    'terminationDate' => $terminationAt->toDateString(),
                    'ratio' => $proration['ratio'],
                ],
            ];
        }

        $pkwtLine = $this->buildPkwtCompensationLine($profile, $terminationAt);
        if ($pkwtLine !== null) {
            $items[] = $pkwtLine;
        }

        if ($run && $lines->isNotEmpty()) {
            $referenceItems = $lines
                ->map(fn (HcmPayrollLine $line) => $this->serializeSettlementBreakdownLine($line, 'payroll_run_reference'))
                ->filter(fn (array $line): bool => ! $this->isBaseCompensationLine($line))
                ->values()
                ->all();

            $items = array_values(array_merge($items, $referenceItems));
        }

        return [
            'source' => $run && $lines->isNotEmpty()
                ? 'termination_policy_prorated_plus_payroll_reference'
                : ($pkwtLine !== null ? 'termination_policy_prorated_plus_pkwt' : 'termination_policy_prorated'),
            'summary' => $this->summarizeSettlementBreakdown($items),
            'items' => $items,
        ];
    }

    /**
     * @return array{items:list<array<string, mixed>>,outstandingCount:int,summaryNotes:string|null}
     */
    private function buildClearanceData(int $companyId, int $userId): array
    {
        $profile = EmployeeProfile::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->first();

        if (! $profile) {
            return [
                'items' => [],
                'outstandingCount' => 0,
                'summaryNotes' => null,
            ];
        }

        $items = AssetAssignment::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $profile->id)
            ->where('active_token', 'active')
            ->whereNull('returned_date')
            ->with(['asset:id,uuid,asset_code,name,condition,status'])
            ->orderByDesc('assigned_date')
            ->get()
            ->map(function (AssetAssignment $assignment): array {
                return [
                    'assignmentId' => $assignment->id,
                    'assetId' => $assignment->asset_id,
                    'assetUuid' => $assignment->asset?->uuid,
                    'assetCode' => $assignment->asset?->asset_code,
                    'assetName' => $assignment->asset?->name,
                    'assetCondition' => $assignment->asset?->condition,
                    'assetStatus' => $assignment->asset?->status,
                    'assignedDate' => $assignment->assigned_date?->toDateString(),
                    'notes' => $assignment->notes,
                    'status' => 'pending_return',
                    'actions' => [
                        'returnEndpoint' => '/v1/hcm/terminations/{terminationId}/clearance-items/'.$assignment->id.'/return',
                    ],
                ];
            })
            ->values()
            ->all();

        return [
            'items' => $items,
            'outstandingCount' => count($items),
            'summaryNotes' => $items !== []
                ? 'Outstanding asset clearance: '.implode(', ', array_map(function (array $item): string {
                    $code = trim((string) ($item['assetCode'] ?? ''));
                    $name = trim((string) ($item['assetName'] ?? 'Asset'));

                    return trim($code.' '.$name);
                }, $items))
                : 'No outstanding asset assignments.',
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, string|null>
     */
    private function summarizeSettlementBreakdown(array $items): array
    {
        $salary = 0.0;
        $allowance = 0.0;
        $deduction = 0.0;

        foreach ($items as $item) {
            $amount = (float) ($item['amount'] ?? 0);
            $bucket = (string) ($item['bucket'] ?? 'allowance');

            if ($bucket === 'salary') {
                $salary += $amount;
                continue;
            }
            if ($bucket === 'deduction') {
                $deduction += $amount;
                continue;
            }

            $allowance += $amount;
        }

        $salaryString = $this->decimalString($salary);
        $allowanceString = $this->decimalString($allowance);
        $deductionString = $this->decimalString($deduction);

        return [
            'finalSalaryAmount' => $salaryString,
            'finalAllowanceAmount' => $allowanceString,
            'finalDeductionAmount' => $deductionString,
            'finalNetAmount' => $this->calculateNetAmount($salaryString, $allowanceString, $deductionString),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSettlementBreakdownLine(HcmPayrollLine $line, string $source): array
    {
        $componentCode = (string) ($line->component_code ?? '');
        $category = (string) ($line->category ?? '');
        $bucket = 'allowance';

        if ($line->kind === 'deduction') {
            $bucket = 'deduction';
        } elseif (in_array($category, ['basic_wage', 'basic_salary'], true) || in_array($componentCode, ['upah_pokok', 'base_salary'], true)) {
            $bucket = 'salary';
        }

        return [
            'componentCode' => $componentCode,
            'componentName' => (string) ($line->component_name ?? 'Payroll component'),
            'kind' => (string) ($line->kind ?? 'addition'),
            'category' => $category,
            'amount' => $this->decimalString($line->amount),
            'bucket' => $bucket,
            'source' => $source,
            'meta' => is_array($line->meta) ? $line->meta : [],
        ];
    }

    private function terminationProration(?EmployeeProfile $profile, Carbon $terminationAt): array
    {
        $monthStart = $terminationAt->copy()->startOfMonth();
        $monthEnd = $terminationAt->copy()->endOfMonth();
        $employmentStart = $profile?->hire_date ? Carbon::parse($profile->hire_date)->startOfDay() : null;
        $workedStart = $employmentStart !== null && $employmentStart->greaterThan($monthStart)
            ? $employmentStart
            : $monthStart;
        $workedEnd = $terminationAt->lessThan($monthEnd) ? $terminationAt : $monthEnd;

        if ($workedEnd->lt($workedStart)) {
            return [
                'workedDays' => 0,
                'daysInMonth' => (int) $monthEnd->day,
                'ratio' => 0.0,
                'salaryAmount' => 0.0,
                'allowanceAmount' => 0.0,
            ];
        }

        $workedDays = $workedStart->diffInDays($workedEnd) + 1;
        $daysInMonth = (int) $monthEnd->day;
        $ratio = $daysInMonth > 0 ? $workedDays / $daysInMonth : 0.0;
        $baseMonthlySalary = max(0, (float) ($profile?->base_salary ?? 0));
        $fixedMonthlyAllowance = 0.0;

        return [
            'workedDays' => $workedDays,
            'daysInMonth' => $daysInMonth,
            'ratio' => round($ratio, 6),
            'salaryAmount' => round($baseMonthlySalary * $ratio, 2),
            'allowanceAmount' => round($fixedMonthlyAllowance * $ratio, 2),
        ];
    }

    private function buildPkwtCompensationLine(?EmployeeProfile $profile, Carbon $terminationAt): ?array
    {
        if (! $profile) {
            return null;
        }

        $summary = $this->pkwtCompensationService->summarizeProfile($profile, $terminationAt->copy()->startOfMonth());
        $amount = (float) ($summary['estimatedCompensationThisMonth'] ?? 0);
        if ($amount <= 0 || ! ($summary['isDueThisMonth'] ?? false)) {
            return null;
        }

        return [
            'componentCode' => 'termination_pkwt_compensation',
            'componentName' => 'PKWT completion compensation',
            'kind' => 'addition',
            'category' => 'termination_compensation',
            'amount' => $this->decimalString($amount),
            'bucket' => 'allowance',
            'source' => 'termination_policy_pkwt',
            'meta' => [
                'regulationReference' => PkwtCompensationService::REGULATION_LABEL,
                'contractType' => $summary['contractType'] ?? null,
                'contractStartDate' => $summary['contractStartDate'] ?? null,
                'contractEndDate' => $summary['contractEndDate'] ?? null,
                'monthsOfService' => $summary['monthsOfService'] ?? null,
                'multiplier' => $summary['multiplier'] ?? null,
            ],
        ];
    }

    private function isBaseCompensationLine(array $line): bool
    {
        $componentCode = (string) ($line['componentCode'] ?? '');
        $category = (string) ($line['category'] ?? '');

        return in_array($componentCode, ['upah_pokok', 'base_salary', 'tunjangan_tetap'], true)
            || in_array($category, ['basic_wage', 'basic_salary', 'fixed_allowance'], true);
    }

    private function normalizeNullableArray(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $normalized = array_values($value);

        return $normalized === [] ? null : $normalized;
    }

    private function normalizeListForResponse(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private function normalizeChecklistForStorage(mixed $value): ?array
    {
        $normalized = $this->normalizeChecklistForResponse($value);

        return $normalized === [] ? null : $normalized;
    }

    private function normalizeChecklistForResponse(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach (array_values($value) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $label = $this->cleanNullableString($item['label'] ?? null);
            if ($label === null) {
                continue;
            }

            $status = $this->cleanNullableString($item['status'] ?? null) ?? 'pending';
            if (! in_array($status, ['pending', 'completed'], true)) {
                $status = 'pending';
            }

            $normalized[] = [
                'label' => $label,
                'ownerName' => $this->cleanNullableString($item['ownerName'] ?? null),
                'dueDate' => $this->cleanNullableString($item['dueDate'] ?? null),
                'status' => $status,
                'completionEvidence' => $this->cleanNullableString($item['completionEvidence'] ?? null),
                'mandatory' => (bool) ($item['mandatory'] ?? false),
            ];
        }

        return $normalized;
    }

    private function cleanNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeNullableAmount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function decimalString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function calculateNetAmount(?string $salary, ?string $allowance, ?string $deduction): ?string
    {
        if ($salary === null || $allowance === null || $deduction === null) {
            return null;
        }

        return number_format(((float) $salary + (float) $allowance) - (float) $deduction, 2, '.', '');
    }

    private function resolveWorkflowStage(mixed $workflowStage, mixed $status): string
    {
        $stage = $this->cleanNullableString($workflowStage);
        if ($stage !== null) {
            return $stage;
        }

        return $this->workflowStageFromStatus($this->cleanNullableString($status));
    }

    private function workflowStageFromStatus(?string $status): string
    {
        return match ($status) {
            'approved' => 'approved_internal',
            'finalized' => 'finalized_execution',
            'cancelled' => 'cancelled',
            default => 'draft_review',
        };
    }

    private function statusFromWorkflowStage(mixed $workflowStage): string
    {
        return match ($workflowStage) {
            'approved_internal' => 'approved',
            'finalized_execution' => 'finalized',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }

    private function isWorkflowTransitionAllowed(?string $currentStage, string $nextStage): bool
    {
        $current = $currentStage ?: 'draft_review';

        if ($current === $nextStage) {
            return true;
        }

        // Strict sequential transitions — no stage skipping allowed (planning doc §5.2.1)
        return match ($current) {
            'draft_review'      => in_array($nextStage, ['legal_review', 'cancelled'], true),
            'legal_review'      => in_array($nextStage, ['approved_internal', 'cancelled'], true),
            'approved_internal' => in_array($nextStage, ['finalized_execution', 'cancelled'], true),
            'finalized_execution', 'cancelled' => false,
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWorkflowPayloadOnCreate(string $workflowStage, int $actorUserId): array
    {
        $payload = [
            'workflow_stage' => $workflowStage,
            'workflow_reviewed_by_user_id' => null,
            'workflow_reviewed_at' => null,
            'workflow_approved_by_user_id' => null,
            'workflow_approved_at' => null,
            'workflow_finalized_by_user_id' => null,
            'workflow_finalized_at' => null,
        ];

        $now = now();
        if (in_array($workflowStage, ['legal_review', 'approved_internal', 'finalized_execution'], true)) {
            $payload['workflow_reviewed_by_user_id'] = $actorUserId;
            $payload['workflow_reviewed_at'] = $now;
        }
        if (in_array($workflowStage, ['approved_internal', 'finalized_execution'], true)) {
            $payload['workflow_approved_by_user_id'] = $actorUserId;
            $payload['workflow_approved_at'] = $now;
        }
        if ($workflowStage === 'finalized_execution') {
            $payload['workflow_finalized_by_user_id'] = $actorUserId;
            $payload['workflow_finalized_at'] = $now;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWorkflowPayloadOnUpdate(HcmTermination $termination, string $nextStage, int $actorUserId): array
    {
        $payload = [];
        $current = $termination->workflow_stage ?: $this->workflowStageFromStatus($termination->status);
        if ($current === $nextStage) {
            return $payload;
        }

        $now = now();
        if (in_array($nextStage, ['legal_review', 'approved_internal', 'finalized_execution'], true) && $termination->workflow_reviewed_at === null) {
            $payload['workflow_reviewed_by_user_id'] = $actorUserId;
            $payload['workflow_reviewed_at'] = $now;
        }
        if (in_array($nextStage, ['approved_internal', 'finalized_execution'], true) && $termination->workflow_approved_at === null) {
            $payload['workflow_approved_by_user_id'] = $actorUserId;
            $payload['workflow_approved_at'] = $now;
        }
        if ($nextStage === 'finalized_execution' && $termination->workflow_finalized_at === null) {
            $payload['workflow_finalized_by_user_id'] = $actorUserId;
            $payload['workflow_finalized_at'] = $now;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function workflowActorPayload(?User $user, mixed $timestamp): ?array
    {
        if ($user === null && $timestamp === null) {
            return null;
        }

        return [
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
            'at' => $timestamp instanceof Carbon ? $timestamp->toIso8601String() : ($timestamp ? (string) $timestamp : null),
        ];
    }

    private function canManageTermination(Request $request): bool
    {
        return $this->hasAnyPermission($request, ['termination.manage', 'termination.view']);
    }

    private function resolveUserIdFromUuid(string $uuid, int $activeCompanyId): ?int
    {
        if (! Str::isUuid($uuid)) {
            return null;
        }

        $resolvedUserId = (int) (User::query()->where('uuid', $uuid)->value('id') ?? 0);
        if ($resolvedUserId <= 0) {
            return null;
        }

        $hasActiveMembership = CompanyUser::query()
            ->where('company_id', $activeCompanyId)
            ->where('user_id', $resolvedUserId)
            ->where('status', 'active')
            ->exists();

        return $hasActiveMembership ? $resolvedUserId : null;
    }
}
