<?php

namespace App\Http\Controllers\Api\Payroll;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\HcmEmployeePayrollItemAssignment;
use App\Models\HcmPayrollItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
            'userId' => ['required', 'uuid', 'exists:users,uuid'],
            'kind' => ['nullable', 'string', Rule::in(['addition', 'deduction'])],
            'isActive' => ['nullable', 'boolean'],
        ]);
        $userId = $this->resolveUserIdFromUuid((string) $validated['userId']);

        $companyId = $this->activeCompanyId($request);

        $query = HcmEmployeePayrollItemAssignment::query()
            ->with(['payrollItem.salaryComponent'])
            ->where('user_id', $userId)
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
        if ($forbidden = $this->ensurePermission($request, 'payroll.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);

        $validated = $request->validate([
            'userId' => ['required', 'uuid', 'exists:users,uuid'],
            'payrollItemId' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'isActive' => ['nullable', 'boolean'],
            'effectiveStartDate' => ['nullable', 'date'],
            'effectiveEndDate' => ['nullable', 'date', 'after_or_equal:effectiveStartDate'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $userId = $this->resolveUserIdFromUuid((string) $validated['userId']);

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
            ->where('user_id', $userId)
            ->where('hcm_payroll_item_id', (int) $item->id)
            ->where('is_active', true);
        $this->applyTenantScope($existsQuery, $companyId);
        $this->applyEffectiveRangeOverlap(
            $existsQuery,
            $validated['effectiveStartDate'] ?? null,
            $validated['effectiveEndDate'] ?? null,
        );
        if ($existsQuery->exists()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PAYROLL_ITEM_ASSIGNMENT_OVERLAP',
                    'message' => 'Payroll item ini sudah memiliki assignment aktif yang overlap dengan rentang efektif baru. Tutup assignment lama (set effectiveEndDate) sebelum menambah yang baru.',
                ],
            ], 422);
        }

        $assignment = HcmEmployeePayrollItemAssignment::query()->create([
            'company_id' => $companyId,
            'user_id' => $userId,
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

    public function update(Request $request, string $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);

        $assignmentQuery = HcmEmployeePayrollItemAssignment::query()->with(['payrollItem.salaryComponent']);
        $this->applyIdentifierScope(
            $assignmentQuery,
            $id,
            Schema::hasColumn((new HcmEmployeePayrollItemAssignment)->getTable(), 'uuid')
        );
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

        // M7 — Overlap guard: when the mutation touches the effective range or
        // re-activates the assignment, make sure the new range does not overlap
        // any *other* active assignment for the same user + payroll item.
        $willBeActive = array_key_exists('is_active', $payload)
            ? (bool) $payload['is_active']
            : (bool) $assignment->is_active;
        $touchesRange = array_key_exists('effective_start_date', $payload)
            || array_key_exists('effective_end_date', $payload)
            || array_key_exists('is_active', $payload);
        if ($willBeActive && $touchesRange) {
            $newStart = array_key_exists('effective_start_date', $payload)
                ? $payload['effective_start_date']
                : optional($assignment->effective_start_date)?->toDateString();
            $newEnd = array_key_exists('effective_end_date', $payload)
                ? $payload['effective_end_date']
                : optional($assignment->effective_end_date)?->toDateString();

            $overlapQuery = HcmEmployeePayrollItemAssignment::query()
                ->where('user_id', $assignment->user_id)
                ->where('hcm_payroll_item_id', (int) $assignment->hcm_payroll_item_id)
                ->where('is_active', true)
                ->where('id', '!=', $assignment->id);
            $this->applyTenantScope($overlapQuery, $companyId);
            $this->applyEffectiveRangeOverlap($overlapQuery, $newStart, $newEnd);

            if ($overlapQuery->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'PAYROLL_ITEM_ASSIGNMENT_OVERLAP',
                        'message' => 'Rentang efektif baru overlap dengan assignment aktif lain untuk payroll item yang sama.',
                    ],
                ], 422);
            }
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

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);

        $assignmentQuery = HcmEmployeePayrollItemAssignment::query();
        $this->applyIdentifierScope(
            $assignmentQuery,
            $id,
            Schema::hasColumn((new HcmEmployeePayrollItemAssignment)->getTable(), 'uuid')
        );
        $this->applyTenantScope($assignmentQuery, $companyId);
        $assignment = $assignmentQuery->firstOrFail();

        $assignment->delete();

        return response()->json([
            'success' => true,
            'data' => ['id' => $assignment->id],
        ]);
    }

    /**
     * M7 — Narrow a builder to rows whose [effective_start_date, effective_end_date]
     * overlaps [$newStart, $newEnd]. NULL ends are treated as open-ended (+∞) and
     * NULL starts as -∞.
     */
    private function applyEffectiveRangeOverlap(Builder $query, ?string $newStart, ?string $newEnd): Builder
    {
        if ($newStart !== null) {
            $query->where(function ($q) use ($newStart): void {
                $q->whereNull('effective_end_date')->orWhere('effective_end_date', '>=', $newStart);
            });
        }
        if ($newEnd !== null) {
            $query->where(function ($q) use ($newEnd): void {
                $q->whereNull('effective_start_date')->orWhere('effective_start_date', '<=', $newEnd);
            });
        }

        return $query;
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

    private function resolveUserIdFromUuid(string $uuid): int
    {
        $user = User::query()->where('uuid', $uuid)->firstOrFail();

        return (int) $user->id;
    }

    private function applyIdentifierScope(Builder $query, string $identifier, bool $hasUuidColumn): Builder
    {
        if ($hasUuidColumn && Str::isUuid($identifier)) {
            return $query->where('uuid', $identifier);
        }

        if (ctype_digit($identifier)) {
            return $query->whereKey((int) $identifier);
        }

        return $query->whereRaw('1 = 0');
    }
}
