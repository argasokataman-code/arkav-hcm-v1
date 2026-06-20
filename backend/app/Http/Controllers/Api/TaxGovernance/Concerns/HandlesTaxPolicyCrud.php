<?php

namespace App\Http\Controllers\Api\TaxGovernance\Concerns;

use App\Events\TaxGovernancePolicyTransitioned;
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
use App\Modelsser;
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
use HandlesPlatformTaxGovernance;

trait HandlesTaxPolicyCrud
{
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
}
