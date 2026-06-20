<?php

namespace App\Http\Controllers\Api\AllowanceGovernance\Concerns;

use App\Models\CompanyUser;
use App\Models\HcmEmployeeAllowanceAssignment;
use App\Models\HcmEmployeeAllowanceAssignmentHistory;
use App\Models\HcmEmployeePayrollItemAssignment;
use App\Models\HcmEmployeeAllowancePolicy;
use App\Models\HcmEmployeeAllowancePolicyHistory;
use App\Models\HcmPayrollItem;
use App\Models\HcmSalaryComponent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use ChecksPermissions;

trait HandlesAllowancePolicies
{    public function reference(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'policyStatuses' => ['draft', 'active', 'superseded', 'archived'],
                'assignmentStatuses' => ['draft', 'active', 'suspended', 'ended'],
                'amountTypes' => ['fixed'],
                'frequencies' => ['monthly'],
            ],
        ]);
    }

    public function policies(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->error('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $this->ensureDefaultAllowancePolicies($companyId, (int) ($request->user()?->id ?? 0) ?: null);

        $validated = $request->validate([
            'active_only' => ['nullable', 'boolean'],
            'as_of' => ['nullable', 'date'],
        ]);

        $asOf = isset($validated['as_of']) ? Carbon::parse((string) $validated['as_of'])->toDateString() : now()->toDateString();

        $query = HcmEmployeeAllowancePolicy::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->orderByDesc('id');

        if ((bool) ($validated['active_only'] ?? false)) {
            $query->where('is_active', true)
                ->whereDate('effective_start_date', '<=', $asOf)
                ->where(function ($builder) use ($asOf): void {
                    $builder->whereNull('effective_end_date')
                        ->orWhereDate('effective_end_date', '>=', $asOf);
                });
        }

        $items = $query->get()->map(fn (HcmEmployeeAllowancePolicy $row) => $this->policyPayload($row))->values();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'meta' => [
                    'total' => $items->count(),
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

        $rows = HcmEmployeeAllowancePolicyHistory::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $actorMap = User::query()
            ->whereIn('id', $rows->pluck('changed_by_user_id')->filter()->unique()->values())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        $items = $rows->map(function (HcmEmployeeAllowancePolicyHistory $row) use ($actorMap): array {
            $snapshot = is_array($row->snapshot) ? $row->snapshot : [];
            $actor = $row->changed_by_user_id ? $actorMap->get((int) $row->changed_by_user_id) : null;

            return [
                'id' => (int) $row->id,
                'actionType' => (string) $row->action_type,
                'policyUuid' => $row->policy_uuid,
                'code' => $snapshot['code'] ?? null,
                'name' => $snapshot['name'] ?? null,
                'status' => $snapshot['status'] ?? null,
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
                    'total' => $items->count(),
                    'limit' => $limit,
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

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'name' => ['required', 'string', 'max:160'],
            'isTaxable' => ['nullable', 'boolean'],
            'isMandatory' => ['nullable', 'boolean'],
            'defaultAmount' => ['nullable', 'numeric', 'min:0'],
            'effectiveStartDate' => ['required', 'date'],
            'effectiveEndDate' => ['nullable', 'date', 'after_or_equal:effectiveStartDate'],
            'status' => ['nullable', 'in:draft,active,superseded,archived'],
            'isActive' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $code = strtolower(trim((string) $validated['code']));
        $effectiveStart = Carbon::parse((string) $validated['effectiveStartDate'])->toDateString();

        $exists = HcmEmployeeAllowancePolicy::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->whereDate('effective_start_date', $effectiveStart)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => ['Policy code for the same effectiveStartDate already exists.'],
            ]);
        }

        $user = $request->user();

        $policy = HcmEmployeeAllowancePolicy::query()->create([
            'company_id' => $companyId,
            'company_uuid' => $request->attributes->get('activeCompanyUuid'),
            'code' => $code,
            'name' => trim((string) $validated['name']),
            'allowance_type' => 'fixed',
            'is_taxable' => array_key_exists('isTaxable', $validated) ? (bool) $validated['isTaxable'] : true,
            'is_mandatory' => array_key_exists('isMandatory', $validated) ? (bool) $validated['isMandatory'] : true,
            'default_amount' => number_format((float) ($validated['defaultAmount'] ?? 0), 2, '.', ''),
            'frequency' => 'monthly',
            'effective_start_date' => $effectiveStart,
            'effective_end_date' => $validated['effectiveEndDate'] ?? null,
            'status' => (string) ($validated['status'] ?? 'active'),
            'is_active' => array_key_exists('isActive', $validated) ? (bool) $validated['isActive'] : true,
            'notes' => $validated['notes'] ?? null,
            'created_by_user_id' => $user?->id,
            'created_by_user_uuid' => $user?->uuid,
            'updated_by_user_id' => $user?->id,
            'updated_by_user_uuid' => $user?->uuid,
        ]);

        $this->appendPolicyHistory($policy, 'created', $user);

        $component = $this->ensureAllowanceSalaryComponent($companyId, $policy);
        $this->ensureAllowancePayrollItem($companyId, $policy, $component);

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

        $policy = $this->findPolicy($companyId, $policyRef);
        if (! $policy) {
            return $this->error('ALLOWANCE_POLICY_NOT_FOUND', 'Allowance policy not found.', 404);
        }

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'name' => ['sometimes', 'string', 'max:160'],
            'isTaxable' => ['sometimes', 'boolean'],
            'isMandatory' => ['sometimes', 'boolean'],
            'defaultAmount' => ['sometimes', 'numeric', 'min:0'],
            'effectiveStartDate' => ['sometimes', 'date'],
            'effectiveEndDate' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'in:draft,active,superseded,archived'],
            'isActive' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        if (
            array_key_exists('effectiveStartDate', $validated)
            && array_key_exists('effectiveEndDate', $validated)
            && $validated['effectiveEndDate'] !== null
        ) {
            $start = Carbon::parse((string) $validated['effectiveStartDate'])->startOfDay();
            $end = Carbon::parse((string) $validated['effectiveEndDate'])->startOfDay();
            if ($end->lt($start)) {
                throw ValidationException::withMessages([
                    'effectiveEndDate' => ['effectiveEndDate must be after or equal to effectiveStartDate.'],
                ]);
            }
        }

        $newCode = array_key_exists('code', $validated) ? strtolower(trim((string) $validated['code'])) : (string) $policy->code;
        $newStart = array_key_exists('effectiveStartDate', $validated)
            ? Carbon::parse((string) $validated['effectiveStartDate'])->toDateString()
            : optional($policy->effective_start_date)->toDateString();

        $exists = HcmEmployeeAllowancePolicy::query()
            ->where('company_id', $companyId)
            ->where('code', $newCode)
            ->whereDate('effective_start_date', $newStart)
            ->where('id', '!=', $policy->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => ['Policy code for the same effectiveStartDate already exists.'],
            ]);
        }

        $user = $request->user();

        $policy->fill([
            'code' => $newCode,
            'name' => array_key_exists('name', $validated) ? trim((string) $validated['name']) : $policy->name,
            'is_taxable' => array_key_exists('isTaxable', $validated) ? (bool) $validated['isTaxable'] : $policy->is_taxable,
            'is_mandatory' => array_key_exists('isMandatory', $validated) ? (bool) $validated['isMandatory'] : $policy->is_mandatory,
            'default_amount' => array_key_exists('defaultAmount', $validated)
                ? number_format((float) $validated['defaultAmount'], 2, '.', '')
                : $policy->default_amount,
            'effective_start_date' => $newStart,
            'effective_end_date' => array_key_exists('effectiveEndDate', $validated) ? ($validated['effectiveEndDate'] ?? null) : $policy->effective_end_date,
            'status' => array_key_exists('status', $validated) ? (string) $validated['status'] : $policy->status,
            'is_active' => array_key_exists('isActive', $validated) ? (bool) $validated['isActive'] : $policy->is_active,
            'notes' => array_key_exists('notes', $validated) ? ($validated['notes'] ?? null) : $policy->notes,
            'updated_by_user_id' => $user?->id,
            'updated_by_user_uuid' => $user?->uuid,
        ]);
        $policy->save();

        $this->appendPolicyHistory($policy, 'updated', $user);

        $component = $this->ensureAllowanceSalaryComponent($companyId, $policy);
        $this->ensureAllowancePayrollItem($companyId, $policy, $component);

        return response()->json([
            'success' => true,
            'data' => $this->policyPayload($policy),
        ]);
    }

    public function activatePolicy(Request $request, string $policyRef): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->error('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $policy = $this->findPolicy($companyId, $policyRef);
        if (! $policy) {
            return $this->error('ALLOWANCE_POLICY_NOT_FOUND', 'Allowance policy not found.', 404);
        }

        $validated = $request->validate([
            'effectiveStartDate' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $startDate = isset($validated['effectiveStartDate'])
            ? Carbon::parse((string) $validated['effectiveStartDate'])->toDateString()
            : optional($policy->effective_start_date)->toDateString();

        HcmEmployeeAllowancePolicy::query()
            ->where('company_id', $companyId)
            ->where('code', $policy->code)
            ->where('id', '!=', $policy->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'status' => 'superseded',
            ]);

        $user = $request->user();

        $policy->fill([
            'effective_start_date' => $startDate,
            'is_active' => true,
            'status' => 'active',
            'notes' => $validated['notes'] ?? $policy->notes,
            'updated_by_user_id' => $user?->id,
            'updated_by_user_uuid' => $user?->uuid,
        ]);
        $policy->save();

        $this->appendPolicyHistory($policy, 'activated', $user);

        $component = $this->ensureAllowanceSalaryComponent($companyId, $policy);
        $this->ensureAllowancePayrollItem($companyId, $policy, $component);

        return response()->json([
            'success' => true,
            'data' => $this->policyPayload($policy),
        ]);
    }

    private function findPolicy(int $companyId, string $policyRef): ?HcmEmployeeAllowancePolicy
    {
        $query = HcmEmployeeAllowancePolicy::query()->where('company_id', $companyId);

        if (str_contains($policyRef, '-')) {
            $query->where('uuid', $policyRef);
        } else {
            $query->where('id', (int) $policyRef);
        }

        return $query->first();
    }

    private function appendPolicyHistory(HcmEmployeeAllowancePolicy $policy, string $action, ?User $actor): void
    {
        HcmEmployeeAllowancePolicyHistory::query()->create([
            'company_id' => (int) $policy->company_id,
            'policy_id' => (int) $policy->id,
            'policy_uuid' => $policy->uuid,
            'action_type' => $action,
            'snapshot' => $this->policyPayload($policy),
            'changed_by_user_id' => $actor?->id,
            'changed_by_user_uuid' => $actor?->uuid,
        ]);
    }

    private function policyPayload(HcmEmployeeAllowancePolicy $policy): array
    {
        return [
            'id' => (int) $policy->id,
            'uuid' => $policy->uuid,
            'code' => (string) $policy->code,
            'name' => (string) $policy->name,
            'allowanceType' => (string) $policy->allowance_type,
            'isTaxable' => (bool) $policy->is_taxable,
            'isMandatory' => (bool) $policy->is_mandatory,
            'defaultAmount' => number_format((float) ($policy->default_amount ?? 0), 2, '.', ''),
            'frequency' => (string) $policy->frequency,
            'effectiveStartDate' => optional($policy->effective_start_date)->toDateString(),
            'effectiveEndDate' => optional($policy->effective_end_date)->toDateString(),
            'status' => (string) $policy->status,
            'isActive' => (bool) $policy->is_active,
            'notes' => $policy->notes,
            'updatedAt' => optional($policy->updated_at)->toIso8601String(),
        ];
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
     * Daftarkan komponen gaji untuk kebijakan tunjangan secara idempoten.
     * Kode komponen = kode kebijakan, kategori bergantung pada is_taxable dan tipe nama.
     */
    private function ensureDefaultAllowancePolicies(int $companyId, ?int $actorUserId): void
    {
        $hasAny = HcmEmployeeAllowancePolicy::query()
            ->where('company_id', $companyId)
            ->exists();

        if ($hasAny) {
            return;
        }

        $defaults = [
            [
                'code' => 'allowance_transport',
                'name' => 'Tunjangan Transport',
                'allowance_type' => 'fixed',
                'is_taxable' => true,
                'is_mandatory' => false,
                'default_amount' => 0,
            ],
            [
                'code' => 'allowance_meal',
                'name' => 'Tunjangan Makan',
                'allowance_type' => 'fixed',
                'is_taxable' => true,
                'is_mandatory' => false,
                'default_amount' => 0,
            ],
            [
                'code' => 'allowance_communication',
                'name' => 'Tunjangan Komunikasi (Pulsa/Internet)',
                'allowance_type' => 'fixed',
                'is_taxable' => true,
                'is_mandatory' => false,
                'default_amount' => 0,
            ],
            [
                'code' => 'allowance_position',
                'name' => 'Tunjangan Jabatan',
                'allowance_type' => 'fixed',
                'is_taxable' => true,
                'is_mandatory' => false,
                'default_amount' => 0,
            ],
            [
                'code' => 'allowance_attendance',
                'name' => 'Tunjangan Kehadiran',
                'allowance_type' => 'fixed',
                'is_taxable' => true,
                'is_mandatory' => false,
                'default_amount' => 0,
            ],
        ];

        DB::transaction(function () use ($companyId, $actorUserId, $defaults): void {
            $startDate = now()->startOfMonth()->toDateString();
            foreach ($defaults as $def) {
                HcmEmployeeAllowancePolicy::query()->create([
                    'company_id' => $companyId,
                    'code' => $def['code'],
                    'name' => $def['name'],
                    'allowance_type' => $def['allowance_type'],
                    'is_taxable' => $def['is_taxable'],
                    'is_mandatory' => $def['is_mandatory'],
                    'default_amount' => $def['default_amount'],
                    'frequency' => 'monthly',
                    'effective_start_date' => $startDate,
                    'effective_end_date' => null,
                    'status' => 'draft',
                    'is_active' => false,
                    'notes' => 'Starter policy — diisi nominal dan aktifkan sesuai kebijakan perusahaan.',
                    'created_by_user_id' => $actorUserId,
                ]);
            }
        });
    }
}
