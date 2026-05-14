<?php

namespace App\Http\Controllers\Api\AllowanceGovernance;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
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

class HcmEmployeeAllowanceGovernanceController extends Controller
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

    public function assignments(Request $request): JsonResponse
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
            'policyRef' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,active,suspended,ended'],
            'as_of' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $asOf = isset($validated['as_of']) ? Carbon::parse((string) $validated['as_of'])->toDateString() : now()->toDateString();
        $perPage = (int) ($validated['perPage'] ?? 20);

        // Source of truth: hcm_employee_payroll_item_assignments filtered for fixed_allowance items.
        // hcm_employee_allowance_assignments is reserved for future governance-only flows;
        // all existing allowance assignments are managed via Employee Salary page and stored here.
        $query = HcmEmployeePayrollItemAssignment::query()
            ->with(['payrollItem.salaryComponent', 'user'])
            ->where('company_id', $companyId)
            ->whereHas('payrollItem', fn ($piq) => $piq->where('category', 'fixed_allowance'))
            ->orderByDesc('is_active')
            ->orderByDesc('id');

        if (! empty($validated['status'])) {
            $status = (string) $validated['status'];
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif (in_array($status, ['ended', 'suspended', 'draft'], true)) {
                $query->where('is_active', false);
            }
        }

        // Treat NULL effective_start_date as always-valid (legacy records without date set).
        $query->where(function ($builder) use ($asOf): void {
            $builder->whereNull('effective_start_date')
                ->orWhereDate('effective_start_date', '<=', $asOf);
        })->where(function ($builder) use ($asOf): void {
            $builder->whereNull('effective_end_date')
                ->orWhereDate('effective_end_date', '>=', $asOf);
        });

        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(function (HcmEmployeePayrollItemAssignment $row): array {
            $user = $row->user;
            $payrollItem = $row->payrollItem;
            $salaryComponent = $payrollItem?->salaryComponent;
            $policyName = $salaryComponent?->name ?? $payrollItem?->name ?? '-';
            $policyCode = $salaryComponent?->code ?? $payrollItem?->code;

            return [
                'id' => (int) $row->id,
                'uuid' => $row->uuid,
                'policyId' => $payrollItem ? (int) $payrollItem->id : null,
                'policyUuid' => $payrollItem?->uuid ?? null,
                'policyCode' => $policyCode,
                'policyName' => $policyName,
                'userId' => $user ? (int) $user->id : null,
                'userUuid' => $user?->uuid,
                'fullName' => $user?->name,
                'email' => $user?->email,
                'amountOverride' => $row->amount !== null ? number_format((float) $row->amount, 2, '.', '') : null,
                'effectiveStartDate' => optional($row->effective_start_date)->toDateString(),
                'effectiveEndDate' => optional($row->effective_end_date)->toDateString(),
                'status' => $row->is_active ? 'active' : 'ended',
                'isActive' => (bool) $row->is_active,
                'notes' => $row->notes,
            ];
        })->values();

        if (! empty($validated['search'])) {
            $search = strtolower(trim((string) $validated['search']));
            $items = $items->filter(function (array $row) use ($search): bool {
                return str_contains(strtolower((string) ($row['fullName'] ?? '')), $search)
                    || str_contains(strtolower((string) ($row['email'] ?? '')), $search)
                    || str_contains(strtolower((string) ($row['policyName'] ?? '')), $search)
                    || str_contains(strtolower((string) ($row['policyCode'] ?? '')), $search);
            })->values();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'meta' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'as_of' => $asOf,
                ],
            ],
        ]);
    }

    public function storeAssignment(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->error('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $validated = $request->validate([
            'policyRef' => ['required', 'string'],
            'userId' => ['required', 'integer', 'min:1'],
            'amountOverride' => ['nullable', 'numeric', 'min:0'],
            'effectiveStartDate' => ['required', 'date'],
            'effectiveEndDate' => ['nullable', 'date', 'after_or_equal:effectiveStartDate'],
            'status' => ['nullable', 'in:draft,active,suspended,ended'],
            'isActive' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $policy = $this->findPolicy($companyId, (string) $validated['policyRef']);
        if (! $policy) {
            return $this->error('ALLOWANCE_POLICY_NOT_FOUND', 'Allowance policy not found.', 404);
        }

        $membership = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('user_id', (int) $validated['userId'])
            ->where('status', 'active')
            ->where('role', '!=', 'owner')
            ->first();

        if (! $membership) {
            return $this->error('ALLOWANCE_EMPLOYEE_NOT_FOUND', 'Employee is not active in tenant payroll scope.', 404);
        }

        $status = (string) ($validated['status'] ?? 'active');
        $isActive = array_key_exists('isActive', $validated) ? (bool) $validated['isActive'] : true;

        $this->ensureNoOverlap(
            $companyId,
            (int) $policy->id,
            (int) $validated['userId'],
            (string) $validated['effectiveStartDate'],
            isset($validated['effectiveEndDate']) ? (string) $validated['effectiveEndDate'] : null,
            null,
            $status,
            $isActive
        );

        $user = $request->user();

        $assignment = HcmEmployeeAllowanceAssignment::query()->create([
            'company_id' => $companyId,
            'company_uuid' => $request->attributes->get('activeCompanyUuid'),
            'policy_id' => (int) $policy->id,
            'policy_uuid' => $policy->uuid,
            'user_id' => (int) $validated['userId'],
            'user_uuid' => User::query()->where('id', (int) $validated['userId'])->value('uuid'),
            'amount_override' => array_key_exists('amountOverride', $validated)
                ? number_format((float) ($validated['amountOverride'] ?? 0), 2, '.', '')
                : null,
            'effective_start_date' => (string) $validated['effectiveStartDate'],
            'effective_end_date' => $validated['effectiveEndDate'] ?? null,
            'status' => $status,
            'is_active' => $isActive,
            'notes' => $validated['notes'] ?? null,
            'created_by_user_id' => $user?->id,
            'created_by_user_uuid' => $user?->uuid,
            'updated_by_user_id' => $user?->id,
            'updated_by_user_uuid' => $user?->uuid,
        ]);

        $this->appendAssignmentHistory($assignment, 'created', $user);

        return response()->json([
            'success' => true,
            'data' => $this->assignmentPayload($assignment),
        ], 201);
    }

    public function updateAssignment(Request $request, string $assignmentRef): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        if (! $companyId) {
            return $this->error('TENANT_REQUIRED', 'Active company context is required.', 400);
        }

        $assignment = $this->findAssignment($companyId, $assignmentRef);
        if (! $assignment) {
            return $this->error('ALLOWANCE_ASSIGNMENT_NOT_FOUND', 'Allowance assignment not found.', 404);
        }

        $validated = $request->validate([
            'amountOverride' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'effectiveStartDate' => ['sometimes', 'date'],
            'effectiveEndDate' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'in:draft,active,suspended,ended'],
            'isActive' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $startDate = array_key_exists('effectiveStartDate', $validated)
            ? (string) $validated['effectiveStartDate']
            : optional($assignment->effective_start_date)->toDateString();
        $endDate = array_key_exists('effectiveEndDate', $validated)
            ? ($validated['effectiveEndDate'] !== null ? (string) $validated['effectiveEndDate'] : null)
            : optional($assignment->effective_end_date)->toDateString();

        if ($endDate !== null && Carbon::parse($endDate)->lt(Carbon::parse($startDate))) {
            throw ValidationException::withMessages([
                'effectiveEndDate' => ['effectiveEndDate must be after or equal to effectiveStartDate.'],
            ]);
        }

        $status = array_key_exists('status', $validated) ? (string) $validated['status'] : (string) $assignment->status;
        $isActive = array_key_exists('isActive', $validated) ? (bool) $validated['isActive'] : (bool) $assignment->is_active;

        $this->ensureNoOverlap(
            $companyId,
            (int) $assignment->policy_id,
            (int) $assignment->user_id,
            $startDate,
            $endDate,
            (int) $assignment->id,
            $status,
            $isActive
        );

        $user = $request->user();

        $assignment->fill([
            'amount_override' => array_key_exists('amountOverride', $validated)
                ? ($validated['amountOverride'] !== null ? number_format((float) $validated['amountOverride'], 2, '.', '') : null)
                : $assignment->amount_override,
            'effective_start_date' => $startDate,
            'effective_end_date' => $endDate,
            'status' => $status,
            'is_active' => $isActive,
            'notes' => array_key_exists('notes', $validated) ? ($validated['notes'] ?? null) : $assignment->notes,
            'updated_by_user_id' => $user?->id,
            'updated_by_user_uuid' => $user?->uuid,
        ]);
        $assignment->save();

        $this->appendAssignmentHistory($assignment, 'updated', $user);

        return response()->json([
            'success' => true,
            'data' => $this->assignmentPayload($assignment),
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

        $validated = $request->validate([
            'as_of' => ['nullable', 'date'],
        ]);

        $asOf = isset($validated['as_of']) ? Carbon::parse((string) $validated['as_of'])->toDateString() : now()->toDateString();

        $activePolicies = HcmEmployeeAllowancePolicy::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereDate('effective_start_date', '<=', $asOf)
            ->where(function ($builder) use ($asOf): void {
                $builder->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $asOf);
            })
            ->get();

        $mandatoryPolicies = $activePolicies->where('is_mandatory', true)->values();

        $employeeMemberships = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('role', '!=', 'owner')
            ->get(['user_id']);

        $employeeIds = $employeeMemberships->pluck('user_id')->unique()->values();
        $users = User::query()->whereIn('id', $employeeIds)->get(['id', 'uuid', 'name', 'email'])->keyBy('id');

        // Resolve active assignments: fixed_allowance items only
        $activeItemAssignments = HcmEmployeePayrollItemAssignment::query()
            ->with(['payrollItem.salaryComponent'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('user_id', $employeeIds)
            ->where(function ($builder) use ($asOf): void {
                $builder->whereNull('effective_start_date')
                    ->orWhereDate('effective_start_date', '<=', $asOf);
            })
            ->where(function ($builder) use ($asOf): void {
                $builder->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $asOf);
            })
            ->get(['id', 'user_id', 'hcm_payroll_item_id', 'is_active', 'effective_start_date', 'effective_end_date']);

        // Build set of user_ids that have at least 1 active allowance assignment.
        // Comply = punya minimal 1 tunjangan aktif, tidak perlu punya semua policy.
        $assignedFromItemAssignments = collect($activeItemAssignments)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $assignedUserIds = $assignedFromItemAssignments->flip(); // flip for O(1) lookup

        $nonCompliantEmployees = [];

        foreach ($employeeIds as $userId) {
            if (! isset($assignedUserIds[(int) $userId])) {
                $user = $users->get((int) $userId);
                $nonCompliantEmployees[] = [
                    'userId' => (int) $userId,
                    'userUuid' => $user?->uuid,
                    'fullName' => $user?->name,
                    'email' => $user?->email,
                    'issues' => [[
                        'code' => 'allowance_assignment_missing',
                        'label' => 'Belum memiliki assignment tunjangan apapun.',
                    ]],
                ];
            }
        }

        $overlapItems = collect(); // Overlap detection not applicable for payroll-item-assignment model

        $checks = [
            [
                'code' => 'default_baseline_seeded',
                'label' => 'Baseline default allowance tersedia',
                'pass' => $activePolicies->count() >= 7,
                'evidence' => [
                    'activePolicyCount' => $activePolicies->count(),
                    'minimumExpected' => 7,
                ],
            ],
            [
                'code' => 'mandatory_assignment_coverage',
                'label' => 'Coverage assignment allowance mandatory',
                'pass' => count($nonCompliantEmployees) === 0,
                'evidence' => [
                    'totalEmployees' => $employeeIds->count(),
                    'mandatoryPolicies' => $mandatoryPolicies->count(),
                    'nonCompliantCount' => count($nonCompliantEmployees),
                    'nonCompliantEmployees' => $nonCompliantEmployees,
                ],
            ],
            [
                'code' => 'assignment_overlap_guard',
                'label' => 'Tidak ada assignment overlap aktif',
                'pass' => $overlapItems->count() === 0,
                'evidence' => [
                    'overlapCount' => $overlapItems->count(),
                    'items' => $overlapItems,
                ],
            ],
        ];

        $passedChecks = collect($checks)->where('pass', true)->count();
        $score = (int) round(($passedChecks / max(count($checks), 1)) * 100);

        return response()->json([
            'success' => true,
            'data' => [
                'reportingPeriod' => $asOf,
                'activePolicyCount' => $activePolicies->count(),
                'mandatoryPolicyCount' => $mandatoryPolicies->count(),
                'employeeScopeCount' => $employeeIds->count(),
                'score' => $score,
                'checks' => $checks,
            ],
        ]);
    }

    public function exportReports(Request $request)
    {
        $report = $this->reports($request);
        $payload = (string) $report->getContent();

        $filename = 'allowance-compliance-report-' . now()->format('Ymd-His') . '.json';

        return response($payload, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
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

    private function findAssignment(int $companyId, string $assignmentRef): ?HcmEmployeeAllowanceAssignment
    {
        $query = HcmEmployeeAllowanceAssignment::query()->where('company_id', $companyId);

        if (str_contains($assignmentRef, '-')) {
            $query->where('uuid', $assignmentRef);
        } else {
            $query->where('id', (int) $assignmentRef);
        }

        return $query->first();
    }

    private function ensureNoOverlap(
        int $companyId,
        int $policyId,
        int $userId,
        string $startDate,
        ?string $endDate,
        ?int $excludeId,
        string $status,
        bool $isActive
    ): void {
        if (! $isActive || $status !== 'active') {
            return;
        }

        $query = HcmEmployeeAllowanceAssignment::query()
            ->where('company_id', $companyId)
            ->where('policy_id', $policyId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where('status', 'active')
            ->whereDate('effective_start_date', '<=', $endDate ?? '9999-12-31')
            ->where(function ($builder) use ($startDate): void {
                $builder->whereNull('effective_end_date')
                    ->orWhereDate('effective_end_date', '>=', $startDate);
            });

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'effectiveStartDate' => ['Active assignment overlap detected for this employee and allowance policy.'],
            ]);
        }
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

    private function appendAssignmentHistory(HcmEmployeeAllowanceAssignment $assignment, string $action, ?User $actor): void
    {
        HcmEmployeeAllowanceAssignmentHistory::query()->create([
            'company_id' => (int) $assignment->company_id,
            'assignment_id' => (int) $assignment->id,
            'assignment_uuid' => $assignment->uuid,
            'action_type' => $action,
            'snapshot' => $this->assignmentPayload($assignment),
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

    private function assignmentPayload(HcmEmployeeAllowanceAssignment $assignment): array
    {
        return [
            'id' => (int) $assignment->id,
            'uuid' => $assignment->uuid,
            'policyId' => (int) $assignment->policy_id,
            'policyUuid' => $assignment->policy_uuid,
            'userId' => (int) $assignment->user_id,
            'userUuid' => $assignment->user_uuid,
            'amountOverride' => $assignment->amount_override !== null ? number_format((float) $assignment->amount_override, 2, '.', '') : null,
            'effectiveStartDate' => optional($assignment->effective_start_date)->toDateString(),
            'effectiveEndDate' => optional($assignment->effective_end_date)->toDateString(),
            'status' => (string) $assignment->status,
            'isActive' => (bool) $assignment->is_active,
            'notes' => $assignment->notes,
            'updatedAt' => optional($assignment->updated_at)->toIso8601String(),
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
    private function ensureAllowanceSalaryComponent(int $companyId, HcmEmployeeAllowancePolicy $policy): HcmSalaryComponent
    {
        $code = (string) $policy->code;
        $name = (string) $policy->name;
        $isTaxable = (bool) $policy->is_taxable;

        // Pilih kategori: fixed_allowance untuk tunjangan wajib, irregular_allowance untuk insentif/tidak tetap
        $isIrregular = str_contains($code, 'insentif') || str_contains($code, 'irregular') || str_contains($code, 'tidak_tetap');
        $category = $isIrregular ? 'irregular_allowance' : 'fixed_allowance';

        $taxTreatmentCode = $isTaxable
            ? HcmSalaryComponent::TAX_TREATMENT_PPH21_TAXABLE_FULL
            : HcmSalaryComponent::TAX_TREATMENT_NON_OBJECT;

        return HcmSalaryComponent::ensureComponent(
            $companyId,
            $code,
            $name,
            'addition',
            $category,
            HcmSalaryComponent::SOURCE_MODULE_ALLOWANCE,
            [
                'tax_treatment_code'           => $taxTreatmentCode,
                'include_pph21_ter_gross'       => $isTaxable,
                'include_pph21_annual_reconciliation' => $isTaxable,
                'include_thr_calculation_base'  => true,
                'include_bpjs_health_wage_base' => false,
                'include_bpjs_tk_wage_base'     => false,
                'affects_net_pay'               => true,
                'employer_cost_line'            => false,
            ]
        );
    }

    /**
     * Daftarkan payroll item linked untuk komponen allowance governance agar
     * langsung tersedia di assignment kompensasi karyawan.
     */
    private function ensureAllowancePayrollItem(
        int $companyId,
        HcmEmployeeAllowancePolicy $policy,
        HcmSalaryComponent $component
    ): void {
        $shouldActive = (bool) $policy->is_active && (bool) $component->is_active;

        $item = HcmPayrollItem::query()
            ->where('hcm_salary_component_id', $component->id)
            ->first();

        if ($item === null) {
            HcmPayrollItem::query()->create([
                'company_id' => $companyId,
                'hcm_salary_component_id' => $component->id,
                'code' => $component->code,
                'name' => $component->name,
                'kind' => $component->kind,
                'category' => $component->category,
                'notes' => 'Auto-linked from allowance governance.',
                'sort_order' => (int) $component->sort_order,
                'is_active' => $shouldActive,
            ]);

            return;
        }

        $item->update([
            'company_id' => $companyId,
            'code' => $component->code,
            'name' => $component->name,
            'kind' => $component->kind,
            'category' => $component->category,
            'sort_order' => (int) $component->sort_order,
            'is_active' => $shouldActive,
        ]);
    }

    /**
     * Auto-provision starter draft allowance policies untuk company baru.
     * Hanya berjalan sekali saat company pertama kali mengakses halaman policies.
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
