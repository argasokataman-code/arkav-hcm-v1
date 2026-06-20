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

trait HandlesTerminationSettlementPreview
{
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
}
