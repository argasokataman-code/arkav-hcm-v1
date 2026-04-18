<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\HcmEmployeePayrollItemAssignment;
use App\Models\HcmPayrollItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HcmPayrollItemAssignmentController extends Controller
{
    use ChecksPermissions;

    public function index(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'userId' => ['required', 'integer', 'exists:users,id'],
            'kind' => ['nullable', 'string', Rule::in(['addition', 'deduction'])],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $companyId = $this->activeCompanyId($request);

        $query = HcmEmployeePayrollItemAssignment::query()
            ->with(['payrollItem.salaryComponent'])
            ->where('user_id', (int) $validated['userId'])
            ->orderByDesc('is_active')
            ->orderBy('id');
        $this->applyTenantScope($query, $companyId);

        if (! empty($validated['kind'] ?? null)) {
            $query->whereHas('payrollItem', fn (Builder $itemQ) => $itemQ->where('kind', $validated['kind']));
        }
        if (array_key_exists('isActive', $validated)) {
            $query->where('is_active', (bool) $validated['isActive']);
        }

        $rows = $query->get()->map(fn (HcmEmployeePayrollItemAssignment $assignment) => $this->serializeAssignment($assignment));

        return response()->json([
            'success' => true,
            'data' => [
                'assignments' => $rows,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);

        $validated = $request->validate([
            'userId' => ['required', 'integer', 'exists:users,id'],
            'payrollItemId' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'isActive' => ['nullable', 'boolean'],
            'effectiveStartDate' => ['nullable', 'date'],
            'effectiveEndDate' => ['nullable', 'date', 'after_or_equal:effectiveStartDate'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $itemQuery = HcmPayrollItem::query()->whereKey((int) $validated['payrollItemId'])->where('is_active', true);
        $this->applyTenantScope($itemQuery, $companyId);
        $item = $itemQuery->first();
        if (! $item) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYROLL_ITEM_NOT_FOUND',
                    'message' => 'Payroll item tidak ditemukan atau tidak aktif pada tenant ini.',
                ],
            ], 422);
        }

        $existsQuery = HcmEmployeePayrollItemAssignment::query()
            ->where('user_id', (int) $validated['userId'])
            ->where('hcm_payroll_item_id', (int) $item->id);
        $this->applyTenantScope($existsQuery, $companyId);
        if ($existsQuery->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYROLL_ITEM_ASSIGNMENT_EXISTS',
                    'message' => 'Payroll item ini sudah di-assign ke karyawan tersebut. Gunakan edit untuk update nominal.',
                ],
            ], 422);
        }

        $assignment = HcmEmployeePayrollItemAssignment::query()->create([
            'company_id' => $companyId,
            'user_id' => (int) $validated['userId'],
            'hcm_payroll_item_id' => (int) $item->id,
            'amount' => round((float) $validated['amount'], 2),
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'effective_start_date' => $validated['effectiveStartDate'] ?? null,
            'effective_end_date' => $validated['effectiveEndDate'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $assignment->load(['payrollItem.salaryComponent']);

        return response()->json([
            'success' => true,
            'data' => $this->serializeAssignment($assignment),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);

        $assignmentQuery = HcmEmployeePayrollItemAssignment::query()->with(['payrollItem.salaryComponent'])->whereKey($id);
        $this->applyTenantScope($assignmentQuery, $companyId);
        $assignment = $assignmentQuery->firstOrFail();

        $validated = $request->validate([
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'isActive' => ['sometimes', 'boolean'],
            'effectiveStartDate' => ['nullable', 'date'],
            'effectiveEndDate' => ['nullable', 'date', 'after_or_equal:effectiveStartDate'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $payload = [];
        if (array_key_exists('amount', $validated)) {
            $payload['amount'] = round((float) $validated['amount'], 2);
        }
        if (array_key_exists('isActive', $validated)) {
            $payload['is_active'] = (bool) $validated['isActive'];
        }
        if (array_key_exists('effectiveStartDate', $validated)) {
            $payload['effective_start_date'] = $validated['effectiveStartDate'];
        }
        if (array_key_exists('effectiveEndDate', $validated)) {
            $payload['effective_end_date'] = $validated['effectiveEndDate'];
        }
        if (array_key_exists('notes', $validated)) {
            $payload['notes'] = $validated['notes'];
        }

        if ($payload !== []) {
            $assignment->update($payload);
            $assignment->refresh();
        }

        return response()->json([
            'success' => true,
            'data' => $this->serializeAssignment($assignment),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);

        $assignmentQuery = HcmEmployeePayrollItemAssignment::query()->whereKey($id);
        $this->applyTenantScope($assignmentQuery, $companyId);
        $assignment = $assignmentQuery->firstOrFail();

        $assignment->delete();

        return response()->json([
            'success' => true,
            'data' => ['id' => $id],
        ]);
    }

    private function serializeAssignment(HcmEmployeePayrollItemAssignment $assignment): array
    {
        $item = $assignment->payrollItem;
        $component = $item?->salaryComponent;

        return [
            'id' => (int) $assignment->id,
            'userId' => (int) $assignment->user_id,
            'payrollItemId' => (int) $assignment->hcm_payroll_item_id,
            'amount' => round((float) $assignment->amount, 2),
            'isActive' => (bool) $assignment->is_active,
            'effectiveStartDate' => optional($assignment->effective_start_date)?->toDateString(),
            'effectiveEndDate' => optional($assignment->effective_end_date)?->toDateString(),
            'notes' => $assignment->notes,
            'payrollItem' => [
                'id' => (int) ($item?->id ?? 0),
                'code' => (string) ($item?->code ?? ''),
                'name' => (string) ($item?->name ?? ''),
                'kind' => (string) ($item?->kind ?? ''),
                'category' => (string) ($item?->category ?? ''),
                'linkedToMaster' => $item?->hcm_salary_component_id !== null,
                'salaryComponentId' => $item?->hcm_salary_component_id,
                'masterDefaultPercent' => $component?->default_percent !== null ? (float) $component->default_percent : null,
                'masterPercentBasis' => $component?->percent_basis,
            ],
        ];
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    private function applyTenantScope(Builder $query, ?int $companyId): Builder
    {
        return $query->where(function (Builder $inner) use ($companyId): void {
            if ($companyId !== null) {
                $inner->where('company_id', $companyId)->orWhereNull('company_id');

                return;
            }

            $inner->whereNull('company_id');
        });
    }
}
