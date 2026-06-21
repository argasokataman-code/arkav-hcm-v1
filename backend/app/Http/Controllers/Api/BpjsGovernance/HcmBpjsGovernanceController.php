<?php

namespace App\Http\Controllers\Api\BpjsGovernance;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\CompanyUser;
use App\Models\EmployeeBenefit;
use App\Models\EmployeeProfile;
use App\Models\HcmBpjsGovernancePolicy;
use App\Models\HcmBpjsGovernancePolicyHistory;
use App\Models\HcmBpjsGovernanceRateBaseline;
use App\Models\HcmSalaryComponent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HcmBpjsGovernanceController extends Controller
{
    use ChecksPermissions;

    public function reference(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'programCodes' => $this->programCodes(),
                'contributionParties' => $this->contributionParties(),
                'wageBases' => $this->wageBases(),
                'regulatoryNotes' => [
                    'BPJS adalah domain jaminan sosial dan bukan domain pajak (PPh 21).',
                    'Tarif dan basis iuran wajib mengikuti regulasi pemerintah yang berlaku.',
                    'Perubahan tarif harus disertai periode efektif yang terdokumentasi.',
                ],
            ],
        ]);
    }

    public function indexPolicies(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->error('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $validated = $request->validate([
            'active_only' => ['nullable', 'boolean'],
            'as_of' => ['nullable', 'date'],
        ]);

        $asOf = isset($validated['as_of']) ? Carbon::parse((string) $validated['as_of'])->toDateString() : now()->toDateString();
        $activeOnly = (bool) ($validated['active_only'] ?? false);

        $query = HcmBpjsGovernancePolicy::query()
            ->where('company_id', $companyId)
            ->orderBy('program_code')
            ->orderBy('contribution_party')
            ->orderByDesc('effective_start_date')
            ->orderByDesc('id');

        if ($activeOnly) {
            $query->where('is_active', true)
                ->whereDate('effective_start_date', '<=', $asOf)
                ->where(function ($builder) use ($asOf): void {
                    $builder->whereNull('effective_end_date')
                        ->orWhereDate('effective_end_date', '>=', $asOf);
                });
        }

        $rows = $query->get()->map(fn (HcmBpjsGovernancePolicy $policy) => $this->policyPayload($policy))->values();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $rows,
                'meta' => [
                    'total' => $rows->count(),
                    'as_of' => $asOf,
                ],
            ],
        ]);
    }

    public function policyHistory(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->error('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $limit = (int) ($validated['limit'] ?? 50);

        $rows = HcmBpjsGovernancePolicyHistory::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $actorIds = $rows->pluck('changed_by_user_id')->filter()->unique()->values();
        $actorMap = User::query()
            ->whereIn('id', $actorIds)
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        $items = $rows->map(function (HcmBpjsGovernancePolicyHistory $row) use ($actorMap): array {
            $snapshot = is_array($row->snapshot) ? $row->snapshot : [];
            $actor = $row->changed_by_user_id ? $actorMap->get((int) $row->changed_by_user_id) : null;

            return [
                'id' => (int) $row->id,
                'actionType' => $row->action_type,
                'policyUuid' => $row->policy_uuid,
                'programCode' => $snapshot['programCode'] ?? null,
                'contributionParty' => $snapshot['contributionParty'] ?? null,
                'ratePercent' => isset($snapshot['ratePercent']) ? (string) $snapshot['ratePercent'] : null,
                'wageBase' => $snapshot['wageBase'] ?? null,
                'effectiveStartDate' => $snapshot['effectiveStartDate'] ?? null,
                'effectiveEndDate' => $snapshot['effectiveEndDate'] ?? null,
                'legalBasis' => $snapshot['legalBasis'] ?? null,
                'notes' => $snapshot['notes'] ?? null,
                'isActive' => $snapshot['isActive'] ?? null,
                'changedByUserId' => $row->changed_by_user_id,
                'changedByUserName' => $actor?->name,
                'changedByUserEmail' => $actor?->email,
                'changedAt' => optional($row->created_at)->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'meta' => [
                    'limit' => $limit,
                    'total' => $items->count(),
                ],
            ],
        ]);
    }

    public function storePolicy(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->error('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $validated = $this->validatePolicyPayload($request, true);
        $this->enforcePolicyBusinessRules($validated, $companyId, null);

        $user = $request->user();
        $policy = HcmBpjsGovernancePolicy::query()->create([
            'company_id' => $companyId,
            'program_code' => (string) $validated['programCode'],
            'contribution_party' => (string) $validated['contributionParty'],
            'rate_percent' => number_format((float) $validated['ratePercent'], 4, '.', ''),
            'wage_base' => $validated['wageBase'] ?? null,
            'effective_start_date' => (string) $validated['effectiveStartDate'],
            'effective_end_date' => $validated['effectiveEndDate'] ?? null,
            'legal_basis' => $validated['legalBasis'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => array_key_exists('isActive', $validated) ? (bool) $validated['isActive'] : true,
            'created_by_user_id' => $user?->id,
            'created_by_user_uuid' => $user?->uuid,
            'updated_by_user_id' => $user?->id,
            'updated_by_user_uuid' => $user?->uuid,
        ]);

        $this->appendPolicyHistory($policy, 'created', $user);

        $this->ensureBpjsSalaryComponents($companyId);

        return response()->json([
            'success' => true,
            'data' => $this->policyPayload($policy),
        ], 201);
    }

    public function updatePolicy(Request $request, string $policyRef): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->error('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $query = HcmBpjsGovernancePolicy::query()->where('company_id', $companyId);
        // UUID (primary) or numeric ID (legacy migration window)
        if (str_contains($policyRef, '-')) {
            $query->where('uuid', $policyRef);
        } else {
            $query->where('id', (int) $policyRef);
        }
        $policy = $query->first();

        if (! $policy) {
            return $this->error('BPJS_POLICY_NOT_FOUND', 'BPJS policy not found.', 404);
        }

        $validated = $this->validatePolicyUpdatePayload($request);
        $this->enforcePolicyBusinessRules($validated, $companyId, $policy);

        $user = $request->user();
        $policy->fill([
            'rate_percent' => array_key_exists('ratePercent', $validated)
                ? number_format((float) $validated['ratePercent'], 4, '.', '')
                : $policy->rate_percent,
            'legal_basis' => array_key_exists('legalBasis', $validated) ? ($validated['legalBasis'] ?? null) : $policy->legal_basis,
            'notes' => array_key_exists('notes', $validated) ? ($validated['notes'] ?? null) : $policy->notes,
            'is_active' => array_key_exists('isActive', $validated) ? (bool) $validated['isActive'] : $policy->is_active,
            'updated_by_user_id' => $user?->id,
            'updated_by_user_uuid' => $user?->uuid,
        ]);
        $policy->save();

        $this->appendPolicyHistory($policy, 'updated', $user);

        return response()->json([
            'success' => true,
            'data' => $this->policyPayload($policy),
        ]);
    }

    public function destroyPolicy(Request $request, string $policyRef): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->error('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $query = HcmBpjsGovernancePolicy::query()->where('company_id', $companyId);
        if (str_contains($policyRef, '-')) {
            $query->where('uuid', $policyRef);
        } else {
            $query->where('id', (int) $policyRef);
        }

        $policy = $query->first();
        if (! $policy) {
            return $this->error('BPJS_POLICY_NOT_FOUND', 'BPJS policy not found.', 404);
        }

        $user = $request->user();
        $this->appendPolicyHistory($policy, 'deleted', $user);

        $policy->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'deleted' => true,
                'policyRef' => $policyRef,
            ],
        ]);
    }

    public function employeeMembership(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->error('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['perPage'] ?? 20);

        $companyUserIds = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('role', '!=', 'owner')
            ->pluck('user_id');

        $membershipSummary = $this->membershipCoverageSummary($companyUserIds);

        $usersQuery = User::query()
            ->whereIn('id', $companyUserIds)
            ->with(['employeeProfile.benefits'])
            ->orderBy('name');

        if (! empty($validated['search'])) {
            $search = '%'.trim((string) $validated['search']).'%';
            $usersQuery->where(function ($query) use ($search): void {
                $query->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        $paginator = $usersQuery->paginate($perPage);

        $rows = collect($paginator->items())->map(function (User $user): array {
            $profile = $user->employeeProfile;
            $latestBenefit = $profile
                ? $profile->benefits()
                    ->orderByDesc('effective_date')
                    ->orderByDesc('id')
                    ->first()
                : null;

            $bpjsKes = (string) ($latestBenefit?->bpjs_kesehatan_no ?? '');
            $bpjsTk = (string) ($latestBenefit?->bpjs_ketenagakerjaan_no ?? '');
            $status = $this->membershipStatus($bpjsKes, $bpjsTk);

            return [
                'id' => (int) $user->id,
                'uuid' => $user->uuid,
                'fullName' => $user->name,
                'email' => $user->email,
                'bpjsKesehatanNo' => $bpjsKes,
                'bpjsKetenagakerjaanNo' => $bpjsTk,
                'membershipStatus' => $status,
                'effectiveDate' => optional($latestBenefit?->effective_date)->toDateString(),
            ];
        })->values();

        $displayedCompleteCount = $rows->filter(fn (array $row): bool => $row['membershipStatus'] === 'complete')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $rows,
                'meta' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $membershipSummary['total'],
                    'complete' => $membershipSummary['complete'],
                    'partial' => $membershipSummary['partial'],
                    'missing' => $membershipSummary['missing'],
                    'filteredTotal' => $paginator->total(),
                    'displayedTotal' => $rows->count(),
                    'displayedComplete' => $displayedCompleteCount,
                ],
            ],
        ]);
    }

    /**
     * @param  Collection<int, int|string>  $companyUserIds
     * @return array{total:int, complete:int, partial:int, missing:int}
     */
    private function membershipCoverageSummary($companyUserIds): array
    {
        if ($companyUserIds->isEmpty()) {
            return ['total' => 0, 'complete' => 0, 'partial' => 0, 'missing' => 0];
        }

        $profiles = EmployeeProfile::query()
            ->whereIn('user_id', $companyUserIds)
            ->get(['id', 'user_id']);

        $profileByUserId = $profiles->keyBy('user_id');
        $latestMembership = EmployeeBenefit::query()
            ->whereIn('employee_id', $profiles->pluck('id'))
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($items) => $items->first());

        $counts = ['complete' => 0, 'partial' => 0, 'missing' => 0];

        foreach ($companyUserIds as $userId) {
            $profile = $profileByUserId->get($userId);
            if (! $profile) {
                $counts['missing']++;

                continue;
            }

            $benefit = $latestMembership->get((int) $profile->id);
            if (! $benefit) {
                $counts['missing']++;

                continue;
            }

            $status = $this->membershipStatus(
                (string) ($benefit->bpjs_kesehatan_no ?? ''),
                (string) ($benefit->bpjs_ketenagakerjaan_no ?? '')
            );

            $counts[$status]++;
        }

        return [
            'total' => (int) $companyUserIds->count(),
            'complete' => (int) $counts['complete'],
            'partial' => (int) $counts['partial'],
            'missing' => (int) $counts['missing'],
        ];
    }

    public function updateEmployeeMembership(Request $request, int $userId): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'employee.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->error('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $belongsToCompany = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('role', '!=', 'owner')
            ->exists();

        if (! $belongsToCompany) {
            return $this->error('EMPLOYEE_NOT_FOUND', 'Employee not found in active company.', 404);
        }

        $validated = $request->validate([
            'bpjsKesehatanNo' => ['nullable', 'string', 'max:100'],
            'bpjsKetenagakerjaanNo' => ['nullable', 'string', 'max:100'],
            'effectiveDate' => ['nullable', 'date'],
        ]);

        $profile = EmployeeProfile::query()->firstOrCreate(
            ['user_id' => $userId],
            ['company_id' => $companyId, 'employment_status' => 'active', 'contract_type' => 'permanent'],
        );

        $effectiveDate = isset($validated['effectiveDate'])
            ? Carbon::parse((string) $validated['effectiveDate'])->toDateString()
            : now()->toDateString();

        $latestBenefit = EmployeeBenefit::query()
            ->where('employee_id', $profile->id)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();

        // Always create a new record to preserve versioned membership history
        $benefit = EmployeeBenefit::query()->create([
            'employee_id' => $profile->id,
            'bpjs_kesehatan_no' => $validated['bpjsKesehatanNo'] ?? null,
            'bpjs_ketenagakerjaan_no' => $validated['bpjsKetenagakerjaanNo'] ?? null,
            'effective_date' => $effectiveDate,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'userId' => $userId,
                'bpjsKesehatanNo' => (string) ($benefit->bpjs_kesehatan_no ?? ''),
                'bpjsKetenagakerjaanNo' => (string) ($benefit->bpjs_ketenagakerjaan_no ?? ''),
                'effectiveDate' => optional($benefit->effective_date)->toDateString(),
                'membershipStatus' => $this->membershipStatus((string) ($benefit->bpjs_kesehatan_no ?? ''), (string) ($benefit->bpjs_ketenagakerjaan_no ?? '')),
            ],
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->error('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $asOf = now()->toDateString();
        $policyRows = HcmBpjsGovernancePolicy::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereDate('effective_start_date', '<=', $asOf)
            ->where(function ($builder) use ($asOf): void {
                $builder->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $asOf);
            })
            ->get();

        $programCoverage = collect($this->programCodes())->mapWithKeys(fn (string $program) => [$program => [
            'employee' => $policyRows->first(fn (HcmBpjsGovernancePolicy $row): bool => $row->program_code === $program && $row->contribution_party === 'employee') !== null,
            'employer' => $policyRows->first(fn (HcmBpjsGovernancePolicy $row): bool => $row->program_code === $program && $row->contribution_party === 'employer') !== null,
        ]]);

        $rateAudits = $policyRows->map(function (HcmBpjsGovernancePolicy $policy) use ($companyId): array {
            $baseline = $this->regulatoryBaselineFor($policy->program_code, $policy->contribution_party, $companyId);

            if ($baseline === null) {
                return [
                    'policyId' => (int) $policy->id,
                    'programCode' => $policy->program_code,
                    'contributionParty' => $policy->contribution_party,
                    'ratePercent' => (float) $policy->rate_percent,
                    'result' => 'unknown_baseline',
                ];
            }

            $actualRate = (float) $policy->rate_percent;
            $ratePass = $actualRate >= (float) $baseline['minRate'] && $actualRate <= (float) $baseline['maxRate'];
            $wageBasePass = $baseline['wageBase'] === null || (string) ($policy->wage_base ?? '') === (string) $baseline['wageBase'];
            $legalBasisPass = trim((string) ($policy->legal_basis ?? '')) !== '';

            return [
                'policyId' => (int) $policy->id,
                'programCode' => $policy->program_code,
                'contributionParty' => $policy->contribution_party,
                'ratePercent' => $actualRate,
                'expectedRateMin' => (float) $baseline['minRate'],
                'expectedRateMax' => (float) $baseline['maxRate'],
                'expectedWageBase' => $baseline['wageBase'],
                'ratePass' => $ratePass,
                'wageBasePass' => $wageBasePass,
                'legalBasisPass' => $legalBasisPass,
                'result' => ($ratePass && $wageBasePass && $legalBasisPass) ? 'pass' : 'review_required',
            ];
        })->values();

        $companyUserIds = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('role', '!=', 'owner')
            ->pluck('user_id');

        $activeUsers = User::query()
            ->whereIn('id', $companyUserIds)
            ->get(['id', 'uuid', 'name', 'email'])
            ->keyBy('id');

        $profiles = EmployeeProfile::query()
            ->whereIn('user_id', $companyUserIds)
            ->get(['id', 'user_id']);

        $profileIds = $profiles->pluck('id');
        $profileByUserId = $profiles->keyBy('user_id');

        $latestMembership = EmployeeBenefit::query()
            ->whereIn('employee_id', $profileIds)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($items) => $items->first());

        $totalEmployees = $companyUserIds->count();
        $membershipCounts = ['complete' => 0, 'partial' => 0, 'missing' => 0];
        $nonCompliantEmployees = [];

        foreach ($companyUserIds as $userId) {
            $user = $activeUsers->get((int) $userId);
            $profile = $profileByUserId->get($userId);
            if (! $profile) {
                $membershipCounts['missing']++;
                $nonCompliantEmployees[] = [
                    'userId' => (int) $userId,
                    'userUuid' => $user?->uuid,
                    'fullName' => $user?->name ?? 'Unknown Employee',
                    'email' => $user?->email,
                    'membershipStatus' => 'missing',
                    'issues' => [
                        ['code' => 'employee_profile_missing', 'label' => 'Profil karyawan belum tersedia untuk evaluasi membership BPJS.'],
                    ],
                ];

                continue;
            }

            /** @var EmployeeBenefit|null $benefit */
            $benefit = $latestMembership->get((int) $profile->id);
            if (! $benefit) {
                $membershipCounts['missing']++;
                $nonCompliantEmployees[] = [
                    'userId' => (int) $userId,
                    'userUuid' => $user?->uuid,
                    'fullName' => $user?->name ?? 'Unknown Employee',
                    'email' => $user?->email,
                    'membershipStatus' => 'missing',
                    'issues' => [
                        ['code' => 'bpjs_membership_missing', 'label' => 'Nomor BPJS Kesehatan dan BPJS Ketenagakerjaan belum diisi.'],
                    ],
                ];

                continue;
            }

            $bpjsKes = trim((string) ($benefit->bpjs_kesehatan_no ?? ''));
            $bpjsTk = trim((string) ($benefit->bpjs_ketenagakerjaan_no ?? ''));
            $status = $this->membershipStatus($bpjsKes, $bpjsTk);
            $membershipCounts[$status]++;

            if ($status !== 'complete') {
                $issues = [];
                if ($bpjsKes === '') {
                    $issues[] = ['code' => 'bpjs_kesehatan_missing', 'label' => 'Nomor BPJS Kesehatan belum diisi.'];
                }
                if ($bpjsTk === '') {
                    $issues[] = ['code' => 'bpjs_ketenagakerjaan_missing', 'label' => 'Nomor BPJS Ketenagakerjaan belum diisi.'];
                }

                $nonCompliantEmployees[] = [
                    'userId' => (int) $userId,
                    'userUuid' => $user?->uuid,
                    'fullName' => $user?->name ?? 'Unknown Employee',
                    'email' => $user?->email,
                    'membershipStatus' => $status,
                    'issues' => $issues,
                ];
            }
        }

        $completeMembership = (int) $membershipCounts['complete'];
        $membershipRate = $totalEmployees > 0
            ? round(($completeMembership / $totalEmployees) * 100, 2)
            : 0;

        $missingProgramPairs = $this->requiredProgramPairs();
        $missingProgramPolicies = collect($missingProgramPairs)
            ->filter(function (array $pair) use ($programCoverage): bool {
                return ! (bool) ($programCoverage[$pair['programCode']][$pair['contributionParty']] ?? false);
            })
            ->values();

        $misalignedRatePolicies = $rateAudits
            ->filter(fn (array $item): bool => ($item['result'] ?? '') === 'review_required')
            ->values();

        $checks = [
            [
                'code' => 'bpjs_health_policy',
                'label' => 'Kebijakan BPJS Kesehatan tersedia (pekerja + perusahaan)',
                'pass' => (bool) (($programCoverage['bpjs_kesehatan']['employee'] ?? false) && ($programCoverage['bpjs_kesehatan']['employer'] ?? false)),
                'evidence' => [
                    'employee' => (bool) ($programCoverage['bpjs_kesehatan']['employee'] ?? false),
                    'employer' => (bool) ($programCoverage['bpjs_kesehatan']['employer'] ?? false),
                ],
            ],
            [
                'code' => 'bpjs_program_pair_coverage',
                'label' => 'Seluruh pasangan program iuran wajib memiliki kebijakan aktif',
                'pass' => $missingProgramPolicies->isEmpty(),
                'evidence' => [
                    'requiredPairs' => $missingProgramPairs,
                    'missingPairs' => $missingProgramPolicies,
                ],
            ],
            [
                'code' => 'bpjs_rate_and_basis_alignment',
                'label' => 'Rate, basis upah, dan legal basis kebijakan sesuai baseline regulasi',
                'pass' => $misalignedRatePolicies->isEmpty() && $rateAudits->isNotEmpty(),
                'evidence' => [
                    'auditedPolicies' => $rateAudits->count(),
                    'reviewRequiredPolicies' => $misalignedRatePolicies,
                ],
            ],
            [
                'code' => 'membership_coverage',
                'label' => 'Data membership BPJS karyawan aktif lengkap',
                'pass' => $totalEmployees > 0 && $completeMembership === $totalEmployees,
                'evidence' => [
                    'totalEmployees' => $totalEmployees,
                    'complete' => $membershipCounts['complete'],
                    'partial' => $membershipCounts['partial'],
                    'missing' => $membershipCounts['missing'],
                    'completionRate' => $membershipRate,
                    'nonCompliantCount' => count($nonCompliantEmployees),
                    'nonCompliantEmployees' => array_slice($nonCompliantEmployees, 0, 100),
                ],
            ],
        ];

        $passed = collect($checks)->where('pass', true)->count();
        $score = count($checks) > 0 ? (int) round(($passed / count($checks)) * 100) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'reportingPeriod' => now()->year.'-Q'.now()->quarter,
                'generatedAt' => now()->toIso8601String(),
                'policyActiveCount' => $policyRows->count(),
                'employeeMembership' => [
                    'totalEmployees' => $totalEmployees,
                    'complete' => $completeMembership,
                    'partial' => (int) $membershipCounts['partial'],
                    'missing' => (int) $membershipCounts['missing'],
                    'completionRate' => $membershipRate,
                ],
                'programCoverage' => $programCoverage,
                'rateAudit' => [
                    'items' => $rateAudits,
                    'reviewRequiredCount' => $misalignedRatePolicies->count(),
                ],
                'checks' => $checks,
                'score' => $score,
            ],
        ]);
    }

    public function exportReports(Request $request): Response
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            abort(403);
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            abort(400, 'Active company context is required.');
        }

        $asOf = now()->toDateString();
        $policyRows = HcmBpjsGovernancePolicy::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereDate('effective_start_date', '<=', $asOf)
            ->where(function ($builder) use ($asOf): void {
                $builder->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $asOf);
            })
            ->get();

        $programCoverage = collect($this->programCodes())->mapWithKeys(fn (string $program) => [$program => [
            'employee' => $policyRows->first(fn (HcmBpjsGovernancePolicy $row): bool => $row->program_code === $program && $row->contribution_party === 'employee') !== null,
            'employer' => $policyRows->first(fn (HcmBpjsGovernancePolicy $row): bool => $row->program_code === $program && $row->contribution_party === 'employer') !== null,
        ]]);

        $rateAudits = $policyRows->map(function (HcmBpjsGovernancePolicy $policy) use ($companyId): array {
            $baseline = $this->regulatoryBaselineFor($policy->program_code, $policy->contribution_party, $companyId);
            if ($baseline === null) {
                return ['policyId' => (int) $policy->id, 'uuid' => $policy->uuid, 'programCode' => $policy->program_code, 'contributionParty' => $policy->contribution_party, 'result' => 'unknown_baseline'];
            }
            $actualRate = (float) $policy->rate_percent;
            $ratePass = $actualRate >= (float) $baseline['minRate'] && $actualRate <= (float) $baseline['maxRate'];
            $wageBasePass = $baseline['wageBase'] === null || (string) ($policy->wage_base ?? '') === (string) $baseline['wageBase'];
            $legalBasisPass = trim((string) ($policy->legal_basis ?? '')) !== '';

            return [
                'policyId' => (int) $policy->id, 'uuid' => $policy->uuid,
                'programCode' => $policy->program_code, 'contributionParty' => $policy->contribution_party,
                'ratePercent' => $actualRate, 'expectedRateMin' => (float) $baseline['minRate'], 'expectedRateMax' => (float) $baseline['maxRate'],
                'ratePass' => $ratePass, 'wageBasePass' => $wageBasePass, 'legalBasisPass' => $legalBasisPass,
                'result' => ($ratePass && $wageBasePass && $legalBasisPass) ? 'pass' : 'review_required',
            ];
        })->values();

        $companyUserIds = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('role', '!=', 'owner')
            ->pluck('user_id');
        $activeUsers = User::query()->whereIn('id', $companyUserIds)->get(['id', 'uuid', 'name', 'email'])->keyBy('id');
        $profiles = EmployeeProfile::query()->whereIn('user_id', $companyUserIds)->get(['id', 'user_id']);
        $profileByUserId = $profiles->keyBy('user_id');
        $latestMembership = EmployeeBenefit::query()
            ->whereIn('employee_id', $profiles->pluck('id'))
            ->orderByDesc('effective_date')->orderByDesc('id')
            ->get()->groupBy('employee_id')->map(fn ($items) => $items->first());
        $totalEmployees = $companyUserIds->count();
        $membershipCounts = ['complete' => 0, 'partial' => 0, 'missing' => 0];
        $nonCompliantEmployees = [];
        foreach ($companyUserIds as $userId) {
            $user = $activeUsers->get((int) $userId);
            $profile = $profileByUserId->get($userId);
            if (! $profile) {
                $membershipCounts['missing']++;
                $nonCompliantEmployees[] = [
                    'userId' => (int) $userId,
                    'userUuid' => $user?->uuid,
                    'fullName' => $user?->name ?? 'Unknown Employee',
                    'email' => $user?->email,
                    'membershipStatus' => 'missing',
                    'issues' => [
                        ['code' => 'employee_profile_missing', 'label' => 'Profil karyawan belum tersedia untuk evaluasi membership BPJS.'],
                    ],
                ];

                continue;
            }
            $benefit = $latestMembership->get((int) $profile->id);
            if (! $benefit) {
                $membershipCounts['missing']++;
                $nonCompliantEmployees[] = [
                    'userId' => (int) $userId,
                    'userUuid' => $user?->uuid,
                    'fullName' => $user?->name ?? 'Unknown Employee',
                    'email' => $user?->email,
                    'membershipStatus' => 'missing',
                    'issues' => [
                        ['code' => 'bpjs_membership_missing', 'label' => 'Nomor BPJS Kesehatan dan BPJS Ketenagakerjaan belum diisi.'],
                    ],
                ];

                continue;
            }
            $bpjsKes = trim((string) ($benefit->bpjs_kesehatan_no ?? ''));
            $bpjsTk = trim((string) ($benefit->bpjs_ketenagakerjaan_no ?? ''));
            $status = $this->membershipStatus($bpjsKes, $bpjsTk);
            $membershipCounts[$status]++;
            if ($status !== 'complete') {
                $issues = [];
                if ($bpjsKes === '') {
                    $issues[] = ['code' => 'bpjs_kesehatan_missing', 'label' => 'Nomor BPJS Kesehatan belum diisi.'];
                }
                if ($bpjsTk === '') {
                    $issues[] = ['code' => 'bpjs_ketenagakerjaan_missing', 'label' => 'Nomor BPJS Ketenagakerjaan belum diisi.'];
                }
                $nonCompliantEmployees[] = [
                    'userId' => (int) $userId,
                    'userUuid' => $user?->uuid,
                    'fullName' => $user?->name ?? 'Unknown Employee',
                    'email' => $user?->email,
                    'membershipStatus' => $status,
                    'issues' => $issues,
                ];
            }
        }
        $completeMembership = (int) $membershipCounts['complete'];
        $membershipRate = $totalEmployees > 0 ? round(($completeMembership / $totalEmployees) * 100, 2) : 0;

        $missingProgramPolicies = collect($this->requiredProgramPairs())
            ->filter(fn (array $pair): bool => ! (bool) ($programCoverage[$pair['programCode']][$pair['contributionParty']] ?? false))
            ->values();
        $misalignedRatePolicies = $rateAudits->filter(fn (array $item): bool => ($item['result'] ?? '') === 'review_required')->values();
        $checks = [
            ['code' => 'bpjs_health_policy', 'label' => 'Kebijakan BPJS Kesehatan (pekerja + perusahaan)', 'pass' => (bool) (($programCoverage['bpjs_kesehatan']['employee'] ?? false) && ($programCoverage['bpjs_kesehatan']['employer'] ?? false))],
            ['code' => 'bpjs_program_pair_coverage', 'label' => 'Seluruh pasangan program iuran wajib aktif', 'pass' => $missingProgramPolicies->isEmpty()],
            ['code' => 'bpjs_rate_and_basis_alignment', 'label' => 'Rate dan legal basis sesuai baseline regulasi', 'pass' => $misalignedRatePolicies->isEmpty() && $rateAudits->isNotEmpty()],
            [
                'code' => 'membership_coverage',
                'label' => 'Membership BPJS karyawan aktif lengkap',
                'pass' => $totalEmployees > 0 && $completeMembership === $totalEmployees,
                'evidence' => [
                    'totalEmployees' => $totalEmployees,
                    'complete' => $membershipCounts['complete'],
                    'partial' => $membershipCounts['partial'],
                    'missing' => $membershipCounts['missing'],
                    'completionRate' => $membershipRate,
                    'nonCompliantCount' => count($nonCompliantEmployees),
                    'nonCompliantEmployees' => array_slice($nonCompliantEmployees, 0, 100),
                ],
            ],
        ];
        $passed = collect($checks)->where('pass', true)->count();
        $score = count($checks) > 0 ? (int) round(($passed / count($checks)) * 100) : 0;

        $payload = json_encode([
            'success' => true,
            'data' => [
                'reportingPeriod' => now()->year.'-Q'.now()->quarter,
                'generatedAt' => now()->toIso8601String(),
                'policyActiveCount' => $policyRows->count(),
                'employeeMembership' => ['totalEmployees' => $totalEmployees, 'complete' => $completeMembership, 'partial' => (int) $membershipCounts['partial'], 'missing' => (int) $membershipCounts['missing'], 'completionRate' => $membershipRate],
                'programCoverage' => $programCoverage,
                'rateAudit' => ['items' => $rateAudits, 'reviewRequiredCount' => $misalignedRatePolicies->count()],
                'checks' => $checks,
                'score' => $score,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $filename = 'bpjs-compliance-report-'.now()->format('Y-m-d').'.json';

        return response((string) $payload, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function rateBaselines(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->error('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $tenantConfigs = HcmBpjsGovernanceRateBaseline::query()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy(fn ($row) => $row->program_code.'_'.$row->contribution_party);

        $systemDefaults = $this->systemRegulatoryMatrix();

        $items = [];
        foreach ($systemDefaults as $programCode => $parties) {
            foreach ($parties as $contributionParty => $defaults) {
                $key = $programCode.'_'.$contributionParty;
                $tenantConfig = $tenantConfigs->get($key);
                $items[] = [
                    'programCode' => $programCode,
                    'contributionParty' => $contributionParty,
                    'minRate' => $tenantConfig ? (float) $tenantConfig->min_rate : (float) $defaults['minRate'],
                    'maxRate' => $tenantConfig ? (float) $tenantConfig->max_rate : (float) $defaults['maxRate'],
                    'wageBase' => $tenantConfig ? $tenantConfig->wage_base : $defaults['wageBase'],
                    'riskCategory' => $tenantConfig?->risk_category,
                    'jpSalaryCap' => $tenantConfig?->jp_salary_cap,
                    'bpjsKesSalaryCap' => $tenantConfig?->bpjs_kes_salary_cap,
                    'notes' => $tenantConfig?->notes,
                    'source' => $tenantConfig ? 'tenant' : 'system_default',
                    'updatedAt' => $tenantConfig ? optional($tenantConfig->updated_at)->toIso8601String() : null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => ['items' => $items],
        ]);
    }

    public function updateRateBaseline(Request $request, string $programCode, string $contributionParty): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->error('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        if (! in_array($programCode, $this->programCodes(), true)) {
            return $this->error('INVALID_PROGRAM_CODE', 'Program code not recognized.', 422);
        }
        if (! in_array($contributionParty, $this->contributionParties(), true)) {
            return $this->error('INVALID_CONTRIBUTION_PARTY', 'Contribution party not recognized.', 422);
        }

        $validated = $request->validate([
            'minRate' => ['required', 'numeric', 'min:0', 'max:100'],
            'maxRate' => ['required', 'numeric', 'min:0', 'max:100', 'gte:minRate'],
            // Backward-compatible: payload boleh mengirim wageBase, namun nilai
            // tetap dipaksa mengikuti matrix regulasi saat disimpan.
            'wageBase' => ['sometimes', 'nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // JKK: risk category 1–5 (hanya relevan untuk jkk/employer)
            'riskCategory' => ['nullable', 'integer', 'min:1', 'max:5'],
            // JP salary cap (Rupiah, opsional — null berarti pakai default sistem)
            'jpSalaryCap' => ['nullable', 'integer', 'min:0'],
            // BPJS Kesehatan salary cap (Rupiah, opsional)
            'bpjsKesSalaryCap' => ['nullable', 'integer', 'min:0'],
        ]);

        // Wage base ditentukan oleh regulasi sistem — ambil dari existing record atau dari matrix regulasi
        $systemMatrix = $this->systemRegulatoryMatrix();
        $regulatoryWageBase = $systemMatrix[$programCode][$contributionParty]['wageBase'] ?? null;

        $user = $request->user();
        $baseline = HcmBpjsGovernanceRateBaseline::query()->updateOrCreate(
            ['company_id' => $companyId, 'program_code' => $programCode, 'contribution_party' => $contributionParty],
            [
                'min_rate' => number_format((float) $validated['minRate'], 4, '.', ''),
                'max_rate' => number_format((float) $validated['maxRate'], 4, '.', ''),
                'wage_base' => $regulatoryWageBase,
                'notes' => $validated['notes'] ?? null,
                'risk_category' => isset($validated['riskCategory']) ? (int) $validated['riskCategory'] : null,
                'jp_salary_cap' => isset($validated['jpSalaryCap']) ? (int) $validated['jpSalaryCap'] : null,
                'bpjs_kes_salary_cap' => isset($validated['bpjsKesSalaryCap']) ? (int) $validated['bpjsKesSalaryCap'] : null,
                'updated_by_user_id' => $user?->id,
                'updated_by_user_uuid' => $user?->uuid,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'programCode' => $programCode,
                'contributionParty' => $contributionParty,
                'minRate' => (float) $baseline->min_rate,
                'maxRate' => (float) $baseline->max_rate,
                'wageBase' => $baseline->wage_base,
                'riskCategory' => $baseline->risk_category,
                'jpSalaryCap' => $baseline->jp_salary_cap,
                'bpjsKesSalaryCap' => $baseline->bpjs_kes_salary_cap,
                'notes' => $baseline->notes,
                'source' => 'tenant',
                'updatedAt' => optional($baseline->updated_at)->toIso8601String(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function policyPayload(HcmBpjsGovernancePolicy $policy): array
    {
        return [
            'id' => (int) $policy->id,
            'uuid' => $policy->uuid,
            'programCode' => $policy->program_code,
            'contributionParty' => $policy->contribution_party,
            'ratePercent' => (string) $policy->rate_percent,
            'wageBase' => $policy->wage_base,
            'effectiveStartDate' => optional($policy->effective_start_date)->toDateString(),
            'effectiveEndDate' => optional($policy->effective_end_date)->toDateString(),
            'legalBasis' => $policy->legal_basis,
            'notes' => $policy->notes,
            'isActive' => (bool) $policy->is_active,
            'createdAt' => optional($policy->created_at)->toIso8601String(),
            'updatedAt' => optional($policy->updated_at)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePolicyPayload(Request $request, bool $isCreate = true): array
    {
        $required = $isCreate ? ['required'] : ['sometimes'];

        return $request->validate([
            'programCode' => array_merge($required, ['string', Rule::in($this->programCodes())]),
            'contributionParty' => array_merge($required, ['string', Rule::in($this->contributionParties())]),
            'ratePercent' => array_merge($required, ['numeric', 'min:0', 'max:100']),
            'wageBase' => ['sometimes', 'nullable', 'string', Rule::in($this->wageBases())],
            'effectiveStartDate' => array_merge($required, ['date']),
            'effectiveEndDate' => ['sometimes', 'nullable', 'date', 'after_or_equal:effectiveStartDate'],
            'legalBasis' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'isActive' => ['sometimes', 'boolean'],
        ]);
    }

    private function validatePolicyUpdatePayload(Request $request): array
    {
        return $request->validate([
            'programCode' => ['prohibited'],
            'contributionParty' => ['prohibited'],
            'wageBase' => ['prohibited'],
            'effectiveStartDate' => ['prohibited'],
            'effectiveEndDate' => ['prohibited'],
            'ratePercent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'legalBasis' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'isActive' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function enforcePolicyBusinessRules(array $validated, int $companyId, ?HcmBpjsGovernancePolicy $existingPolicy): void
    {
        $programCode = (string) ($validated['programCode'] ?? $existingPolicy?->program_code ?? '');
        $contributionParty = (string) ($validated['contributionParty'] ?? $existingPolicy?->contribution_party ?? '');
        $ratePercent = (float) ($validated['ratePercent'] ?? $existingPolicy?->rate_percent ?? 0);
        $wageBase = array_key_exists('wageBase', $validated)
            ? ($validated['wageBase'] ?? null)
            : $existingPolicy?->wage_base;
        $legalBasis = array_key_exists('legalBasis', $validated)
            ? ($validated['legalBasis'] ?? null)
            : $existingPolicy?->legal_basis;
        $effectiveStartDate = Carbon::parse((string) ($validated['effectiveStartDate'] ?? $existingPolicy?->effective_start_date ?? now()->toDateString()))
            ->toDateString();
        $effectiveEndDate = array_key_exists('effectiveEndDate', $validated)
            ? ($validated['effectiveEndDate'] ?? null)
            : $existingPolicy?->effective_end_date;
        $isActive = array_key_exists('isActive', $validated)
            ? (bool) $validated['isActive']
            : (bool) ($existingPolicy?->is_active ?? true);

        $errors = [];
        $baseline = $this->regulatoryBaselineFor($programCode, $contributionParty, $companyId);
        if ($baseline !== null) {
            if ($ratePercent < (float) $baseline['minRate'] || $ratePercent > (float) $baseline['maxRate']) {
                $errors['ratePercent'][] = sprintf(
                    'Rate percent for %s (%s) must be between %.4f and %.4f.',
                    $programCode,
                    $contributionParty,
                    (float) $baseline['minRate'],
                    (float) $baseline['maxRate']
                );
            }

            if ($baseline['wageBase'] !== null && (string) $wageBase !== (string) $baseline['wageBase']) {
                $errors['wageBase'][] = sprintf(
                    'Wage base for %s (%s) must be %s.',
                    $programCode,
                    $contributionParty,
                    (string) $baseline['wageBase']
                );
            }
        }

        if ($isActive && trim((string) ($legalBasis ?? '')) === '') {
            $errors['legalBasis'][] = 'Active policy must include legalBasis for compliance audit traceability.';
        }

        if ($effectiveEndDate !== null) {
            $resolvedEndDate = Carbon::parse((string) $effectiveEndDate)->toDateString();
            if ($resolvedEndDate < $effectiveStartDate) {
                $errors['effectiveEndDate'][] = 'effectiveEndDate must be after or equal to effectiveStartDate.';
            }
        }

        if ($isActive && $programCode !== '' && $contributionParty !== '') {
            $overlapQuery = HcmBpjsGovernancePolicy::query()
                ->where('company_id', $companyId)
                ->where('program_code', $programCode)
                ->where('contribution_party', $contributionParty)
                ->where('is_active', true);

            if ($existingPolicy !== null) {
                $overlapQuery->where('id', '!=', $existingPolicy->id);
            }

            $candidatePolicies = $overlapQuery->get();
            foreach ($candidatePolicies as $candidate) {
                $candidateStart = optional($candidate->effective_start_date)->toDateString();
                $candidateEnd = optional($candidate->effective_end_date)->toDateString();
                if ($candidateStart === null) {
                    continue;
                }

                if ($this->dateRangeOverlaps(
                    $effectiveStartDate,
                    $effectiveEndDate !== null ? Carbon::parse((string) $effectiveEndDate)->toDateString() : null,
                    $candidateStart,
                    $candidateEnd
                )) {
                    $errors['effectiveStartDate'][] = 'Active policy period overlaps with existing policy for the same program and contribution party.';
                    break;
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array{minRate: float, maxRate: float, wageBase: string|null}|null
     */
    private function regulatoryBaselineFor(string $programCode, string $contributionParty, ?int $companyId = null): ?array
    {
        // Check tenant-configured override first
        if ($companyId !== null) {
            $config = HcmBpjsGovernanceRateBaseline::query()
                ->where('company_id', $companyId)
                ->where('program_code', $programCode)
                ->where('contribution_party', $contributionParty)
                ->first();
            if ($config) {
                return [
                    'minRate' => (float) $config->min_rate,
                    'maxRate' => (float) $config->max_rate,
                    'wageBase' => $config->wage_base,
                ];
            }
        }

        return $this->systemRegulatoryMatrix()[$programCode][$contributionParty] ?? null;
    }

    /**
     * @return array<string, array<string, array{minRate: float, maxRate: float, wageBase: string|null}>>
     */
    private function systemRegulatoryMatrix(): array
    {
        return [
            'bpjs_kesehatan' => [
                'employee' => ['minRate' => 1.0, 'maxRate' => 1.0, 'wageBase' => 'wage_bpjs_health'],
                'employer' => ['minRate' => 4.0, 'maxRate' => 4.0, 'wageBase' => 'wage_bpjs_health'],
            ],
            'jht' => [
                'employee' => ['minRate' => 2.0, 'maxRate' => 2.0, 'wageBase' => 'wage_bpjs_tk'],
                'employer' => ['minRate' => 3.7, 'maxRate' => 3.7, 'wageBase' => 'wage_bpjs_tk'],
            ],
            'jp' => [
                'employee' => ['minRate' => 1.0, 'maxRate' => 1.0, 'wageBase' => 'wage_bpjs_tk'],
                'employer' => ['minRate' => 2.0, 'maxRate' => 2.0, 'wageBase' => 'wage_bpjs_tk'],
            ],
            'jkk' => [
                'employee' => ['minRate' => 0.0, 'maxRate' => 0.0, 'wageBase' => 'wage_bpjs_tk'],
                'employer' => ['minRate' => 0.24, 'maxRate' => 1.74, 'wageBase' => 'wage_bpjs_tk'],
            ],
            'jkm' => [
                'employee' => ['minRate' => 0.0, 'maxRate' => 0.0, 'wageBase' => 'wage_bpjs_tk'],
                'employer' => ['minRate' => 0.3, 'maxRate' => 0.3, 'wageBase' => 'wage_bpjs_tk'],
            ],
        ];
    }

    /**
     * @return list<array{programCode: string, contributionParty: string}>
     */
    private function requiredProgramPairs(): array
    {
        return [
            ['programCode' => 'bpjs_kesehatan', 'contributionParty' => 'employee'],
            ['programCode' => 'bpjs_kesehatan', 'contributionParty' => 'employer'],
            ['programCode' => 'jht', 'contributionParty' => 'employee'],
            ['programCode' => 'jht', 'contributionParty' => 'employer'],
            ['programCode' => 'jp', 'contributionParty' => 'employee'],
            ['programCode' => 'jp', 'contributionParty' => 'employer'],
            ['programCode' => 'jkk', 'contributionParty' => 'employer'],
            ['programCode' => 'jkm', 'contributionParty' => 'employer'],
        ];
    }

    private function dateRangeOverlaps(string $startA, ?string $endA, string $startB, ?string $endB): bool
    {
        $resolvedEndA = $endA ?? '9999-12-31';
        $resolvedEndB = $endB ?? '9999-12-31';

        return $startA <= $resolvedEndB && $startB <= $resolvedEndA;
    }

    /**
     * @return list<string>
     */
    private function programCodes(): array
    {
        return ['bpjs_kesehatan', 'jht', 'jp', 'jkk', 'jkm'];
    }

    /**
     * @return list<string>
     */
    private function contributionParties(): array
    {
        return ['employee', 'employer'];
    }

    /**
     * @return list<string>
     */
    private function wageBases(): array
    {
        return ['wage_bpjs_health', 'wage_bpjs_tk', 'fixed_nominal'];
    }

    private function membershipStatus(string $bpjsKes, string $bpjsTk): string
    {
        $hasKes = trim($bpjsKes) !== '';
        $hasTk = trim($bpjsTk) !== '';

        if ($hasKes && $hasTk) {
            return 'complete';
        }

        if ($hasKes || $hasTk) {
            return 'partial';
        }

        return 'missing';
    }

    private function appendPolicyHistory(HcmBpjsGovernancePolicy $policy, string $actionType, $actor = null): void
    {
        HcmBpjsGovernancePolicyHistory::query()->create([
            'company_id' => (int) $policy->company_id,
            'policy_id' => (int) $policy->id,
            'policy_uuid' => $policy->uuid,
            'action_type' => $actionType,
            'snapshot' => $this->policyPayload($policy),
            'changed_by_user_id' => $actor?->id,
            'changed_by_user_uuid' => $actor?->uuid,
        ]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }

    /**
     * Daftarkan semua komponen gaji BPJS secara idempoten ke registri komponen.
     * Dipanggil saat kebijakan BPJS pertama kali dibuat.
     */
    private function ensureBpjsSalaryComponents(int $companyId): void
    {
        $components = [
            // Komponen beban perusahaan (addition — info slip)
            ['iuran_bpjs_kes_pk',  'Iuran BPJS Kesehatan pihak perusahaan (informasi slip)', 'addition',  'employer_cost_display', ['employer_cost_line' => true,  'affects_net_pay' => false, 'include_bpjs_health_wage_base' => true]],
            ['iuran_jht_pk',       'Iuran JHT pihak perusahaan (informasi slip)',             'addition',  'employer_cost_display', ['employer_cost_line' => true,  'affects_net_pay' => false, 'include_bpjs_tk_wage_base' => true]],
            ['iuran_jp_pk',        'Iuran JP pihak perusahaan (informasi slip)',               'addition',  'employer_cost_display', ['employer_cost_line' => true,  'affects_net_pay' => false, 'include_bpjs_tk_wage_base' => true]],
            ['premi_jkk_pk',       'Premi JKK (beban perusahaan — informasi slip)',            'addition',  'employer_cost_display', ['employer_cost_line' => true,  'affects_net_pay' => false, 'include_bpjs_tk_wage_base' => true]],
            ['premi_jkm_pk',       'Premi JKM (beban perusahaan — informasi slip)',            'addition',  'employer_cost_display', ['employer_cost_line' => true,  'affects_net_pay' => false, 'include_bpjs_tk_wage_base' => true]],
            // Potongan karyawan (deduction)
            ['iuran_bpjs_kes_pekerja', 'Iuran BPJS Kesehatan (peserta pekerja)',             'deduction', 'bpjs_health_employee',  ['include_bpjs_health_wage_base' => true]],
            ['iuran_jht_pekerja',      'Iuran JHT (pekerja)',                                 'deduction', 'bpjs_jht_employee',     ['include_bpjs_tk_wage_base' => true]],
            ['iuran_jp_pekerja',       'Iuran JP / Jaminan Pensiun (pekerja)',                'deduction', 'bpjs_jp_employee',      ['include_bpjs_tk_wage_base' => true]],
        ];

        foreach ($components as [$code, $name, $kind, $category, $extra]) {
            HcmSalaryComponent::ensureComponent($companyId, $code, $name, $kind, $category, HcmSalaryComponent::SOURCE_MODULE_BPJS, $extra);
        }
    }
}
