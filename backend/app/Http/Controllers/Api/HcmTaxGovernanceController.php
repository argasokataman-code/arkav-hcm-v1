<?php

namespace App\Http\Controllers\Api;

use App\Events\TaxGovernancePolicyTransitioned;
use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\EmployeeTaxProfile;
use App\Models\HcmBillingTaxPolicy;
use App\Models\HcmSalaryComponent;
use App\Models\HcmTaxGovernanceBreakGlassRequest;
use App\Models\HcmTaxGovernancePolicy;
use App\Models\HcmTaxGovernancePolicyEvent;
use App\Models\HcmTaxGovernanceProjection;
use App\Models\HcmTaxGovernanceAnomaly;
use App\Models\User;
use App\Services\BillingTaxCalculationService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class HcmTaxGovernanceController extends Controller
{
    use ChecksPermissions;

    private const POLICY_REGULATION_SOURCE_TYPES = [
        'government_regulation',
        'ministry_regulation',
        'director_general_regulation',
        'company_policy_reference',
    ];

    private const POLICY_CALCULATION_METHODS = [
        'monthly_ter_lookup',
        'monthly_ter_with_year_end_reconciliation',
        'final_rate',
        'separate_calculation',
    ];

    private const POLICY_SCHEDULE_CATEGORIES = ['A', 'B', 'C', 'FINAL', 'SEPARATE', 'NON_OBJECT'];

    private const POLICY_SCHEDULE_MODES = [
        'ter_lookup',
        'fixed_rate_override',
        'final_rate',
        'separate_rate',
        'non_object',
    ];

    private const NUMERIC_POLICY_ID_DEPRECATION = 'true';

    private const NUMERIC_POLICY_ID_SUNSET_AT = '2026-07-26T00:00:00Z';

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.policy.view')) {
            return $response;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $this->ensureDefaultTenantPolicyTemplate($companyId, (int) ($request->user()?->id ?? 0) ?: null);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in([
                HcmTaxGovernancePolicy::STATUS_DRAFT,
                HcmTaxGovernancePolicy::STATUS_SUBMITTED,
                HcmTaxGovernancePolicy::STATUS_APPROVED,
                HcmTaxGovernancePolicy::STATUS_PUBLISHED,
                HcmTaxGovernancePolicy::STATUS_SUPERSEDED,
                HcmTaxGovernancePolicy::STATUS_VOID,
            ])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = HcmTaxGovernancePolicy::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $rows = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => collect($rows->items())->map(fn (HcmTaxGovernancePolicy $policy) => $this->policyPayload($policy))->values(),
                'meta' => [
                    'page' => $rows->currentPage(),
                    'perPage' => $rows->perPage(),
                    'total' => $rows->total(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.policy.draft.manage')) {
            return $response;
        }

        if ($response = $this->ensureTenantOwnerOrGlobalAdmin($request)) {
            return $response;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $validated = $this->validateUpsertRequest($request, true);
        $normalized = $this->normalizeUpsertPayload($validated);
        $actorId = (int) ($request->user()?->id ?? 0) ?: null;

        if (! empty($normalized['draftKey'])) {
            $existingDraft = HcmTaxGovernancePolicy::query()
                ->where('company_id', $companyId)
                ->where('draft_fingerprint', $normalized['draftKey'])
                ->first();

            if ($existingDraft) {
                return response()->json([
                    'success' => true,
                    'data' => $this->policyPayload($existingDraft),
                ]);
            }
        }

        $duplicateDraft = HcmTaxGovernancePolicy::query()
            ->where('company_id', $companyId)
            ->where('status', HcmTaxGovernancePolicy::STATUS_DRAFT)
            ->where('policy_code', strtoupper((string) $normalized['policyCode']))
            ->first();

        if ($duplicateDraft) {
            return $this->errorResponse('TAX_POLICY_DRAFT_EXISTS', 'A draft with the same policy code already exists for this tenant.', 409);
        }

        $policy = DB::transaction(function () use ($normalized, $companyId, $actorId): HcmTaxGovernancePolicy {
            $policy = HcmTaxGovernancePolicy::query()->create([
                'company_id' => $companyId,
                'policy_code' => strtoupper((string) $normalized['policyCode']),
                'name' => $normalized['name'],
                'status' => HcmTaxGovernancePolicy::STATUS_DRAFT,
                'draft_fingerprint' => $normalized['draftKey'],
                'effective_start_date' => $normalized['effectiveStartDate'],
                'effective_end_date' => $normalized['effectiveEndDate'] ?? null,
                'rules' => $normalized['rules'],
                'rate_schedules' => $normalized['rateSchedules'],
                'version' => 1,
                'created_by_user_id' => $actorId,
                'last_note' => null,
            ]);

            $this->recordEvent($policy, 'created', null, $this->policyStateSnapshot($policy), null, $actorId);

            return $policy;
        });

        // Dispatch event for projection sync
        TaxGovernancePolicyTransitioned::dispatch($policy, HcmTaxGovernancePolicy::STATUS_DRAFT, $policy->status, (int) ($actorId ?? 0));

        return response()->json([
            'success' => true,
            'data' => $this->policyPayload($policy),
        ], 201);
    }

    public function show(Request $request, string $policyRef): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.policy.view')) {
            return $response;
        }

        $usedNumericLegacy = false;
        $policy = $this->findPolicyForRequest($request, $policyRef, $usedNumericLegacy);
        if (! $policy) {
            return $this->errorResponse('TAX_POLICY_NOT_FOUND', 'Tax policy not found.', 404);
        }

        return $this->withNumericPolicyDeprecationHeaders(response()->json([
            'success' => true,
            'data' => $this->policyPayload($policy, true),
        ]), $usedNumericLegacy);
    }

    public function update(Request $request, string $policyRef): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.policy.draft.manage')) {
            return $response;
        }

        if ($response = $this->ensureTenantOwnerOrGlobalAdmin($request)) {
            return $response;
        }

        $usedNumericLegacy = false;
        $policy = $this->findPolicyForRequest($request, $policyRef, $usedNumericLegacy);
        if (! $policy) {
            return $this->errorResponse('TAX_POLICY_NOT_FOUND', 'Tax policy not found.', 404);
        }

        $validated = $this->validateUpsertRequest($request, false);
        $normalized = $this->normalizeUpsertPayload($validated, $policy);
        if (array_key_exists('version', $normalized) && (int) $normalized['version'] !== (int) $policy->version) {
            return $this->errorResponse('TAX_POLICY_VERSION_CONFLICT', 'Policy version conflict.', 409);
        }

        $before = $this->policyStateSnapshot($policy);
        $actorId = (int) ($request->user()?->id ?? 0) ?: null;

        DB::transaction(function () use ($policy, $normalized, $before, $actorId): void {
            $policy->fill([
                'policy_code' => strtoupper((string) $normalized['policyCode']),
                'name' => $normalized['name'],
                'effective_start_date' => $normalized['effectiveStartDate'],
                'effective_end_date' => $normalized['effectiveEndDate'] ?? null,
                'rules' => $normalized['rules'],
                'rate_schedules' => $normalized['rateSchedules'],
                'version' => (int) $policy->version + 1,
            ]);
            $policy->save();

            $this->recordEvent($policy, 'updated', $before, $this->policyStateSnapshot($policy), null, $actorId);
        });

        $policy->refresh();

        return $this->withNumericPolicyDeprecationHeaders(response()->json([
            'success' => true,
            'data' => $this->policyPayload($policy),
        ]), $usedNumericLegacy);
    }

    public function submit(Request $request, string $policyRef): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.policy.draft.manage')) {
            return $response;
        }

        if ($response = $this->ensureTenantOwnerOrGlobalAdmin($request)) {
            return $response;
        }

        $usedNumericLegacy = false;
        $policy = $this->findPolicyForRequest($request, $policyRef, $usedNumericLegacy);
        if (! $policy) {
            return $this->errorResponse('TAX_POLICY_NOT_FOUND', 'Tax policy not found.', 404);
        }

        if ($policy->status !== HcmTaxGovernancePolicy::STATUS_DRAFT) {
            return $this->errorResponse(
                'TAX_POLICY_INVALID_STATE_TRANSITION',
                'Only draft policies can be submitted.',
                422
            );
        }

        $validated = $request->validate([
            'submissionNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $actorId = (int) ($request->user()?->id ?? 0) ?: null;

        return $this->withNumericPolicyDeprecationHeaders(response()->json([
            'success' => true,
            'data' => $this->policyPayload($this->transitionPolicyStatus(
                $policy,
                HcmTaxGovernancePolicy::STATUS_SUBMITTED,
                'submitted',
                $actorId,
                $validated['submissionNote'] ?? null,
                [
                    'submitted_by_user_id' => $actorId,
                    'submitted_at' => now(),
                ],
            )),
        ]), $usedNumericLegacy);
    }

    public function approve(Request $request, string $policyRef): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.policy.draft.manage')) {
            return $response;
        }

        if ($response = $this->ensureTenantOwnerOrGlobalAdmin($request)) {
            return $response;
        }

        $usedNumericLegacy = false;
        $policy = $this->findPolicyForRequest($request, $policyRef, $usedNumericLegacy);
        if (! $policy) {
            return $this->errorResponse('TAX_POLICY_NOT_FOUND', 'Tax policy not found.', 404);
        }

        if ($policy->status !== HcmTaxGovernancePolicy::STATUS_SUBMITTED) {
            return $this->errorResponse(
                'TAX_POLICY_INVALID_STATE_TRANSITION',
                'Only submitted policies can be approved.',
                422
            );
        }

        $validated = $request->validate([
            'approvalNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $actorId = (int) ($request->user()?->id ?? 0) ?: null;

        return $this->withNumericPolicyDeprecationHeaders(response()->json([
            'success' => true,
            'data' => $this->policyPayload($this->transitionPolicyStatus(
                $policy,
                HcmTaxGovernancePolicy::STATUS_APPROVED,
                'approved',
                $actorId,
                $validated['approvalNote'] ?? null,
                [
                    'approved_by_user_id' => $actorId,
                    'approved_at' => now(),
                ],
            )),
        ]), $usedNumericLegacy);
    }

    public function reject(Request $request, string $policyRef): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.policy.draft.manage')) {
            return $response;
        }

        if ($response = $this->ensureTenantOwnerOrGlobalAdmin($request)) {
            return $response;
        }

        $usedNumericLegacy = false;
        $policy = $this->findPolicyForRequest($request, $policyRef, $usedNumericLegacy);
        if (! $policy) {
            return $this->errorResponse('TAX_POLICY_NOT_FOUND', 'Tax policy not found.', 404);
        }

        if (! in_array($policy->status, [HcmTaxGovernancePolicy::STATUS_SUBMITTED, HcmTaxGovernancePolicy::STATUS_APPROVED], true)) {
            return $this->errorResponse(
                'TAX_POLICY_INVALID_STATE_TRANSITION',
                'Only submitted or approved policies can be rejected back to draft.',
                422
            );
        }

        $validated = $request->validate([
            'rejectionNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $actorId = (int) ($request->user()?->id ?? 0) ?: null;

        return $this->withNumericPolicyDeprecationHeaders(response()->json([
            'success' => true,
            'data' => $this->policyPayload($this->transitionPolicyStatus(
                $policy,
                HcmTaxGovernancePolicy::STATUS_DRAFT,
                'rejected',
                $actorId,
                $validated['rejectionNote'] ?? null,
                [
                    'approved_by_user_id' => null,
                    'approved_by_user_uuid' => null,
                    'approved_at' => null,
                    'submitted_by_user_id' => null,
                    'submitted_by_user_uuid' => null,
                    'submitted_at' => null,
                ],
            )),
        ]), $usedNumericLegacy);
    }

    public function publish(Request $request, string $policyRef): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.policy.draft.manage')) {
            return $response;
        }

        if ($response = $this->ensureTenantOwnerOrGlobalAdmin($request)) {
            return $response;
        }

        $usedNumericLegacy = false;
        $policy = $this->findPolicyForRequest($request, $policyRef, $usedNumericLegacy);
        if (! $policy) {
            return $this->errorResponse('TAX_POLICY_NOT_FOUND', 'Tax policy not found.', 404);
        }

        $allowedFromStatuses = [
            HcmTaxGovernancePolicy::STATUS_DRAFT,
            HcmTaxGovernancePolicy::STATUS_SUBMITTED,
            HcmTaxGovernancePolicy::STATUS_APPROVED,
        ];
        if (! in_array($policy->status, $allowedFromStatuses, true)) {
            return $this->errorResponse(
                'TAX_POLICY_INVALID_STATE_TRANSITION',
                'Policy cannot be published from its current status: ' . $policy->status,
                422
            );
        }

        $before = $this->policyStateSnapshot($policy);
        $actorId = (int) ($request->user()?->id ?? 0) ?: null;
        $previousStatus = $policy->status;

        $validated = $request->validate([
            'publishReason' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($policy, $before, $actorId, $validated): void {
            $policy->status = HcmTaxGovernancePolicy::STATUS_PUBLISHED;
            $policy->published_at = now();
            $policy->published_by_user_id = $actorId;
            $policy->last_note = $validated['publishReason'] ?? $policy->last_note;
            $policy->version = (int) $policy->version + 1;
            $policy->save();
            HcmSalaryComponent::ensurePph21Components((int) $policy->company_id);
            $this->recordEvent($policy, 'published', $before, $this->policyStateSnapshot($policy), $validated['publishReason'] ?? null, $actorId);
        });

        $policy->refresh();

        TaxGovernancePolicyTransitioned::dispatch($policy, $previousStatus, $policy->status, (int) ($actorId ?? 0));

        return $this->withNumericPolicyDeprecationHeaders(response()->json([
            'success' => true,
            'data' => $this->policyPayload($policy),
        ]), $usedNumericLegacy);
    }

    public function tenantSelfAuditReport(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.report.export')) {
            return $response;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $validated = $request->validate([
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'format' => ['required', Rule::in(['json', 'csv', 'xlsx', 'pdf'])],
        ]);

        $summary = [
            'totalPolicies' => HcmTaxGovernancePolicy::query()->where('company_id', $companyId)->count(),
            'publishedPolicies' => HcmTaxGovernancePolicy::query()
                ->where('company_id', $companyId)
                ->where('status', HcmTaxGovernancePolicy::STATUS_PUBLISHED)
                ->count(),
            'eventsCount' => HcmTaxGovernancePolicyEvent::query()->where('company_id', $companyId)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'reportType' => 'tenant_self_audit',
                'generatedAt' => now()->toIso8601String(),
                'periodYear' => (int) $validated['period_year'],
                'periodMonth' => (int) $validated['period_month'],
                'format' => $validated['format'],
                'summary' => $summary,
            ],
        ]);
    }

    public function dashboardSummary(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.governance.dashboard.view_all')) {
            return $response;
        }

        $validated = $request->validate([
            'risk_level_filter' => ['nullable', Rule::in(['green', 'yellow', 'red'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 50);

        $query = HcmTaxGovernanceProjection::query()
            ->with(['company:id,name', 'lastActor:id,email'])
            ->orderByDesc('updated_at');

        if (!empty($validated['risk_level_filter'])) {
            $query->where('tenant_risk_level', $validated['risk_level_filter']);
        }

        $projections = $query->paginate($perPage);
        $companyIds = collect($projections->items())
            ->pluck('company_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $anomalyCountByCompany = HcmTaxGovernanceAnomaly::query()
            ->select('company_id')
            ->selectRaw('COUNT(*) as anomaly_count')
            ->selectRaw("SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_anomaly_count")
            ->whereNull('resolved_at')
            ->whereIn('company_id', $companyIds)
            ->groupBy('company_id')
            ->get()
            ->keyBy('company_id');

        // Build summary metrics
        $allProjections = HcmTaxGovernanceProjection::query()->get();
        $summary = [
            'total_tenants' => $allProjections->count(),
            'tenants_with_published_policy' => $allProjections->where('status', 'published')->count(),
            'tenants_with_draft_only' => $allProjections->where('status', 'draft')->count(),
            'tenants_with_no_policy' => 0, // Would query tenants without any projection
            'total_anomalies' => HcmTaxGovernanceAnomaly::whereNull('resolved_at')->count(),
            'critical_anomalies' => HcmTaxGovernanceAnomaly::whereNull('resolved_at')->where('severity', 'critical')->count(),
        ];

        $riskHeatmap = [
            'green' => $allProjections->where('tenant_risk_level', 'green')->count(),
            'yellow' => $allProjections->where('tenant_risk_level', 'yellow')->count(),
            'red' => $allProjections->where('tenant_risk_level', 'red')->count(),
        ];

        $billingMonth = now()->format('Y-m');
        $billingService = app(BillingTaxCalculationService::class);
        $billingReport = $billingService->generateCrossTenantMonthlyReport($billingMonth);
        $billingTaxHealth = [
            'billing_month' => $billingMonth,
            'tenant_count_with_policy' => (int) ($billingReport['summary']['tenant_count_with_policy'] ?? 0),
            'total_tax_due' => (float) ($billingReport['summary']['total_tax_due'] ?? 0),
            'total_invoice_amount' => (float) ($billingReport['summary']['total_invoice_amount'] ?? 0),
            'unpaid_invoice_count' => (int) ($billingReport['summary']['unpaid_invoice_count'] ?? 0),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'risk_heatmap' => $riskHeatmap,
                'billing_tax_health' => $billingTaxHealth,
                'tenants' => collect($projections->items())->map(function (HcmTaxGovernanceProjection $proj) use ($anomalyCountByCompany) {
                    $anomalyCounts = $anomalyCountByCompany->get($proj->company_id);

                    return [
                        'company_id' => $proj->company_id,
                        'company_name' => optional($proj->company)->name ?? 'Unknown',
                        'latest_policy_status' => $proj->status,
                        'latest_policy_version' => $proj->version,
                        'effective_since' => optional($proj->effective_date)?->toDateString(),
                        'policy_complexity_score' => $proj->policy_complexity_score,
                        'risk_level' => $proj->tenant_risk_level,
                        'anomaly_count' => (int) ($anomalyCounts->anomaly_count ?? 0),
                        'critical_anomaly_count' => (int) ($anomalyCounts->critical_anomaly_count ?? 0),
                        'last_change_at' => optional($proj->last_actor_timestamp)?->toIso8601String(),
                        'last_change_by' => optional($proj->lastActor)->email ?? 'System',
                    ];
                })->values(),
                'meta' => [
                    'page' => $projections->currentPage(),
                    'per_page' => $projections->perPage(),
                    'total' => $projections->total(),
                ],
            ],
        ]);
    }

    public function requestBreakGlassAccess(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.governance.break_glass.request')) {
            return $response;
        }

        if (! ($request->user()?->isGlobalHcmAdmin() ?? false)) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Access denied for this operation.', 403);
        }

        $validated = $request->validate([
            'targetTenantUuid' => ['required', 'string', 'uuid', 'exists:companies,uuid'],
            'reasonCode' => ['required', 'string', 'max:100'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $targetCompany = Company::query()->where('uuid', $validated['targetTenantUuid'])->firstOrFail();
        $record = HcmTaxGovernanceBreakGlassRequest::query()->create([
            'target_company_id' => (int) $targetCompany->id,
            'target_company_uuid' => (string) $targetCompany->uuid,
            'requested_by_user_id' => (int) ($request->user()?->id ?? 0) ?: null,
            'reason_code' => $validated['reasonCode'],
            'reason' => $validated['reason'],
            'status' => 'requested',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->breakGlassPayload($record),
        ], 201);
    }

    public function approveBreakGlassRequest(Request $request, string $requestUuid): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.governance.break_glass.approve')) {
            return $response;
        }

        if (! ($request->user()?->isGlobalHcmAdmin() ?? false)) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Access denied for this operation.', 403);
        }

        $validated = $request->validate([
            'approvalNote' => ['required', 'string', 'max:1000'],
            'expiresAt' => ['required', 'date', 'after:now'],
        ]);

        $record = HcmTaxGovernanceBreakGlassRequest::query()
            ->where('uuid', $requestUuid)
            ->first();

        if (! $record) {
            return $this->errorResponse('BREAK_GLASS_REQUEST_NOT_FOUND', 'Break-glass request not found.', 404);
        }

        if ($record->status !== 'requested') {
            return $this->errorResponse('BREAK_GLASS_REQUEST_INVALID_STATE', 'Break-glass request can only be approved from requested status.', 422);
        }

        $record->fill([
            'status' => 'approved',
            'approval_note' => $validated['approvalNote'],
            'approved_by_user_id' => (int) ($request->user()?->id ?? 0) ?: null,
            'approved_at' => now(),
            'expires_at' => Carbon::parse($validated['expiresAt']),
        ]);
        $record->save();

        return response()->json([
            'success' => true,
            'data' => $this->breakGlassPayload($record),
        ]);
    }

    public function anomalyRegistry(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.governance.anomaly.view_all')) {
            return $response;
        }

        $validated = $request->validate([
            'severity_filter' => ['nullable', Rule::in(['info', 'warning', 'critical'])],
            'anomaly_type_filter' => ['nullable', 'string'],
            'resolved' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 50);

        $query = HcmTaxGovernanceAnomaly::query()
            ->orderByDesc('detected_at');

        if (!empty($validated['severity_filter'])) {
            $query->where('severity', $validated['severity_filter']);
        }

        if (!empty($validated['anomaly_type_filter'])) {
            $query->where('anomaly_type', $validated['anomaly_type_filter']);
        }

        if (isset($validated['resolved'])) {
            if ($validated['resolved']) {
                $query->whereNotNull('resolved_at');
            } else {
                $query->whereNull('resolved_at');
            }
        } else {
            // Default: show unresolved only
            $query->whereNull('resolved_at');
        }

        $anomalies = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'anomalies' => collect($anomalies->items())->map(function (HcmTaxGovernanceAnomaly $anom) {
                    return [
                        'id' => $anom->id,
                        'company_id' => $anom->company_id,
                        'company_name' => optional($anom->company)->name ?? 'Unknown',
                        'anomaly_type' => $anom->anomaly_type,
                        'severity' => $anom->severity,
                        'description' => $anom->description,
                        'affected_policy_id' => $anom->affected_policy_id,
                        'detected_at' => optional($anom->detected_at)->toIso8601String(),
                        'resolved_at' => optional($anom->resolved_at)?->toIso8601String(),
                    ];
                })->values(),
                'meta' => [
                    'page' => $anomalies->currentPage(),
                    'per_page' => $anomalies->perPage(),
                    'total' => $anomalies->total(),
                ],
            ],
        ]);
    }

    public function resolveAnomaly(Request $request, string $anomalyId): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.governance.anomaly.manage')) {
            return $response;
        }

        $anomaly = HcmTaxGovernanceAnomaly::find($anomalyId);
        if (!$anomaly) {
            return $this->errorResponse('ANOMALY_NOT_FOUND', 'Anomaly not found.', 404);
        }

        // Verify tenant access (global admin can resolve any, tenant user can only resolve own)
        $userCompanyId = $this->activeCompanyId($request);
        $isGlobalAdmin = (bool) ($request->user()?->isGlobalHcmAdmin() ?? false);
        if (!$isGlobalAdmin && $anomaly->company_id !== $userCompanyId) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Cannot resolve anomaly in other tenant.', 403);
        }

        if ($anomaly->resolved_at !== null) {
            return $this->errorResponse('ANOMALY_ALREADY_RESOLVED', 'Anomaly is already resolved.', 422);
        }

        $validated = $request->validate([
            'resolution_note' => ['required', 'string', 'max:1000'],
        ]);

        $actorId = (int) ($request->user()?->id ?? 0) ?: null;

        DB::transaction(function () use ($anomaly, $validated, $actorId): void {
            $evidenceSnapshot = is_array($anomaly->evidence_snapshot) ? $anomaly->evidence_snapshot : [];
            $resolutionLog = is_array($evidenceSnapshot['resolution_audit'] ?? null)
                ? $evidenceSnapshot['resolution_audit']
                : [];
            $resolutionLog[] = [
                'resolved_at' => now()->toIso8601String(),
                'resolved_by_user_id' => $actorId,
                'resolution_note' => $validated['resolution_note'],
            ];

            $anomaly->resolved_at = now();
            $anomaly->resolution_note = $validated['resolution_note'];
            $anomaly->evidence_snapshot = array_merge($evidenceSnapshot, [
                'resolution_audit' => $resolutionLog,
            ]);
            $anomaly->save();
        });

        return response()->json([
            'success' => true,
            'data' => [
                'anomaly_id' => $anomaly->id,
                'resolved_at' => $anomaly->resolved_at->toIso8601String(),
                'resolution_note' => $anomaly->resolution_note,
            ],
        ]);
    }

    public function acknowledgeAnomaly(Request $request, string $anomalyId): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.governance.anomaly.manage')) {
            return $response;
        }

        $anomaly = HcmTaxGovernanceAnomaly::find($anomalyId);
        if (!$anomaly) {
            return $this->errorResponse('ANOMALY_NOT_FOUND', 'Anomaly not found.', 404);
        }

        // Verify tenant access
        $userCompanyId = $this->activeCompanyId($request);
        $isGlobalAdmin = (bool) ($request->user()?->isGlobalHcmAdmin() ?? false);
        if (!$isGlobalAdmin && (int) $anomaly->company_id !== (int) $userCompanyId) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Cannot acknowledge anomaly in other tenant.', 403);
        }

        $actorId = (int) ($request->user()?->id ?? 0) ?: null;

        // Add acknowledgment to evidence (in JSON column if available, or create event)
        $anomaly->acknowledged_by_user_id = $actorId;
        $anomaly->acknowledged_at = now();
        $anomaly->save();

        return response()->json([
            'success' => true,
            'data' => [
                'anomaly_id' => $anomaly->id,
                'acknowledged_at' => $anomaly->acknowledged_at->toIso8601String(),
                'acknowledged_by_user_id' => $actorId,
            ],
        ]);
    }

    public function tenantSelfAuditReportEnhanced(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.policy.view')) {
            return $response;
        }

        $companyId = $request->input('company_id');
        $userCompanyId = $this->activeCompanyId($request);

        // Authorization: tenant user can only view own tenant; global admin can view any
        $isGlobalAdmin = (bool) ($request->user()?->isGlobalHcmAdmin() ?? false);
        if (!$isGlobalAdmin && $companyId && (int) $companyId !== (int) $userCompanyId) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Cannot view other tenant self-audit report.', 403);
        }

        if (!$companyId && $userCompanyId) {
            $companyId = $userCompanyId;
        }

        if (!$companyId) {
            return $this->errorResponse('TENANT_REQUIRED', 'Company context is required.', 400);
        }

        $this->ensureDefaultTenantPolicyTemplate((int) $companyId, (int) ($request->user()?->id ?? 0) ?: null);

        $company = Company::find((int) $companyId);
        if (!$company) {
            return $this->errorResponse('COMPANY_NOT_FOUND', 'Company not found.', 404);
        }

        $validated = $request->validate([
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
        ]);

        $periodStart = !empty($validated['period_start']) ? Carbon::parse($validated['period_start']) : now()->subDays(90);
        $periodEnd = !empty($validated['period_end']) ? Carbon::parse($validated['period_end']) : now();

        $policies = HcmTaxGovernancePolicy::where('company_id', (int) $companyId)->get();
        $currentPublishedPolicy = $policies->where('status', 'published')->first();

        // Build change history from events
        $events = HcmTaxGovernancePolicyEvent::where('company_id', (int) $companyId)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $changeHistory = collect($events)
            ->map(function (HcmTaxGovernancePolicyEvent $event) {
                return [
                    'event_type' => $event->event_type,
                    'created_at' => optional($event->created_at)->toIso8601String(),
                    'created_by' => optional($event->actor)->email ?? 'System',
                    'note' => $event->note,
                ];
            })
            ->values();

        // Compute compliance checklist
        // AN-011: Replaced hardcoded all_payroll_runs_covered=true with real payroll query
        $payrollRunsInPeriod = 0;
        $payrollRunsUsingPolicy = 0;
        $allPayrollRunsCovered = true;
        if (\Illuminate\Support\Facades\Schema::hasTable('hcm_payroll_runs')) {
            $payrollRuns = \Illuminate\Support\Facades\DB::table('hcm_payroll_runs')
                ->where('company_id', (int) $companyId)
                ->whereBetween('finalized_at', [$periodStart->toDateTimeString(), $periodEnd->toDateTimeString()])
                ->where('status', 'finalized')
                ->select('id', 'hcm_tax_governance_policy_id')
                ->get();
            $payrollRunsInPeriod = $payrollRuns->count();
            $payrollRunsUsingPolicy = $payrollRuns->whereNotNull('hcm_tax_governance_policy_id')->count();
            $allPayrollRunsCovered = $payrollRunsInPeriod === 0 || $payrollRunsUsingPolicy === $payrollRunsInPeriod;
        }

        $complianceChecklist = [
            'has_published_policy' => (bool) $currentPublishedPolicy,
            'has_recent_publication' => $currentPublishedPolicy && $currentPublishedPolicy->published_at && $currentPublishedPolicy->published_at->diffInDays(now()) < 90,
            'all_payroll_runs_covered' => $allPayrollRunsCovered,
            'no_unresolved_anomalies' => HcmTaxGovernanceAnomaly::where('company_id', (int) $companyId)->whereNull('resolved_at')->count() === 0,
        ];

        $billingService = app(BillingTaxCalculationService::class);
        $billingTaxCompliance = $billingService->calculateBillingTax((int) $companyId, now()->format('Y-m'));

        return response()->json([
            'success' => true,
            'data' => [
                'company_id' => $companyId,
                'company_name' => $company->name,
                'period' => [
                    'start' => $periodStart->toDateString(),
                    'end' => $periodEnd->toDateString(),
                ],
                'policy_snapshot' => [
                    'current_published_version' => $currentPublishedPolicy?->version,
                    'effective_date' => optional($currentPublishedPolicy?->effective_start_date)?->toDateString(),
                    'policy_summary' => [
                        'policy_code' => $currentPublishedPolicy?->policy_code,
                        'name' => $currentPublishedPolicy?->name,
                        'rules_count' => count($currentPublishedPolicy?->rules ?? []),
                    ],
                ],
                'change_history' => $changeHistory,
                'payroll_impact' => [
                    'payroll_runs_in_period' => $payrollRunsInPeriod,
                    'payroll_runs_using_published_policy' => $payrollRunsUsingPolicy,
                    'anomalies_in_period' => [],
                ],
                'compliance_checklist' => $complianceChecklist,
                'billing_tax_compliance' => [
                    'billing_month' => $billingTaxCompliance['billing_month'] ?? now()->format('Y-m'),
                    'policy_uuid' => $billingTaxCompliance['policy_uuid'] ?? null,
                    'invoice_count' => (int) ($billingTaxCompliance['invoice_count'] ?? 0),
                    'paid_invoice_count' => (int) ($billingTaxCompliance['paid_invoice_count'] ?? 0),
                    'unpaid_invoice_count' => (int) ($billingTaxCompliance['unpaid_invoice_count'] ?? 0),
                    'total_invoice_amount' => (float) ($billingTaxCompliance['total_invoice_amount'] ?? 0),
                    'taxable_revenue_amount' => (float) ($billingTaxCompliance['taxable_revenue_amount'] ?? 0),
                    'cleared_revenue_amount' => (float) ($billingTaxCompliance['cleared_revenue_amount'] ?? 0),
                    'uncleared_revenue_amount' => (float) ($billingTaxCompliance['uncleared_revenue_amount'] ?? 0),
                    'disputed_revenue_amount' => (float) ($billingTaxCompliance['disputed_revenue_amount'] ?? 0),
                    'reversed_revenue_amount' => (float) ($billingTaxCompliance['reversed_revenue_amount'] ?? 0),
                    'tax_amount_due' => (float) ($billingTaxCompliance['tax_amount'] ?? 0),
                    'tax_rate_percentage' => (float) ($billingTaxCompliance['tax_rate_percentage'] ?? 0),
                ],
            ],
        ]);
    }

    public function tenantComplianceStatus(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.report.export')) {
            return $response;
        }

        $companyId = $request->input('company_id');
        $userCompanyId = $this->activeCompanyId($request);
        $isGlobalAdmin = (bool) ($request->user()?->isGlobalHcmAdmin() ?? false);

        if (!$isGlobalAdmin && $companyId && (int) $companyId !== (int) $userCompanyId) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Cannot view other tenant compliance status.', 403);
        }

        if (!$companyId && $userCompanyId) {
            $companyId = $userCompanyId;
        }

        if (!$companyId) {
            return $this->errorResponse('TENANT_REQUIRED', 'Company context is required.', 400);
        }

        $this->ensureDefaultTenantPolicyTemplate((int) $companyId, (int) ($request->user()?->id ?? 0) ?: null);

        $company = Company::find((int) $companyId);
        if (!$company) {
            return $this->errorResponse('COMPANY_NOT_FOUND', 'Company not found.', 404);
        }

        $currentPolicy = HcmTaxGovernancePolicy::query()
            ->where('company_id', (int) $companyId)
            ->where('status', HcmTaxGovernancePolicy::STATUS_PUBLISHED)
            ->orderByDesc('version')
            ->first();

        $unresolvedAnomalies = HcmTaxGovernanceAnomaly::query()
            ->where('company_id', (int) $companyId)
            ->whereNull('resolved_at')
            ->count();

        $billingCompliance = app(BillingTaxCalculationService::class)
            ->calculateBillingTax((int) $companyId, now()->format('Y-m'));

        $employeePph21Compliance = $this->buildEmployeePph21ComplianceSnapshot((int) $companyId);

        $overallStatus = (
            $currentPolicy
            && $unresolvedAnomalies === 0
            && (int) ($billingCompliance['unpaid_invoice_count'] ?? 0) === 0
            && (int) ($employeePph21Compliance['missing_npwp'] ?? 0) === 0
            && (int) ($employeePph21Compliance['invalid_npwp_format'] ?? 0) === 0
            && (int) ($employeePph21Compliance['missing_ptkp_status'] ?? 0) === 0
        )
            ? 'compliant'
            : 'attention_required';

        $recommendedActions = [];
        if (!$currentPolicy) {
            $recommendedActions[] = [
                'priority' => 'high',
                'action' => 'Publish active statutory tax policy.',
                'target_date' => now()->addDays(7)->toDateString(),
            ];
        }
        if ($unresolvedAnomalies > 0) {
            $recommendedActions[] = [
                'priority' => 'high',
                'action' => 'Resolve unresolved tax governance anomalies.',
                'target_date' => now()->addDays(5)->toDateString(),
            ];
        }
        if ((int) ($billingCompliance['unpaid_invoice_count'] ?? 0) > 0) {
            $recommendedActions[] = [
                'priority' => 'medium',
                'action' => 'Reconcile unpaid billing tax invoices.',
                'target_date' => now()->addDays(10)->toDateString(),
            ];
        }
        if ((int) ($employeePph21Compliance['missing_npwp'] ?? 0) > 0) {
            $recommendedActions[] = [
                'priority' => 'high',
                'action' => 'Lengkapi NPWP untuk seluruh karyawan aktif yang wajib pajak.',
                'target_date' => now()->addDays(7)->toDateString(),
            ];
        }
        if ((int) ($employeePph21Compliance['invalid_npwp_format'] ?? 0) > 0) {
            $recommendedActions[] = [
                'priority' => 'high',
                'action' => 'Normalisasi format NPWP karyawan agar sesuai format numerik resmi.',
                'target_date' => now()->addDays(7)->toDateString(),
            ];
        }
        if ((int) ($employeePph21Compliance['missing_ptkp_status'] ?? 0) > 0) {
            $recommendedActions[] = [
                'priority' => 'high',
                'action' => 'Tetapkan status PTKP valid untuk seluruh karyawan aktif.',
                'target_date' => now()->addDays(7)->toDateString(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'company_id' => (int) $companyId,
                'company_name' => $company->name,
                'reporting_period' => now()->year . '-Q' . now()->quarter,
                'compliance_status' => [
                    'statutory_tax_compliance' => [
                        'has_active_policy' => (bool) $currentPolicy,
                        'policy_version' => $currentPolicy?->version,
                        'last_publication_date' => optional($currentPolicy?->published_at)?->toDateString(),
                        'anomalies_unresolved' => $unresolvedAnomalies,
                    ],
                    'billing_tax_compliance' => [
                        'billing_cycle_active' => !empty($billingCompliance['policy_uuid']),
                        'invoices_issued' => !empty($billingCompliance['policy_uuid']) ? (int) ($billingCompliance['unpaid_invoice_count'] ?? 0) : 0,
                        'invoices_paid' => !empty($billingCompliance['policy_uuid']) ? (int) ($billingCompliance['paid_invoice_count'] ?? 0) : 0,
                        'amount_outstanding' => !empty($billingCompliance['policy_uuid']) ? (float) ($billingCompliance['outstanding_invoice_amount'] ?? 0) : 0,
                        'taxable_revenue_amount' => (float) ($billingCompliance['taxable_revenue_amount'] ?? 0),
                        'cleared_revenue_amount' => (float) ($billingCompliance['cleared_revenue_amount'] ?? 0),
                        'uncleared_revenue_amount' => (float) ($billingCompliance['uncleared_revenue_amount'] ?? 0),
                        'disputed_revenue_amount' => (float) ($billingCompliance['disputed_revenue_amount'] ?? 0),
                        'reversed_revenue_amount' => (float) ($billingCompliance['reversed_revenue_amount'] ?? 0),
                        'payment_status' => ((int) ($billingCompliance['unpaid_invoice_count'] ?? 0) === 0) ? 'current' : 'overdue',
                    ],
                    'employee_pph21_compliance' => [
                        'active_employees' => (int) ($employeePph21Compliance['active_employees'] ?? 0),
                        'profiles_available' => (int) ($employeePph21Compliance['profiles_available'] ?? 0),
                        'complete_profiles' => (int) ($employeePph21Compliance['complete_profiles'] ?? 0),
                        'missing_npwp' => (int) ($employeePph21Compliance['missing_npwp'] ?? 0),
                        'invalid_npwp_format' => (int) ($employeePph21Compliance['invalid_npwp_format'] ?? 0),
                        'missing_ptkp_status' => (int) ($employeePph21Compliance['missing_ptkp_status'] ?? 0),
                        'completion_rate' => (float) ($employeePph21Compliance['completion_rate'] ?? 0),
                        'non_compliant_employees' => array_values((array) ($employeePph21Compliance['non_compliant_employees'] ?? [])),
                    ],
                    'overall_status' => $overallStatus,
                    'next_review_date' => now()->addMonth()->toDateString(),
                ],
                'recommended_actions' => $recommendedActions,
            ],
        ]);
    }

    /**
     * @return array<string, int|float|array<int, array<string, mixed>>>
     */
    private function buildEmployeePph21ComplianceSnapshot(int $companyId): array
    {
        $activeUserIds = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('role', '!=', 'owner')
            ->pluck('user_id');

        $activeUsers = User::query()
            ->whereIn('id', $activeUserIds)
            ->get(['id', 'uuid', 'name', 'email'])
            ->keyBy('id');

        $activeEmployeeCount = $activeUserIds->count();
        if ($activeEmployeeCount === 0) {
            return [
                'active_employees' => 0,
                'profiles_available' => 0,
                'complete_profiles' => 0,
                'missing_npwp' => 0,
                'invalid_npwp_format' => 0,
                'missing_ptkp_status' => 0,
                'completion_rate' => 0.0,
                'non_compliant_employees' => [],
            ];
        }

        $employeeProfiles = EmployeeProfile::query()
            ->whereIn('user_id', $activeUserIds)
            ->get(['id', 'user_id', 'marital_status']);

        $profileByUserId = $employeeProfiles->keyBy('user_id');
        $profileIds = $employeeProfiles->pluck('id');

        $latestTaxProfiles = EmployeeTaxProfile::query()
            ->whereIn('employee_id', $profileIds)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($items) => $items->first());

        $allowedPtkpStatuses = $this->allowedPtkpStatuses();
        $profilesAvailable = 0;
        $completeProfiles = 0;
        $missingNpwp = 0;
        $invalidNpwpFormat = 0;
        $missingPtkpStatus = 0;
        $nonCompliantEmployees = [];

        foreach ($activeUserIds as $userId) {
            $user = $activeUsers->get((int) $userId);
            $profile = $profileByUserId->get($userId);
            $issues = [];

            if (! $profile) {
                $missingNpwp++;
                $missingPtkpStatus++;
                $issues[] = ['code' => 'employee_profile_missing', 'label' => 'Profil karyawan belum tersedia.'];
                $issues[] = ['code' => 'npwp_missing', 'label' => 'NPWP belum diisi.'];
                $issues[] = ['code' => 'ptkp_status_missing', 'label' => 'Status PTKP belum diisi.'];

                $nonCompliantEmployees[] = [
                    'user_id' => (int) $userId,
                    'user_uuid' => $user?->uuid,
                    'full_name' => $user?->name ?? 'Unknown Employee',
                    'email' => $user?->email,
                    'issues' => $issues,
                ];
                continue;
            }

            $derivedPtkpStatus = $this->inferTaxStatusFromMaritalStatus($profile->marital_status);

            /** @var EmployeeTaxProfile|null $taxProfile */
            $taxProfile = $latestTaxProfiles->get((int) $profile->id);
            if (! $taxProfile) {
                $missingNpwp++;
                $issues[] = ['code' => 'npwp_missing', 'label' => 'NPWP belum diisi.'];
                if ($derivedPtkpStatus === null) {
                    $missingPtkpStatus++;
                    $issues[] = ['code' => 'ptkp_status_missing', 'label' => 'Status PTKP belum diisi.'];
                }

                $nonCompliantEmployees[] = [
                    'user_id' => (int) $userId,
                    'user_uuid' => $user?->uuid,
                    'full_name' => $user?->name ?? 'Unknown Employee',
                    'email' => $user?->email,
                    'issues' => $issues,
                ];
                continue;
            }

            $profilesAvailable++;

            $rawNpwp = trim((string) ($taxProfile->npwp ?? ''));
            $npwp = $this->normalizeNpwp($rawNpwp);
            $hasRawNpwp = $rawNpwp !== '';
            $npwpFormatValid = $hasRawNpwp && $this->isValidNpwpFormat($npwp);

            if (! $hasRawNpwp) {
                $missingNpwp++;
                $issues[] = ['code' => 'npwp_missing', 'label' => 'NPWP belum diisi.'];
            } elseif (! $npwpFormatValid) {
                $invalidNpwpFormat++;
                $issues[] = [
                    'code' => 'npwp_invalid_format',
                    'label' => 'Format NPWP tidak valid.',
                    'current_value' => $rawNpwp,
                ];
            }

            $ptkpStatus = strtoupper(trim((string) ($taxProfile->ptkp_status ?: $taxProfile->tax_status ?: $derivedPtkpStatus ?: '')));
            $ptkpValid = $ptkpStatus !== '' && in_array($ptkpStatus, $allowedPtkpStatuses, true);
            if (! $ptkpValid) {
                $missingPtkpStatus++;
                $issues[] = [
                    'code' => 'ptkp_status_missing',
                    'label' => 'Status PTKP belum valid.',
                    'current_value' => $ptkpStatus,
                ];
            }

            if ($npwpFormatValid && $ptkpValid) {
                $completeProfiles++;
            } else {
                $nonCompliantEmployees[] = [
                    'user_id' => (int) $userId,
                    'user_uuid' => $user?->uuid,
                    'full_name' => $user?->name ?? 'Unknown Employee',
                    'email' => $user?->email,
                    'issues' => $issues,
                ];
            }
        }

        $completionRate = $activeEmployeeCount > 0
            ? round(($completeProfiles / $activeEmployeeCount) * 100, 2)
            : 0;

        return [
            'active_employees' => $activeEmployeeCount,
            'profiles_available' => $profilesAvailable,
            'complete_profiles' => $completeProfiles,
            'missing_npwp' => $missingNpwp,
            'invalid_npwp_format' => $invalidNpwpFormat,
            'missing_ptkp_status' => $missingPtkpStatus,
            'completion_rate' => $completionRate,
            'non_compliant_employees' => array_slice($nonCompliantEmployees, 0, 100),
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedPtkpStatuses(): array
    {
        $statuses = (array) config('hcm.tax_statuses', ['TK0', 'TK1', 'TK2', 'TK3', 'K0', 'K1', 'K2', 'K3']);
        return array_values(array_unique(array_map(fn ($item): string => strtoupper(trim((string) $item)), $statuses)));
    }

    private function inferTaxStatusFromMaritalStatus(?string $maritalStatus): ?string
    {
        $normalized = strtolower(trim((string) $maritalStatus));

        return match ($normalized) {
            'married' => 'K0',
            'single', 'divorced', 'widowed' => 'TK0',
            default => null,
        };
    }

    private function normalizeNpwp(string $value): string
    {
        return preg_replace('/[^0-9]/', '', trim($value)) ?? '';
    }

    private function isValidNpwpFormat(string $normalizedNpwp): bool
    {
        return preg_match('/^[0-9]{15,16}$/', $normalizedNpwp) === 1;
    }

    private function validateUpsertRequest(Request $request, bool $isCreate): array
    {
        $rules = [
            'policyCode' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'effectiveStartDate' => ['required', 'date'],
            'effectiveEndDate' => ['nullable', 'date', 'after_or_equal:effectiveStartDate'],
            'draftKey' => ['nullable', 'string', 'max:120'],
            'rules' => ['required', 'array'],
            'rules.scheme' => ['required', 'string', 'in:STATUTORY_PPH21,TER'],
            'rules.currency' => ['nullable', 'string', 'in:IDR'],
            'rules.regulationReference' => ['nullable', 'string', 'max:255'],
            'rules.regulation_reference' => ['nullable', 'string', 'max:255'],
            'rules.regulationSourceType' => ['nullable', 'string', Rule::in(self::POLICY_REGULATION_SOURCE_TYPES)],
            'rules.calculationMethod' => ['nullable', 'string', Rule::in(self::POLICY_CALCULATION_METHODS)],
            'rateSchedules' => ['required', 'array'],
            'rateSchedules.*.category' => ['nullable', 'string', Rule::in(self::POLICY_SCHEDULE_CATEGORIES)],
            'rateSchedules.*.bracket' => ['nullable', 'string', 'in:A,B,C'],
            'rateSchedules.*.calculationMode' => ['nullable', 'string', Rule::in(self::POLICY_SCHEDULE_MODES)],
            'rateSchedules.*.lookupTableCode' => ['nullable', 'string', 'in:A,B,C'],
            'rateSchedules.*.rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rateSchedules.*.upperBound' => ['nullable', 'numeric', 'gt:0'],
            'rateSchedules.*.effectiveStartDate' => ['nullable', 'date'],
            'rateSchedules.*.effectiveEndDate' => ['nullable', 'date'],
            'rateSchedules.*.regulationReference' => ['nullable', 'string', 'max:255'],
            'rateSchedules.*.regulationSourceType' => ['nullable', 'string', Rule::in(self::POLICY_REGULATION_SOURCE_TYPES)],
            'version' => ['nullable', 'integer', 'min:1'],
        ];

        if ($isCreate) {
            unset($rules['version']);
        }

        return $request->validate($rules);
    }

    private function ensureTenantOwnerOrGlobalAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required.',
                ],
            ], 401);
        }

        if ($user->isGlobalHcmAdmin()) {
            return null;
        }

        $activeCompanyRole = strtolower(trim((string) $request->attributes->get('activeCompanyRole', '')));
        if ($activeCompanyRole === 'owner') {
            return null;
        }

        return $this->errorResponse('AUTH_FORBIDDEN', 'Only tenant owner can manage employee tax policy at this stage.', 403);
    }

    private function ensureDefaultTenantPolicyTemplate(int $companyId, ?int $actorUserId): void
    {
        $hasAnyPolicy = HcmTaxGovernancePolicy::query()
            ->where('company_id', $companyId)
            ->exists();

        if ($hasAnyPolicy) {
            return;
        }

        DB::transaction(function () use ($companyId, $actorUserId): void {
            $policy = HcmTaxGovernancePolicy::query()->create([
                'company_id' => $companyId,
                'policy_code' => 'PPH21-STATUTORY-DEFAULT',
                'name' => 'Default PPh 21 Statutory Policy (Indonesia)',
                'status' => HcmTaxGovernancePolicy::STATUS_DRAFT,
                'draft_fingerprint' => 'tenant-pph21-statutory-default',
                'effective_start_date' => now()->startOfMonth()->toDateString(),
                'effective_end_date' => null,
                'rules' => $this->buildStatutoryRules('PP 58/2023 & PMK 168/PMK.03/2023', 'ministry_regulation'),
                'rate_schedules' => $this->buildDefaultStatutorySchedules(now()->startOfMonth()->toDateString(), null, 'PP 58/2023 & PMK 168/PMK.03/2023', 'ministry_regulation'),
                'version' => 1,
                'created_by_user_id' => $actorUserId,
                'last_note' => 'Auto provisioned statutory PPh 21 baseline for the active company. Review regulation reference and effective period before production payroll usage.',
            ]);

            $this->recordEvent(
                $policy,
                'default_template_provisioned',
                null,
                $this->policyStateSnapshot($policy),
                'Default employee tax template was auto-provisioned for onboarding baseline.',
                $actorUserId,
            );
        });
    }

    private function findPolicyForRequest(Request $request, string $policyRef, bool &$usedNumericLegacy = false): ?HcmTaxGovernancePolicy
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return null;
        }

        $usedNumericLegacy = false;

        if (! Str::isUuid($policyRef)) {
            return null;
        }

        $query = HcmTaxGovernancePolicy::query()
            ->where('company_id', $companyId)
            ->where('uuid', $policyRef);

        return $query->first();
    }

    private function policyPayload(HcmTaxGovernancePolicy $policy, bool $withEvents = false): array
    {
        $payload = [
            'uuid' => $policy->uuid,
            'policyCode' => $policy->policy_code,
            'name' => $policy->name,
            'status' => $policy->status,
            'effectiveStartDate' => optional($policy->effective_start_date)?->toDateString(),
            'effectiveEndDate' => optional($policy->effective_end_date)?->toDateString(),
            'rules' => $policy->rules ?? [],
            'rateSchedules' => $policy->rate_schedules ?? [],
            'draftKey' => $policy->draft_fingerprint,
            'version' => (int) $policy->version,
            'submittedAt' => optional($policy->submitted_at)?->toIso8601String(),
            'approvedAt' => optional($policy->approved_at)?->toIso8601String(),
            'publishedAt' => optional($policy->published_at)?->toIso8601String(),
            'lastNote' => $policy->last_note,
            'createdAt' => optional($policy->created_at)?->toIso8601String(),
            'updatedAt' => optional($policy->updated_at)?->toIso8601String(),
        ];

        if ($withEvents) {
            $payload['events'] = $policy->events()
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->map(static fn (HcmTaxGovernancePolicyEvent $event): array => [
                    'uuid' => $event->uuid,
                    'eventType' => $event->event_type,
                    'actorUserId' => $event->actor_user_id,
                    'note' => $event->note,
                    'createdAt' => optional($event->created_at)?->toIso8601String(),
                ])
                ->values();
        }

        return $payload;
    }

    private function transitionPolicyStatus(
        HcmTaxGovernancePolicy $policy,
        string $nextStatus,
        string $eventType,
        ?int $actorId,
        ?string $note = null,
        array $extraAttributes = [],
    ): HcmTaxGovernancePolicy {
        $before = $this->policyStateSnapshot($policy);
        $previousStatus = $policy->status;

        DB::transaction(function () use ($policy, $nextStatus, $eventType, $actorId, $note, $extraAttributes, $before): void {
            $policy->fill(array_merge([
                'status' => $nextStatus,
                'version' => (int) $policy->version + 1,
                'last_note' => $note,
            ], $extraAttributes));
            $policy->save();

            $this->recordEvent($policy, $eventType, $before, $this->policyStateSnapshot($policy), $note, $actorId);
        });

        $policy->refresh();

        TaxGovernancePolicyTransitioned::dispatch($policy, $previousStatus, $policy->status, (int) ($actorId ?? 0));

        return $policy;
    }

    private function breakGlassPayload(HcmTaxGovernanceBreakGlassRequest $record): array
    {
        return [
            'requestUuid' => $record->uuid,
            'targetTenantUuid' => $record->target_company_uuid,
            'status' => $record->status,
            'requestedByUserUuid' => $record->requested_by_user_uuid,
            'approvedByUserUuid' => $record->approved_by_user_uuid,
            'expiresAt' => optional($record->expires_at)?->toIso8601String(),
            'createdAt' => optional($record->created_at)?->toIso8601String(),
            'updatedAt' => optional($record->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeUpsertPayload(array $validated, ?HcmTaxGovernancePolicy $existingPolicy = null): array
    {
        $effectiveStartDate = (string) $validated['effectiveStartDate'];
        $effectiveEndDate = $validated['effectiveEndDate'] ?? null;
        $inputRules = is_array($validated['rules'] ?? null) ? $validated['rules'] : [];
        $regulationReference = (string) ($inputRules['regulationReference'] ?? $inputRules['regulation_reference'] ?? 'PP 58/2023 & PMK 168/PMK.03/2023');
        $regulationSourceType = (string) ($inputRules['regulationSourceType'] ?? 'ministry_regulation');

        $rateSchedules = [];
        foreach ((array) ($validated['rateSchedules'] ?? []) as $schedule) {
            if (! is_array($schedule)) {
                continue;
            }

            $category = strtoupper((string) ($schedule['category'] ?? $schedule['bracket'] ?? 'A'));
            $calculationMode = (string) ($schedule['calculationMode'] ?? (isset($schedule['bracket']) ? 'ter_lookup' : 'fixed_rate_override'));
            $rateSchedules[] = [
                'category' => $category,
                'lookupTableCode' => $schedule['lookupTableCode'] ?? (in_array($category, ['A', 'B', 'C'], true) ? $category : null),
                'calculationMode' => $calculationMode,
                'rate' => array_key_exists('rate', $schedule) ? $schedule['rate'] : null,
                'upperBound' => $schedule['upperBound'] ?? null,
                'effectiveStartDate' => $schedule['effectiveStartDate'] ?? $effectiveStartDate,
                'effectiveEndDate' => $schedule['effectiveEndDate'] ?? $effectiveEndDate,
                'regulationReference' => $schedule['regulationReference'] ?? $regulationReference,
                'regulationSourceType' => $schedule['regulationSourceType'] ?? $regulationSourceType,
            ];
        }

        if ($rateSchedules === []) {
            $rateSchedules = $this->buildDefaultStatutorySchedules($effectiveStartDate, $effectiveEndDate, $regulationReference, $regulationSourceType);
        }

        $normalizedRules = array_merge(
            $existingPolicy && is_array($existingPolicy->rules) ? $existingPolicy->rules : [],
            $inputRules,
            $this->buildStatutoryRules($regulationReference, $regulationSourceType),
            [
                'calculationMethod' => $inputRules['calculationMethod'] ?? 'monthly_ter_lookup',
            ],
        );

        return [
            'policyCode' => $validated['policyCode'],
            'name' => $validated['name'],
            'effectiveStartDate' => $effectiveStartDate,
            'effectiveEndDate' => $effectiveEndDate,
            'draftKey' => $validated['draftKey'] ?? null,
            'rules' => $normalizedRules,
            'rateSchedules' => $rateSchedules,
            'version' => $validated['version'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStatutoryRules(string $regulationReference, string $regulationSourceType): array
    {
        return [
            'scheme' => 'STATUTORY_PPH21',
            'currency' => 'IDR',
            'country' => 'ID',
            'calculationMethod' => 'monthly_ter_lookup',
            'regulationReference' => $regulationReference,
            'regulationSourceType' => $regulationSourceType,
            'source' => 'tenant_statutory_policy',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildDefaultStatutorySchedules(string $effectiveStartDate, ?string $effectiveEndDate, string $regulationReference, string $regulationSourceType): array
    {
        $schedules = [];
        foreach (['A', 'B', 'C'] as $category) {
            $schedules[] = [
                'category' => $category,
                'lookupTableCode' => $category,
                'calculationMode' => 'ter_lookup',
                'rate' => null,
                'upperBound' => null,
                'effectiveStartDate' => $effectiveStartDate,
                'effectiveEndDate' => $effectiveEndDate,
                'regulationReference' => $regulationReference,
                'regulationSourceType' => $regulationSourceType,
            ];
        }

        return $schedules;
    }
    public function tenantSelfAuditReportExport(Request $request): JsonResponse|Response
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.report.export')) {
            return $response;
        }

        $companyId = $request->input('company_id');
        $userCompanyId = $this->activeCompanyId($request);
        $format = $request->input('format', 'json'); // json or pdf

        // Authorization: tenant user can only export own tenant; global admin can export any
        $isGlobalAdmin = (bool) ($request->user()?->isGlobalHcmAdmin() ?? false);
        if (!$isGlobalAdmin && $companyId && $companyId !== $userCompanyId) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Cannot export other tenant audit report.', 403);
        }

        if (!$companyId && $userCompanyId) {
            $companyId = $userCompanyId;
        }

        if (!$companyId) {
            return $this->errorResponse('TENANT_REQUIRED', 'Company context is required.', 400);
        }

        // Validate company exists
        $company = Company::find($companyId);
        if (!$company) {
            return $this->errorResponse('COMPANY_NOT_FOUND', 'Company not found.', 404);
        }

        $validated = $request->validate([
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
        ]);

        $periodStart = $validated['period_start'] ? \Carbon\Carbon::parse($validated['period_start']) : now()->subDays(90);
        $periodEnd = $validated['period_end'] ? \Carbon\Carbon::parse($validated['period_end']) : now();

        // Validate period is within last 2 years
        if ($periodStart->diffInYears($periodEnd) > 2) {
            return $this->errorResponse('INVALID_PERIOD', 'Period cannot exceed 2 years.', 422);
        }

        // Generate report data
        $reportService = app(\App\Services\TaxGovernanceReportingService::class);
        $reportData = $reportService->generateTenantSelfAuditReport($companyId, $periodStart, $periodEnd);

        if ($format === 'pdf') {
            $pdfBinary = $this->renderTenantSelfAuditPdf($reportData);

            return response($pdfBinary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="tenant-self-audit-' . ((string) $companyId) . '-' . now()->format('Ymd-His') . '.pdf"',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $reportData,
        ]);
    }

    public function platformBillingPolicies(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.platform.policy.view')) {
            return $response;
        }

        if (!($request->user()?->isGlobalHcmAdmin() ?? false)) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Access denied for this operation.', 403);
        }

        $validated = $request->validate([
            'billing_month' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'inactive'])],
            'global_mode' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = HcmBillingTaxPolicy::query()->with('company')->orderByDesc('effective_from')->orderByDesc('created_at');

        if (!empty($validated['billing_month'])) {
            $query->where('billing_month', $validated['billing_month']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $globalSourceQuery = clone $query;
        $rows = $query->paginate((int) ($validated['per_page'] ?? 20));

        $rawItems = collect($rows->items());
        $globalMode = (bool) ($validated['global_mode'] ?? false);
        $globalSourceItems = $globalMode ? $globalSourceQuery->get() : $rawItems;
        $globalItems = $this->buildGlobalPlatformPolicyItems($globalSourceItems);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $rawItems->map(fn (HcmBillingTaxPolicy $policy): array => [
                    'id' => $policy->id,
                    'company_id' => $policy->company_id,
                    'company_name' => optional($policy->company)->name,
                    'billing_month' => $policy->billing_month,
                    'billing_cycle_type' => $policy->billing_cycle_type,
                    'tax_rate_percentage' => (float) $policy->tax_rate_percentage,
                    'base_calculation_method' => $policy->base_calculation_method,
                    'effective_from' => optional($policy->effective_from)?->toDateString(),
                    'effective_to' => optional($policy->effective_to)?->toDateString(),
                    'status' => $policy->status,
                    'notes' => $policy->notes,
                    'created_at' => optional($policy->created_at)?->toIso8601String(),
                ])->values(),
                'items_global' => $globalItems,
                'view_mode' => $globalMode ? 'global' : 'company',
                'meta' => [
                    'page' => $rows->currentPage(),
                    'per_page' => $rows->perPage(),
                    'total' => $rows->total(),
                    'items_global_total' => count($globalItems),
                ],
            ],
        ]);
    }

    public function storePlatformBillingPolicy(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.platform.policy.manage')) {
            return $response;
        }

        if (!($request->user()?->isGlobalHcmAdmin() ?? false)) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Access denied for this operation.', 403);
        }

        $isGlobalPayload = $request->hasAny(['subscription_tax_rate', 'addon_markup_rate']);

        if ($isGlobalPayload) {
            $validated = $request->validate([
                'subscription_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'addon_markup_rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'billing_cycle_type' => ['nullable', Rule::in(['monthly', 'yearly', 'custom'])],
                'billing_month' => ['nullable', 'date_format:Y-m'],
                'effective_from' => ['nullable', 'date'],
                'status' => ['nullable', Rule::in(['draft', 'active', 'inactive'])],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]);

            $billingCycleType = (string) ($validated['billing_cycle_type'] ?? 'monthly');

            $service = app(BillingTaxCalculationService::class);
            if (! $service->validateBillingTaxPolicy([
                'tax_rate_percentage' => $validated['subscription_tax_rate'],
                'billing_cycle_type' => $billingCycleType,
                'base_calculation_method' => 'invoice_amount_due',
            ])) {
                return $this->errorResponse('BILLING_TAX_POLICY_INVALID', 'Billing tax policy validation failed.', 422);
            }

            $actorId = (int) ($request->user()?->id ?? 0) ?: null;
            $billingMonth = (string) ($validated['billing_month'] ?? now()->format('Y-m'));
            $effectiveFrom = (string) ($validated['effective_from'] ?? now()->toDateString());
            $status = (string) ($validated['status'] ?? 'active');

            $companyIds = Company::query()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->values();
            if ($companyIds->isEmpty()) {
                return $this->errorResponse('COMPANY_NOT_FOUND', 'No company available for global policy propagation.', 422);
            }

            $globalRates = [
                'subscription_tax_rate' => (float) $validated['subscription_tax_rate'],
                'payroll_service_fee' => 0.0,
                'addon_markup_rate' => (float) $validated['addon_markup_rate'],
            ];
            $propagationKey = (string) Str::uuid();

            $policySource = (string) $request->input('policy_source', 'global_platform_policy');
            $policyDomain = (string) $request->input('policy_domain', 'platform_billing');

            $notesPayload = [
                'global_rates' => $globalRates,
                'notes' => $validated['notes'] ?? null,
                'source' => $policySource,
                'domain' => $policyDomain,
                'propagation_key' => $propagationKey,
            ];

            DB::transaction(function () use ($companyIds, $billingMonth, $billingCycleType, $effectiveFrom, $status, $globalRates, $notesPayload, $actorId): void {
                foreach ($companyIds as $companyId) {
                    // Serialize writes for a company/month pair to prevent split-brain active policies.
                    Company::query()->whereKey($companyId)->lockForUpdate()->first();

                    if ($status === 'active') {
                        HcmBillingTaxPolicy::query()
                            ->where('company_id', $companyId)
                            ->where('billing_month', $billingMonth)
                            ->where('status', 'active')
                            ->lockForUpdate()
                            ->update([
                                'status' => 'inactive',
                                'updated_by_user_id' => $actorId,
                                'updated_at' => now(),
                            ]);
                    }

                    HcmBillingTaxPolicy::query()->create([
                        'id' => (string) Str::uuid(),
                        'company_id' => $companyId,
                        'billing_month' => $billingMonth,
                        'billing_cycle_type' => $billingCycleType,
                        'tax_rate_percentage' => $globalRates['subscription_tax_rate'],
                        'base_calculation_method' => 'invoice_amount_due',
                        'effective_from' => $effectiveFrom,
                        'effective_to' => null,
                        'status' => $status,
                        'notes' => json_encode($notesPayload, JSON_UNESCAPED_UNICODE),
                        'created_by_user_id' => $actorId,
                        'updated_by_user_id' => $actorId,
                    ]);

                    if ($status === 'active') {
                        $activeCount = HcmBillingTaxPolicy::query()
                            ->where('company_id', $companyId)
                            ->where('billing_month', $billingMonth)
                            ->where('status', 'active')
                            ->count();

                        if ($activeCount > 1) {
                            throw new \RuntimeException('Detected conflicting active billing policies for the same company and month.');
                        }
                    }
                }
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'version' => 'v' . now()->format('YmdHis'),
                    'billing_month' => $billingMonth,
                    'billing_cycle_type' => $billingCycleType,
                    'effective_from' => $effectiveFrom,
                    'status' => $status,
                    'subscription_tax_rate' => $globalRates['subscription_tax_rate'],
                    'payroll_service_fee' => $globalRates['payroll_service_fee'],
                    'addon_markup_rate' => $globalRates['addon_markup_rate'],
                    'affected_company_count' => $companyIds->count(),
                    'notes' => $validated['notes'] ?? null,
                ],
            ], 201);
        }

        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'billing_month' => ['required', 'date_format:Y-m'],
            'billing_cycle_type' => ['required', Rule::in(['monthly', 'yearly', 'custom'])],
            'tax_rate_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'base_calculation_method' => ['required', Rule::in(['invoice_amount_due'])],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $service = app(BillingTaxCalculationService::class);
        if (!$service->validateBillingTaxPolicy($validated)) {
            return $this->errorResponse('BILLING_TAX_POLICY_INVALID', 'Billing tax policy validation failed.', 422);
        }

        $actorId = (int) ($request->user()?->id ?? 0) ?: null;

        $policy = HcmBillingTaxPolicy::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => (int) $validated['company_id'],
            'billing_month' => $validated['billing_month'],
            'billing_cycle_type' => $validated['billing_cycle_type'],
            'tax_rate_percentage' => $validated['tax_rate_percentage'],
            'base_calculation_method' => $validated['base_calculation_method'],
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'notes' => $validated['notes'] ?? null,
            'created_by_user_id' => $actorId,
            'updated_by_user_id' => $actorId,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $policy->id,
                'company_id' => $policy->company_id,
                'billing_month' => $policy->billing_month,
                'billing_cycle_type' => $policy->billing_cycle_type,
                'tax_rate_percentage' => (float) $policy->tax_rate_percentage,
                'base_calculation_method' => $policy->base_calculation_method,
                'effective_from' => optional($policy->effective_from)?->toDateString(),
                'effective_to' => optional($policy->effective_to)?->toDateString(),
                'status' => $policy->status,
            ],
        ], 201);
    }

    public function platformBillingReports(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.platform.report.view_all')) {
            return $response;
        }

        if (!($request->user()?->isGlobalHcmAdmin() ?? false)) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Access denied for this operation.', 403);
        }

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $report = app(BillingTaxCalculationService::class)->generateCrossTenantMonthlyReport($validated['month']);

        $tenantGlobal = collect($report['tenants'] ?? [])->map(function (array $item): array {
            return [
                'tenant' => $item['company_name'] ?? '-',
                'plan' => $item['plan_name'] ?? '-',
                'billing_month' => $item['billing_month'] ?? null,
                'billing_cycle_type' => $item['billing_cycle_type'] ?? null,
                'next_renewal_month' => $item['next_renewal_month'] ?? null,
                'subscription_revenue' => (float) ($item['subscription_revenue'] ?? 0),
                'payroll_service_fee' => (float) ($item['payroll_service_fee'] ?? 0),
                'addon_revenue' => (float) ($item['addon_revenue'] ?? 0),
                'gross_revenue' => (float) ($item['gross_revenue'] ?? 0),
                'tax_amount_due' => (float) ($item['tax_amount_due'] ?? 0),
                'net_revenue' => (float) ($item['net_revenue'] ?? 0),
                'company_id' => $item['company_id'] ?? null,
                'company_name' => $item['company_name'] ?? '-',
            ];
        })->values();

        $summary = $report['summary'] ?? [];
        $summaryGlobal = [
            'total_subscription_revenue' => (float) ($summary['total_subscription_revenue'] ?? 0),
            'total_payroll_service_fee' => (float) ($summary['total_payroll_service_fee'] ?? 0),
            'total_addon_revenue' => (float) ($summary['total_addon_revenue'] ?? 0),
            'total_gross_revenue' => (float) ($summary['total_gross_revenue'] ?? 0),
            'total_tax_due' => (float) ($summary['total_tax_due'] ?? 0),
            'total_net_revenue' => (float) ($summary['total_net_revenue'] ?? 0),
            'effective_tax_rate' => (float) ($summary['effective_tax_rate'] ?? 0),
        ];

        return response()->json([
            'success' => true,
            'data' => array_merge($report, [
                'summary_global' => $summaryGlobal,
                'tenants_global' => $tenantGlobal,
            ]),
        ]);
    }

    public function platformTaxCompliancePolicies(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.platform.policy.view')) {
            return $response;
        }

        if (!($request->user()?->isGlobalHcmAdmin() ?? false)) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Access denied for this operation.', 403);
        }

        $request->merge(['global_mode' => true]);
        $baseResponse = $this->platformBillingPolicies($request);
        $payload = $baseResponse->getData(true);

        if (isset($payload['data']['items_global']) && is_array($payload['data']['items_global'])) {
            $filteredItems = array_values(array_filter($payload['data']['items_global'], function (array $item): bool {
                return (string) ($item['source'] ?? '') === 'government_tax_compliance_policy';
            }));

            $activeByMonth = [];
            foreach ($filteredItems as $item) {
                $status = strtolower((string) ($item['status'] ?? ''));
                if ($status !== 'active') {
                    continue;
                }

                $month = (string) ($item['billing_month'] ?? 'unknown');
                if (! isset($activeByMonth[$month])) {
                    $activeByMonth[$month] = (string) ($item['version'] ?? '');
                }
            }

            $payload['data']['items_global'] = array_map(function (array $item) use ($activeByMonth): array {
                $item['government_tax_rate'] = (float) ($item['subscription_tax_rate'] ?? $item['tax_rate_percentage'] ?? 0);
                $item['payroll_component_rate'] = (float) ($item['payroll_service_fee'] ?? 0);
                $item['addon_component_rate'] = (float) ($item['addon_markup_rate'] ?? 0);

                $notesDecoded = json_decode((string) ($item['notes'] ?? ''), true);
                $transactionTaxRate = 0.0;
                if (is_array($notesDecoded)) {
                    // Support both legacy nested payload (notes.transaction_tax) and current direct payload.
                    $notesPayload = $notesDecoded;
                    if (! isset($notesPayload['transaction_tax'])) {
                        $rawNotes = $notesDecoded['notes'] ?? null;
                        $notesPayload = is_array($rawNotes)
                            ? $rawNotes
                            : (is_string($rawNotes) ? json_decode($rawNotes, true) : []);
                    }

                    if (is_array($notesPayload)) {
                        $transactionTaxRate = (float) ($notesPayload['transaction_tax']['tax_rate'] ?? 0);
                    }
                }
                $item['transaction_tax_rate'] = max(0.0, min(100.0, $transactionTaxRate));

                $month = (string) ($item['billing_month'] ?? 'unknown');
                $currentActiveVersion = (string) ($activeByMonth[$month] ?? '');
                $item['is_current_active_rule'] = $currentActiveVersion !== '' && $currentActiveVersion === (string) ($item['version'] ?? '');

                return $item;
            }, $filteredItems);
        }

        $payload['data']['view_context'] = 'government_tax_compliance';

        return response()->json($payload, $baseResponse->getStatusCode());
    }

    public function storePlatformTaxCompliancePolicy(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.platform.policy.manage')) {
            return $response;
        }

        if (!($request->user()?->isGlobalHcmAdmin() ?? false)) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Access denied for this operation.', 403);
        }

        $request->merge([
            'global_mode' => true,
            'policy_source' => 'government_tax_compliance_policy',
            'policy_domain' => 'platform_tax_compliance',
        ]);

        return $this->storePlatformBillingPolicy($request);
    }

    public function platformTaxComplianceReports(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.platform.report.view_all')) {
            return $response;
        }

        if (!($request->user()?->isGlobalHcmAdmin() ?? false)) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Access denied for this operation.', 403);
        }

        $baseResponse = $this->platformBillingReports($request);
        $payload = $baseResponse->getData(true);
        $selectedMonth = (string) $request->input('month', now()->format('Y-m'));
        $compliancePolicyConfigured = $this->hasGovernmentCompliancePolicyForMonth($selectedMonth);
        $policySnapshotsByCompany = $compliancePolicyConfigured
            ? $this->governmentCompliancePolicySnapshotsForMonth($selectedMonth)
            : [];
        $invoiceSnapshots = isset($payload['data']['invoice_snapshots']) && is_array($payload['data']['invoice_snapshots'])
            ? $payload['data']['invoice_snapshots']
            : [];
        $liabilityByCompany = [];

        if ($compliancePolicyConfigured && $invoiceSnapshots !== []) {
            foreach ($invoiceSnapshots as $invoice) {
                if (! is_array($invoice)) {
                    continue;
                }

                $companyId = (int) ($invoice['company_id'] ?? 0);
                if ($companyId <= 0) {
                    continue;
                }

                $amountDue = (float) ($invoice['amount_due'] ?? 0);
                if ($amountDue <= 0) {
                    continue;
                }

                $issueDate = is_string($invoice['issue_date'] ?? null) ? $invoice['issue_date'] : null;
                $rate = $this->resolveGovernmentTransactionTaxRateForInvoice($companyId, $issueDate, $policySnapshotsByCompany);
                $liability = round($amountDue * ($rate / 100), 2);
                $liabilityByCompany[$companyId] = round(($liabilityByCompany[$companyId] ?? 0.0) + $liability, 2);
            }
        }

        if (isset($payload['data']['tenants_global']) && is_array($payload['data']['tenants_global'])) {
            $payload['data']['tenants_global'] = array_map(function (array $item) use ($liabilityByCompany): array {
                $companyId = (int) ($item['company_id'] ?? 0);
                $item['collected_tax_liability'] = (float) ($liabilityByCompany[$companyId] ?? 0.0);

                return $item;
            }, $payload['data']['tenants_global']);

            $payload['data']['summary_global']['total_collected_tax_liability'] = round(
                array_reduce($payload['data']['tenants_global'], function (float $carry, array $item): float {
                    return $carry + (float) ($item['collected_tax_liability'] ?? 0);
                }, 0.0),
                2
            );
        }

        if (! $compliancePolicyConfigured) {
            $summaryGlobal = $payload['data']['summary_global'] ?? [];
            $grossRevenue = (float) ($summaryGlobal['total_gross_revenue'] ?? 0);

            $payload['data']['summary_global']['total_tax_due'] = 0.0;
            $payload['data']['summary_global']['total_collected_tax_liability'] = 0.0;
            $payload['data']['summary_global']['total_net_revenue'] = $grossRevenue;
            $payload['data']['summary_global']['effective_tax_rate'] = 0.0;

            if (isset($payload['data']['tenants_global']) && is_array($payload['data']['tenants_global'])) {
                $payload['data']['tenants_global'] = array_map(function (array $item): array {
                    $grossRevenue = (float) ($item['gross_revenue'] ?? 0);
                    $item['tax_amount_due'] = 0.0;
                    $item['collected_tax_liability'] = 0.0;
                    $item['net_revenue'] = $grossRevenue;

                    return $item;
                }, $payload['data']['tenants_global']);
            }
        }

        $summaryGlobal = $payload['data']['summary_global'] ?? [];
        $payload['data']['summary_compliance'] = [
            'total_taxable_revenue' => (float) ($summaryGlobal['total_gross_revenue'] ?? 0),
            'total_collected_tax_liability' => (float) ($summaryGlobal['total_collected_tax_liability'] ?? 0),
            'total_payroll_component' => (float) ($summaryGlobal['total_payroll_service_fee'] ?? 0),
            'total_addon_component' => (float) ($summaryGlobal['total_addon_revenue'] ?? 0),
            'total_tax_payable' => (float) ($summaryGlobal['total_tax_due'] ?? 0),
            'total_net_revenue' => (float) ($summaryGlobal['total_net_revenue'] ?? 0),
            'effective_tax_rate' => (float) ($summaryGlobal['effective_tax_rate'] ?? 0),
        ];

        if (isset($payload['data']['tenants_global']) && is_array($payload['data']['tenants_global'])) {
            $payload['data']['tenants_compliance'] = array_map(function (array $item): array {
                return array_merge($item, [
                    'taxable_revenue' => (float) ($item['gross_revenue'] ?? $item['taxable_revenue_amount'] ?? 0),
                    'collected_tax_liability' => (float) ($item['collected_tax_liability'] ?? 0),
                    'payroll_component' => (float) ($item['payroll_service_fee'] ?? 0),
                    'addon_component' => (float) ($item['addon_revenue'] ?? 0),
                    'total_tax_payable' => (float) ($item['tax_amount_due'] ?? 0),
                ]);
            }, $payload['data']['tenants_global']);
        }

        $payload['data']['view_context'] = 'government_tax_compliance';
        $payload['data']['policy_configured'] = $compliancePolicyConfigured;

        return response()->json($payload, $baseResponse->getStatusCode());
    }

    public function platformBillingInvoices(Request $request): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.platform.report.export_all')) {
            return $response;
        }

        if (!($request->user()?->isGlobalHcmAdmin() ?? false)) {
            return $this->errorResponse('AUTH_FORBIDDEN', 'Access denied for this operation.', 403);
        }

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $report = app(BillingTaxCalculationService::class)->generateCrossTenantMonthlyReport($validated['month']);

        return response()->json([
            'success' => true,
            'data' => [
                'month' => $validated['month'],
                'invoice_snapshots' => $report['invoice_snapshots'] ?? [],
            ],
        ]);
    }

    public function policyEventHistory(Request $request, string $policyRef): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.policy.view')) {
            return $response;
        }

        $usedNumericLegacy = false;
        $policy = $this->findPolicyForRequest($request, $policyRef, $usedNumericLegacy);
        if (!$policy) {
            return $this->errorResponse('TAX_POLICY_NOT_FOUND', 'Tax policy not found.', 404);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 50);

        $events = HcmTaxGovernancePolicyEvent::where('hcm_tax_governance_policy_id', $policy->id)
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);

        return $this->withNumericPolicyDeprecationHeaders(response()->json([
            'success' => true,
            'data' => [
                'policy_uuid' => $policy->uuid,
                'events' => collect($events->items())->map(function (HcmTaxGovernancePolicyEvent $event) {
                    return [
                        'event_uuid' => $event->uuid,
                        'event_type' => $event->event_type,
                        'timestamp' => $event->created_at->toIso8601String(),
                        'actor_user_id' => $event->actor_user_id,
                        'actor_email' => optional($event->actorUser)->email ?? 'System',
                        'note' => $event->note,
                        'before_state' => $event->before_state,
                        'after_state' => $event->after_state,
                    ];
                })->values(),
                'meta' => [
                    'page' => $events->currentPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                ],
            ],
        ]), $usedNumericLegacy);
    }

    private function withNumericPolicyDeprecationHeaders(JsonResponse $response, bool $usedNumericLegacy): JsonResponse
    {
        if (! $usedNumericLegacy) {
            return $response;
        }

        return $response
            ->header('Deprecation', self::NUMERIC_POLICY_ID_DEPRECATION)
            ->header('Sunset', self::NUMERIC_POLICY_ID_SUNSET_AT)
            ->header('Warning', '299 - "Numeric policy identifier is deprecated. Use UUID."');
    }

    /**
     * @param \Illuminate\Support\Collection<int, HcmBillingTaxPolicy> $policies
     * @return array<int, array<string, mixed>>
     */
    private function buildGlobalPlatformPolicyItems($policies): array
    {
        $items = [];
        $seen = [];

        foreach ($policies as $policy) {
            $rates = $this->extractGlobalRatesFromPolicy($policy);
            $key = (string) ($rates['propagation_key'] ?? '');
            if ($key === '') {
                $key = implode('|', [
                    (string) ($policy->billing_month ?? ''),
                    (string) $rates['subscription_tax_rate'],
                    (string) $rates['payroll_service_fee'],
                    (string) $rates['addon_markup_rate'],
                    (string) ($policy->billing_cycle_type ?? ''),
                    (string) $rates['source'],
                    (string) $rates['domain'],
                    (string) $policy->status,
                    (string) optional($policy->effective_from)?->toDateString(),
                    (string) optional($policy->created_at)?->toDateTimeString(),
                ]);
            }

            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $items[] = [
                'version' => 'v' . optional($policy->created_at)->format('YmdHis'),
                'billing_month' => (string) ($policy->billing_month ?? ''),
                'billing_cycle_type' => (string) ($policy->billing_cycle_type ?? ''),
                'subscription_tax_rate' => $rates['subscription_tax_rate'],
                'payroll_service_fee' => $rates['payroll_service_fee'],
                'addon_markup_rate' => $rates['addon_markup_rate'],
                'source' => $rates['source'],
                'domain' => $rates['domain'],
                'status' => $policy->status,
                'created_at' => optional($policy->created_at)?->toIso8601String(),
                'effective_from' => optional($policy->effective_from)?->toDateString(),
                'notes' => $rates['notes'],
            ];
        }

        return $items;
    }

    /**
    * @return array{subscription_tax_rate: float, payroll_service_fee: float, addon_markup_rate: float, notes: string, source: string, domain: string, propagation_key: string}
     */
    private function extractGlobalRatesFromPolicy(HcmBillingTaxPolicy $policy): array
    {
        $rawNotes = $policy->notes;
        $decoded = json_decode((string) $rawNotes, true);
        $globalRates = is_array($decoded) && isset($decoded['global_rates']) && is_array($decoded['global_rates'])
            ? $decoded['global_rates']
            : [];

        return [
            'subscription_tax_rate' => (float) ($globalRates['subscription_tax_rate'] ?? $policy->tax_rate_percentage ?? 0),
            'payroll_service_fee' => (float) ($globalRates['payroll_service_fee'] ?? 0),
            'addon_markup_rate' => (float) ($globalRates['addon_markup_rate'] ?? 0),
            'notes' => (string) ($decoded['notes'] ?? (string) ($rawNotes ?? '')),
            'source' => (string) ($decoded['source'] ?? 'global_platform_policy'),
            'domain' => (string) ($decoded['domain'] ?? 'platform_billing'),
            'propagation_key' => (string) ($decoded['propagation_key'] ?? ''),
        ];
    }

    private function hasGovernmentCompliancePolicyForMonth(string $month): bool
    {
        return HcmBillingTaxPolicy::query()
            ->where('billing_month', $month)
            ->where('status', 'active')
            ->where('notes', 'like', '%government_tax_compliance_policy%')
            ->exists();
    }

    /**
     * @return array<int, list<array{effective_from:?string, created_at:?string, transaction_tax_rate:float}>>
     */
    private function governmentCompliancePolicySnapshotsForMonth(string $month): array
    {
        $policies = HcmBillingTaxPolicy::query()
            ->where('billing_month', $month)
            ->where('status', 'active')
            ->where('notes', 'like', '%government_tax_compliance_policy%')
            ->orderBy('company_id')
            ->orderByDesc('effective_from')
            ->orderByDesc('created_at')
            ->get();

        $snapshots = [];
        foreach ($policies as $policy) {
            $companyId = (int) $policy->company_id;
            if ($companyId <= 0) {
                continue;
            }

            if (! isset($snapshots[$companyId])) {
                $snapshots[$companyId] = [];
            }

            $snapshots[$companyId][] = [
                'effective_from' => optional($policy->effective_from)?->toDateString(),
                'created_at' => optional($policy->created_at)?->toIso8601String(),
                'transaction_tax_rate' => $this->extractGovernmentTransactionTaxRate($policy),
            ];
        }

        return $snapshots;
    }

    /**
     * @param array<int, list<array{effective_from:?string, created_at:?string, transaction_tax_rate:float}>> $policySnapshotsByCompany
     */
    private function resolveGovernmentTransactionTaxRateForInvoice(int $companyId, ?string $issueDate, array $policySnapshotsByCompany): float
    {
        $rows = $policySnapshotsByCompany[$companyId] ?? [];
        if ($rows === []) {
            return 0.0;
        }

        // Data sudah diurutkan latest-first; ambil yang effective_from <= issue date.
        $targetTimestamp = $issueDate ? strtotime($issueDate) : false;
        if ($targetTimestamp === false) {
            return max(0.0, min(100.0, (float) ($rows[0]['transaction_tax_rate'] ?? 0.0)));
        }

        foreach ($rows as $row) {
            $effectiveTs = isset($row['effective_from']) && is_string($row['effective_from'])
                ? strtotime($row['effective_from'])
                : false;

            if ($effectiveTs === false || $effectiveTs <= $targetTimestamp) {
                return max(0.0, min(100.0, (float) ($row['transaction_tax_rate'] ?? 0.0)));
            }
        }

        return max(0.0, min(100.0, (float) ($rows[0]['transaction_tax_rate'] ?? 0.0)));
    }

    private function extractGovernmentTransactionTaxRate(HcmBillingTaxPolicy $policy): float
    {
        $decoded = json_decode((string) ($policy->notes ?? ''), true);
        if (! is_array($decoded)) {
            return 0.0;
        }

        $rawNotes = $decoded['notes'] ?? null;
        $notesPayload = is_array($rawNotes)
            ? $rawNotes
            : (is_string($rawNotes) ? json_decode($rawNotes, true) : null);

        $rate = is_array($notesPayload)
            ? (float) ($notesPayload['transaction_tax']['tax_rate'] ?? 0)
            : 0.0;

        return max(0.0, min(100.0, $rate));
    }

    private function renderTenantSelfAuditPdf(array $reportData): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);

        $companyName = (string) ($reportData['company_name'] ?? 'Unknown Company');
        $generatedAt = (string) ($reportData['report_generated_at'] ?? now()->toIso8601String());
        $periodStart = (string) ($reportData['period']['start'] ?? '-');
        $periodEnd = (string) ($reportData['period']['end'] ?? '-');
        $policyVersion = (string) ($reportData['policy_snapshot']['current_version'] ?? '-');
        $readinessScore = (string) ($reportData['compliance_checklist']['readiness_score'] ?? '-');
        $anomalyCount = (int) count($reportData['anomalies_detected'] ?? []);

        $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;}h1{font-size:18px;}table{width:100%;border-collapse:collapse;}td,th{border:1px solid #ccc;padding:6px;vertical-align:top;}th{background:#f5f5f5;text-align:left;}</style></head><body>';
        $html .= '<h1>Tenant Self-Audit Report</h1>';
        $html .= '<p><strong>Company:</strong> ' . e($companyName) . '<br><strong>Generated At:</strong> ' . e($generatedAt) . '</p>';
        $html .= '<table><tr><th>Period Start</th><th>Period End</th><th>Current Policy Version</th><th>Readiness Score</th><th>Unresolved Anomalies</th></tr>';
        $html .= '<tr><td>' . e($periodStart) . '</td><td>' . e($periodEnd) . '</td><td>' . e($policyVersion) . '</td><td>' . e($readinessScore) . '</td><td>' . e((string) $anomalyCount) . '</td></tr>';
        $html .= '</table>';
        $html .= '<p>Generated by Arkav Tax Governance Reporting.</p>';
        $html .= '</body></html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
    private function policyStateSnapshot(HcmTaxGovernancePolicy $policy): array
    {
        return [
            'uuid' => $policy->uuid,
            'status' => $policy->status,
            'effective_start_date' => optional($policy->effective_start_date)?->toDateString(),
            'effective_end_date' => optional($policy->effective_end_date)?->toDateString(),
            'version' => (int) $policy->version,
        ];
    }

    private function recordEvent(
        HcmTaxGovernancePolicy $policy,
        string $eventType,
        ?array $beforeState,
        ?array $afterState,
        ?string $note,
        ?int $actorUserId,
    ): void {
        HcmTaxGovernancePolicyEvent::query()->create([
            'company_id' => (int) $policy->company_id,
            'hcm_tax_governance_policy_id' => (int) $policy->id,
            'event_type' => $eventType,
            'actor_user_id' => $actorUserId,
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'note' => $note,
        ]);
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
}
