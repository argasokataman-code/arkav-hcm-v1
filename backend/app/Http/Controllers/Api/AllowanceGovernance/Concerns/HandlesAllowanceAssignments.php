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

trait HandlesAllowanceAssignments
{    public function assignments(Request $request): JsonResponse
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
}
