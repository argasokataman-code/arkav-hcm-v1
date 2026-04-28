<?php

namespace App\Http\Controllers\Api;

use App\Events\TaxGovernancePolicyTransitioned;
use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\HcmBillingTaxPolicy;
use App\Models\HcmTaxGovernancePolicy;
use App\Models\HcmTaxGovernancePolicyEvent;
use App\Models\HcmTaxGovernanceProjection;
use App\Models\HcmTaxGovernanceAnomaly;
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

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->errorResponse('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $validated = $this->validateUpsertRequest($request, true);
        $actorId = (int) ($request->user()?->id ?? 0) ?: null;

        $policy = DB::transaction(function () use ($validated, $companyId, $actorId): HcmTaxGovernancePolicy {
            $policy = HcmTaxGovernancePolicy::query()->create([
                'company_id' => $companyId,
                'policy_code' => strtoupper((string) $validated['policyCode']),
                'name' => $validated['name'],
                'status' => HcmTaxGovernancePolicy::STATUS_DRAFT,
                'effective_start_date' => $validated['effectiveStartDate'],
                'effective_end_date' => $validated['effectiveEndDate'] ?? null,
                'rules' => $validated['rules'],
                'rate_schedules' => $validated['rateSchedules'],
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

        $usedNumericLegacy = false;
        $policy = $this->findPolicyForRequest($request, $policyRef, $usedNumericLegacy);
        if (! $policy) {
            return $this->errorResponse('TAX_POLICY_NOT_FOUND', 'Tax policy not found.', 404);
        }

        if ($policy->status !== HcmTaxGovernancePolicy::STATUS_DRAFT) {
            return $this->errorResponse('TAX_POLICY_INVALID_STATE_TRANSITION', 'Only draft policy can be updated.', 422);
        }

        $validated = $this->validateUpsertRequest($request, false);
        if (array_key_exists('version', $validated) && (int) $validated['version'] !== (int) $policy->version) {
            return $this->errorResponse('TAX_POLICY_VERSION_CONFLICT', 'Policy version conflict.', 409);
        }

        $before = $this->policyStateSnapshot($policy);
        $actorId = (int) ($request->user()?->id ?? 0) ?: null;

        DB::transaction(function () use ($policy, $validated, $before, $actorId): void {
            $policy->fill([
                'policy_code' => strtoupper((string) $validated['policyCode']),
                'name' => $validated['name'],
                'effective_start_date' => $validated['effectiveStartDate'],
                'effective_end_date' => $validated['effectiveEndDate'] ?? null,
                'rules' => $validated['rules'],
                'rate_schedules' => $validated['rateSchedules'],
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

        $usedNumericLegacy = false;
        $policy = $this->findPolicyForRequest($request, $policyRef, $usedNumericLegacy);
        if (! $policy) {
            return $this->errorResponse('TAX_POLICY_NOT_FOUND', 'Tax policy not found.', 404);
        }

        if ($policy->status !== HcmTaxGovernancePolicy::STATUS_DRAFT) {
            return $this->errorResponse('TAX_POLICY_INVALID_STATE_TRANSITION', 'Only draft policy can be submitted.', 422);
        }

        $validated = $request->validate([
            'submissionNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $before = $this->policyStateSnapshot($policy);
        $actorId = (int) ($request->user()?->id ?? 0) ?: null;
        $previousStatus = $policy->status;

        DB::transaction(function () use ($policy, $validated, $before, $actorId): void {
            $policy->status = HcmTaxGovernancePolicy::STATUS_SUBMITTED;
            $policy->submitted_by_user_id = $actorId;
            $policy->submitted_at = now();
            $policy->last_note = $validated['submissionNote'] ?? null;
            $policy->save();

            $this->recordEvent(
                $policy,
                'submitted',
                $before,
                $this->policyStateSnapshot($policy),
                $validated['submissionNote'] ?? null,
                $actorId,
            );
        });

        $policy->refresh();
        
        // Dispatch event for projection sync
        TaxGovernancePolicyTransitioned::dispatch($policy, $previousStatus, $policy->status, (int) ($actorId ?? 0));

        return $this->withNumericPolicyDeprecationHeaders(
            response()->json(['success' => true, 'data' => $this->policyPayload($policy)]),
            $usedNumericLegacy
        );
    }

    public function approve(Request $request, string $policyRef): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.policy.approve')) {
            return $response;
        }

        $usedNumericLegacy = false;
        $policy = $this->findPolicyForRequest($request, $policyRef, $usedNumericLegacy);
        if (! $policy) {
            return $this->errorResponse('TAX_POLICY_NOT_FOUND', 'Tax policy not found.', 404);
        }

        if ($policy->status !== HcmTaxGovernancePolicy::STATUS_SUBMITTED) {
            return $this->errorResponse('TAX_POLICY_INVALID_STATE_TRANSITION', 'Only submitted policy can be approved.', 422);
        }

        $validated = $request->validate([
            'approvalNote' => ['required', 'string', 'max:1000'],
        ]);

        $actorId = (int) ($request->user()?->id ?? 0) ?: null;

        // Tenant isolation: approver must be a member of the policy's own company.
        // Blocks global admins from participating in tenant approval workflows.
        if ($actorId && ! CompanyUser::where('user_id', $actorId)->where('company_id', $policy->company_id)->exists()) {
            return $this->errorResponse('TAX_POLICY_TENANT_ISOLATION_VIOLATION', 'Approver must be an active member of the policy tenant.', 403);
        }

        if ($actorId && (int) $policy->created_by_user_id === $actorId) {
            return $this->errorResponse('TAX_POLICY_SOD_VIOLATION', 'Maker cannot approve their own policy.', 403);
        }

        $before = $this->policyStateSnapshot($policy);
        $previousStatus = $policy->status;

        DB::transaction(function () use ($policy, $validated, $before, $actorId): void {
            $policy->status = HcmTaxGovernancePolicy::STATUS_APPROVED;
            $policy->approved_by_user_id = $actorId;
            $policy->approved_at = now();
            $policy->last_note = $validated['approvalNote'];
            $policy->save();

            $this->recordEvent($policy, 'approved', $before, $this->policyStateSnapshot($policy), $validated['approvalNote'], $actorId);
        });

        $policy->refresh();

        // Dispatch event for projection sync
        TaxGovernancePolicyTransitioned::dispatch($policy, $previousStatus, $policy->status, (int) ($actorId ?? 0));

        return $this->withNumericPolicyDeprecationHeaders(
            response()->json(['success' => true, 'data' => $this->policyPayload($policy)]),
            $usedNumericLegacy
        );
    }

    public function reject(Request $request, string $policyRef): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.policy.approve')) {
            return $response;
        }

        $usedNumericLegacy = false;
        $policy = $this->findPolicyForRequest($request, $policyRef, $usedNumericLegacy);
        if (! $policy) {
            return $this->errorResponse('TAX_POLICY_NOT_FOUND', 'Tax policy not found.', 404);
        }

        if ($policy->status !== HcmTaxGovernancePolicy::STATUS_SUBMITTED) {
            return $this->errorResponse('TAX_POLICY_INVALID_STATE_TRANSITION', 'Only submitted policy can be rejected.', 422);
        }

        $validated = $request->validate([
            'rejectionNote' => ['required', 'string', 'max:1000'],
        ]);

        $actorId = (int) ($request->user()?->id ?? 0) ?: null;

        // Tenant isolation: rejector must be a member of the policy's own company.
        if ($actorId && ! CompanyUser::where('user_id', $actorId)->where('company_id', $policy->company_id)->exists()) {
            return $this->errorResponse('TAX_POLICY_TENANT_ISOLATION_VIOLATION', 'Rejector must be an active member of the policy tenant.', 403);
        }

        if ($actorId && (int) $policy->created_by_user_id === $actorId) {
            return $this->errorResponse('TAX_POLICY_SOD_VIOLATION', 'Maker cannot reject their own policy.', 403);
        }

        $before = $this->policyStateSnapshot($policy);
        DB::transaction(function () use ($policy, $validated, $before, $actorId): void {
            $policy->status = HcmTaxGovernancePolicy::STATUS_DRAFT;
            $policy->last_note = $validated['rejectionNote'];
            $policy->save();

            $this->recordEvent($policy, 'rejected', $before, $this->policyStateSnapshot($policy), $validated['rejectionNote'], $actorId);
        });

        $policy->refresh();

        // Dispatch event for projection sync
        TaxGovernancePolicyTransitioned::dispatch($policy, 'submitted', $policy->status, (int) ($actorId ?? 0));

        return $this->withNumericPolicyDeprecationHeaders(
            response()->json(['success' => true, 'data' => $this->policyPayload($policy)]),
            $usedNumericLegacy
        );
    }

    public function publish(Request $request, string $policyRef): JsonResponse
    {
        if ($response = $this->ensurePermission($request, 'tax.tenant.policy.publish')) {
            return $response;
        }

        $usedNumericLegacy = false;
        $policy = $this->findPolicyForRequest($request, $policyRef, $usedNumericLegacy);
        if (! $policy) {
            return $this->errorResponse('TAX_POLICY_NOT_FOUND', 'Tax policy not found.', 404);
        }

        if ($policy->status !== HcmTaxGovernancePolicy::STATUS_APPROVED) {
            return $this->errorResponse('TAX_POLICY_INVALID_STATE_TRANSITION', 'Only approved policy can be published.', 422);
        }

        $validated = $request->validate([
            'publishReason' => ['required', 'string', 'max:1000'],
            'effectiveStartDate' => ['required', 'date'],
        ]);

        $actorId = (int) ($request->user()?->id ?? 0) ?: null;

        // Tenant isolation: publisher must be a member of the policy's own company.
        if ($actorId && ! CompanyUser::where('user_id', $actorId)->where('company_id', $policy->company_id)->exists()) {
            return $this->errorResponse('TAX_POLICY_TENANT_ISOLATION_VIOLATION', 'Publisher must be an active member of the policy tenant.', 403);
        }

        if ($actorId && (int) $policy->created_by_user_id === $actorId) {
            return $this->errorResponse('TAX_POLICY_SOD_VIOLATION', 'Maker cannot publish their own policy.', 403);
        }

        $before = $this->policyStateSnapshot($policy);
        $previousStatus = $policy->status;

        DB::transaction(function () use ($policy, $validated, $before, $actorId): void {
            $policy->status = HcmTaxGovernancePolicy::STATUS_PUBLISHED;
            $policy->effective_start_date = $validated['effectiveStartDate'];
            $policy->published_by_user_id = $actorId;
            $policy->published_at = now();
            $policy->last_note = $validated['publishReason'];
            $policy->save();

            $this->recordEvent($policy, 'published', $before, $this->policyStateSnapshot($policy), $validated['publishReason'], $actorId);
        });

        $policy->refresh();
        
        // Dispatch event for projection sync
        TaxGovernancePolicyTransitioned::dispatch($policy, $previousStatus, $policy->status, (int) ($actorId ?? 0));

        return $this->withNumericPolicyDeprecationHeaders(
            response()->json(['success' => true, 'data' => $this->policyPayload($policy)]),
            $usedNumericLegacy
        );
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

        $overallStatus = ($currentPolicy && $unresolvedAnomalies === 0 && (int) ($billingCompliance['unpaid_invoice_count'] ?? 0) === 0)
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

        return response()->json([
            'success' => true,
            'data' => [
                'company_id' => (int) $companyId,
                'company_name' => $company->name,
                'reporting_period' => now()->format('Y-\QQ'),
                'compliance_status' => [
                    'statutory_tax_compliance' => [
                        'has_active_policy' => (bool) $currentPolicy,
                        'policy_version' => $currentPolicy?->version,
                        'last_publication_date' => optional($currentPolicy?->published_at)?->toDateString(),
                        'anomalies_unresolved' => $unresolvedAnomalies,
                    ],
                    'billing_tax_compliance' => [
                        'billing_cycle_active' => !empty($billingCompliance['policy_uuid']),
                        'invoices_issued' => (int) ($billingCompliance['invoice_count'] ?? 0),
                        'invoices_paid' => (int) ($billingCompliance['paid_invoice_count'] ?? 0),
                        'amount_outstanding' => (float) ($billingCompliance['outstanding_invoice_amount'] ?? 0),
                        'taxable_revenue_amount' => (float) ($billingCompliance['taxable_revenue_amount'] ?? 0),
                        'cleared_revenue_amount' => (float) ($billingCompliance['cleared_revenue_amount'] ?? 0),
                        'uncleared_revenue_amount' => (float) ($billingCompliance['uncleared_revenue_amount'] ?? 0),
                        'disputed_revenue_amount' => (float) ($billingCompliance['disputed_revenue_amount'] ?? 0),
                        'reversed_revenue_amount' => (float) ($billingCompliance['reversed_revenue_amount'] ?? 0),
                        'payment_status' => ((int) ($billingCompliance['unpaid_invoice_count'] ?? 0) === 0) ? 'current' : 'overdue',
                    ],
                    'overall_status' => $overallStatus,
                    'next_review_date' => now()->addMonth()->toDateString(),
                ],
                'recommended_actions' => $recommendedActions,
            ],
        ]);
    }

    private function validateUpsertRequest(Request $request, bool $isCreate): array
    {
        $rules = [
            'policyCode' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'effectiveStartDate' => ['required', 'date'],
            'effectiveEndDate' => ['nullable', 'date', 'after_or_equal:effectiveStartDate'],
            'rules' => ['required', 'array'],
            'rules.scheme' => ['required', 'string', 'in:TER'],
            'rules.currency' => ['nullable', 'string', 'in:IDR'],
            'rateSchedules' => ['required', 'array'],
            'rateSchedules.*.bracket' => ['required', 'string', 'in:A,B,C'],
            'rateSchedules.*.rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'rateSchedules.*.upperBound' => ['nullable', 'numeric', 'gt:0'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];

        if ($isCreate) {
            unset($rules['version']);
        }

        return $request->validate($rules);
    }

    private function findPolicyForRequest(Request $request, string $policyRef, bool &$usedNumericLegacy = false): ?HcmTaxGovernancePolicy
    {
        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return null;
        }

        $usedNumericLegacy = ctype_digit($policyRef);

        if ($usedNumericLegacy) {
            Log::notice('tax_governance.numeric_policy_reference_used', [
                'user_id' => (int) ($request->user()?->id ?? 0) ?: null,
                'company_id' => (int) $companyId,
                'method' => $request->method(),
                'path' => $request->path(),
                'policy_reference' => $policyRef,
                'sunset_at' => self::NUMERIC_POLICY_ID_SUNSET_AT,
            ]);
        }

        $query = HcmTaxGovernancePolicy::query()
            ->where('company_id', $companyId);

        if ($usedNumericLegacy) {
            $query->where('id', (int) $policyRef);
        } else {
            $query->where('uuid', $policyRef);
        }

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

        $rows = $query->paginate((int) ($validated['per_page'] ?? 20));

        $rawItems = collect($rows->items());
        $globalMode = (bool) ($validated['global_mode'] ?? false);
        $globalItems = $this->buildGlobalPlatformPolicyItems($rawItems);

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

        $isGlobalPayload = $request->hasAny(['subscription_tax_rate', 'payroll_service_fee', 'addon_markup_rate']);

        if ($isGlobalPayload) {
            $validated = $request->validate([
                'subscription_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'payroll_service_fee' => ['required', 'numeric', 'min:0', 'max:100'],
                'addon_markup_rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'billing_month' => ['nullable', 'date_format:Y-m'],
                'effective_from' => ['nullable', 'date'],
                'status' => ['nullable', Rule::in(['draft', 'active', 'inactive'])],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]);

            $service = app(BillingTaxCalculationService::class);
            if (! $service->validateBillingTaxPolicy([
                'tax_rate_percentage' => $validated['subscription_tax_rate'],
                'billing_cycle_type' => 'monthly',
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
                'payroll_service_fee' => (float) $validated['payroll_service_fee'],
                'addon_markup_rate' => (float) $validated['addon_markup_rate'],
            ];

            $notesPayload = [
                'global_rates' => $globalRates,
                'notes' => $validated['notes'] ?? null,
                'source' => 'global_platform_policy',
            ];

            DB::transaction(function () use ($companyIds, $billingMonth, $effectiveFrom, $status, $globalRates, $notesPayload, $actorId): void {
                foreach ($companyIds as $companyId) {
                    $policy = HcmBillingTaxPolicy::query()->firstOrNew([
                        'company_id' => $companyId,
                        'billing_month' => $billingMonth,
                        'billing_cycle_type' => 'monthly',
                    ]);

                    if (! $policy->exists) {
                        $policy->id = (string) Str::uuid();
                        $policy->created_by_user_id = $actorId;
                    }

                    $policy->tax_rate_percentage = $globalRates['subscription_tax_rate'];
                    $policy->base_calculation_method = 'invoice_amount_due';
                    $policy->effective_from = $effectiveFrom;
                    $policy->effective_to = null;
                    $policy->status = $status;
                    $policy->notes = json_encode($notesPayload, JSON_UNESCAPED_UNICODE);
                    $policy->updated_by_user_id = $actorId;
                    $policy->save();
                }
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'version' => 'v' . now()->format('YmdHis'),
                    'billing_month' => $billingMonth,
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
            $payload['data']['items_global'] = array_map(function (array $item): array {
                $item['government_tax_rate'] = (float) ($item['subscription_tax_rate'] ?? $item['tax_rate_percentage'] ?? 0);
                $item['payroll_component_rate'] = (float) ($item['payroll_service_fee'] ?? 0);
                $item['addon_component_rate'] = (float) ($item['addon_markup_rate'] ?? 0);

                return $item;
            }, $payload['data']['items_global']);
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

        $request->merge(['global_mode' => true]);

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

        $summaryGlobal = $payload['data']['summary_global'] ?? [];
        $payload['data']['summary_compliance'] = [
            'total_taxable_revenue' => (float) ($summaryGlobal['total_gross_revenue'] ?? 0),
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
                    'payroll_component' => (float) ($item['payroll_service_fee'] ?? 0),
                    'addon_component' => (float) ($item['addon_revenue'] ?? 0),
                    'total_tax_payable' => (float) ($item['tax_amount_due'] ?? 0),
                ]);
            }, $payload['data']['tenants_global']);
        }

        $payload['data']['view_context'] = 'government_tax_compliance';

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
            $key = implode('|', [
                (string) $rates['subscription_tax_rate'],
                (string) $rates['payroll_service_fee'],
                (string) $rates['addon_markup_rate'],
                (string) $policy->status,
                (string) optional($policy->effective_from)?->toDateString(),
                (string) optional($policy->created_at)?->toDateTimeString(),
            ]);

            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $items[] = [
                'version' => 'v' . optional($policy->created_at)->format('YmdHis'),
                'subscription_tax_rate' => $rates['subscription_tax_rate'],
                'payroll_service_fee' => $rates['payroll_service_fee'],
                'addon_markup_rate' => $rates['addon_markup_rate'],
                'status' => $policy->status,
                'created_at' => optional($policy->created_at)?->toIso8601String(),
                'effective_from' => optional($policy->effective_from)?->toDateString(),
                'notes' => $rates['notes'],
            ];
        }

        return $items;
    }

    /**
     * @return array{subscription_tax_rate: float, payroll_service_fee: float, addon_markup_rate: float, notes: string}
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
        ];
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
