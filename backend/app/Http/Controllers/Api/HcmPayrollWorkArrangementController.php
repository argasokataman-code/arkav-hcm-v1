<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\HcmEmployeeWorkArrangement;
use App\Models\HcmPayrollWorkProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HcmPayrollWorkArrangementController extends Controller
{
    use ChecksPermissions;

    public function profiles(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        $rows = HcmPayrollWorkProfile::query()
            ->where(function (Builder $query) use ($companyId): void {
                if ($companyId !== null) {
                    $query->where('company_id', $companyId)->orWhereNull('company_id');

                    return;
                }

                $query->whereNull('company_id');
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (HcmPayrollWorkProfile $row) => $this->serializeProfile($row));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function storeProfile(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.manage')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_\-]+$/'],
            'name' => ['required', 'string', 'max:120'],
            'arrangementMode' => ['required', Rule::in(['office_hour', 'shift_worker'])],
            'defaultDayType' => ['required', Rule::in(['workday', 'public_holiday', 'weekly_rest_day', 'weekly_rest_day_short'])],
            'weeklyWorkDays' => ['required', 'integer', Rule::in([5, 6])],
            'isDefault' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
        ]);

        $companyId = $this->activeCompanyId($request);
        if ((bool) ($validated['isDefault'] ?? false)) {
            HcmPayrollWorkProfile::query()
                ->where('company_id', $companyId)
                ->update(['is_default' => false]);
        }

        $row = HcmPayrollWorkProfile::query()->create([
            'company_id' => $companyId,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'arrangement_mode' => $validated['arrangementMode'],
            'default_day_type' => $validated['defaultDayType'],
            'weekly_work_days' => $validated['weeklyWorkDays'],
            'is_default' => (bool) ($validated['isDefault'] ?? false),
            'meta' => $validated['meta'] ?? null,
        ]);

        return response()->json(['success' => true, 'data' => $this->serializeProfile($row)], 201);
    }

    public function updateProfile(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        $row = HcmPayrollWorkProfile::query()
            ->where('id', $id)
            ->where(function (Builder $query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'arrangementMode' => ['sometimes', Rule::in(['office_hour', 'shift_worker'])],
            'defaultDayType' => ['sometimes', Rule::in(['workday', 'public_holiday', 'weekly_rest_day', 'weekly_rest_day_short'])],
            'weeklyWorkDays' => ['sometimes', 'integer', Rule::in([5, 6])],
            'isDefault' => ['sometimes', 'boolean'],
            'meta' => ['nullable', 'array'],
        ]);

        if (array_key_exists('isDefault', $validated) && (bool) $validated['isDefault']) {
            HcmPayrollWorkProfile::query()
                ->where('company_id', $companyId)
                ->whereKeyNot($row->id)
                ->update(['is_default' => false]);
        }

        $row->update([
            'name' => $validated['name'] ?? $row->name,
            'arrangement_mode' => $validated['arrangementMode'] ?? $row->arrangement_mode,
            'default_day_type' => $validated['defaultDayType'] ?? $row->default_day_type,
            'weekly_work_days' => $validated['weeklyWorkDays'] ?? $row->weekly_work_days,
            'is_default' => array_key_exists('isDefault', $validated) ? (bool) $validated['isDefault'] : $row->is_default,
            'meta' => array_key_exists('meta', $validated) ? $validated['meta'] : $row->meta,
        ]);

        return response()->json(['success' => true, 'data' => $this->serializeProfile($row->fresh())]);
    }

    public function arrangements(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.view')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'userId' => ['nullable', 'integer'],
            'effectiveDate' => ['nullable', 'date'],
            'arrangementMode' => ['nullable', Rule::in(['office_hour', 'shift_worker'])],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $companyId = $this->activeCompanyId($request);
        $query = HcmEmployeeWorkArrangement::query()
            ->with(['user:id,name,email', 'profile:id,name,code'])
            ->where(function (Builder $q) use ($companyId): void {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id');

        if (! empty($validated['userId'] ?? null)) {
            $query->where('user_id', (int) $validated['userId']);
        }

        if (! empty($validated['effectiveDate'] ?? null)) {
            $query->whereDate('effective_from', '<=', $validated['effectiveDate'])
                ->where(function (Builder $q) use ($validated): void {
                    $q->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $validated['effectiveDate']);
                });
        }

        if (! empty($validated['arrangementMode'] ?? null)) {
            $query->where('arrangement_mode', $validated['arrangementMode']);
        }

        $perPage = (int) ($validated['perPage'] ?? 20);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($paginator->items())->map(fn (HcmEmployeeWorkArrangement $row) => $this->serializeArrangement($row))->values(),
            'meta' => [
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'totalPages' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function storeArrangement(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.manage')) {
            return $forbidden;
        }

        $validated = $request->validate([
            'userId' => ['required', 'integer', 'exists:users,id'],
            'profileId' => ['nullable', 'integer', 'exists:hcm_payroll_work_profiles,id'],
            'arrangementMode' => ['required', Rule::in(['office_hour', 'shift_worker'])],
            'defaultDayType' => ['nullable', Rule::in(['workday', 'public_holiday', 'weekly_rest_day', 'weekly_rest_day_short'])],
            'weeklyWorkDays' => ['nullable', 'integer', Rule::in([5, 6])],
            'effectiveFrom' => ['required', 'date'],
            'effectiveTo' => ['nullable', 'date', 'after_or_equal:effectiveFrom'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $row = HcmEmployeeWorkArrangement::query()->create([
            'company_id' => $this->activeCompanyId($request),
            'user_id' => (int) $validated['userId'],
            'hcm_payroll_work_profile_id' => $validated['profileId'] ?? null,
            'arrangement_mode' => $validated['arrangementMode'],
            'default_day_type' => $validated['defaultDayType'] ?? null,
            'weekly_work_days' => $validated['weeklyWorkDays'] ?? null,
            'effective_from' => $validated['effectiveFrom'],
            'effective_to' => $validated['effectiveTo'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json(['success' => true, 'data' => $this->serializeArrangement($row->fresh(['user:id,name,email', 'profile:id,name,code']))], 201);
    }

    public function updateArrangement(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'payroll.manage')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        $row = HcmEmployeeWorkArrangement::query()
            ->where('id', $id)
            ->where(function (Builder $query) use ($companyId): void {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->firstOrFail();

        $validated = $request->validate([
            'profileId' => ['sometimes', 'nullable', 'integer', 'exists:hcm_payroll_work_profiles,id'],
            'arrangementMode' => ['sometimes', Rule::in(['office_hour', 'shift_worker'])],
            'defaultDayType' => ['sometimes', 'nullable', Rule::in(['workday', 'public_holiday', 'weekly_rest_day', 'weekly_rest_day_short'])],
            'weeklyWorkDays' => ['sometimes', 'nullable', 'integer', Rule::in([5, 6])],
            'effectiveFrom' => ['sometimes', 'date'],
            'effectiveTo' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $effectiveFrom = $validated['effectiveFrom'] ?? $row->effective_from?->toDateString();
        $effectiveTo = array_key_exists('effectiveTo', $validated)
            ? $validated['effectiveTo']
            : $row->effective_to?->toDateString();

        if ($effectiveTo !== null && $effectiveTo < $effectiveFrom) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'WORK_ARRANGEMENT_DATE_INVALID',
                    'message' => 'effectiveTo must be greater than or equal to effectiveFrom.',
                ],
            ], 422);
        }

        $payload = [
            'hcm_payroll_work_profile_id' => array_key_exists('profileId', $validated) ? $validated['profileId'] : $row->hcm_payroll_work_profile_id,
            'arrangement_mode' => $validated['arrangementMode'] ?? $row->arrangement_mode,
            'default_day_type' => array_key_exists('defaultDayType', $validated) ? $validated['defaultDayType'] : $row->default_day_type,
            'weekly_work_days' => array_key_exists('weeklyWorkDays', $validated) ? $validated['weeklyWorkDays'] : $row->weekly_work_days,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $row->notes,
        ];

        $row->update($payload);

        return response()->json(['success' => true, 'data' => $this->serializeArrangement($row->fresh(['user:id,name,email', 'profile:id,name,code']))]);
    }

    private function serializeProfile(HcmPayrollWorkProfile $row): array
    {
        return [
            'id' => (int) $row->id,
            'code' => (string) $row->code,
            'name' => (string) $row->name,
            'arrangementMode' => (string) $row->arrangement_mode,
            'defaultDayType' => (string) $row->default_day_type,
            'weeklyWorkDays' => (int) $row->weekly_work_days,
            'isDefault' => (bool) $row->is_default,
            'meta' => $row->meta,
            'updatedAt' => optional($row->updated_at)->toIso8601String(),
        ];
    }

    private function serializeArrangement(HcmEmployeeWorkArrangement $row): array
    {
        return [
            'id' => (int) $row->id,
            'userId' => (int) $row->user_id,
            'userName' => (string) ($row->user?->name ?? ''),
            'userEmail' => (string) ($row->user?->email ?? ''),
            'profileId' => $row->hcm_payroll_work_profile_id,
            'profileCode' => $row->profile?->code,
            'profileName' => $row->profile?->name,
            'arrangementMode' => (string) $row->arrangement_mode,
            'defaultDayType' => $row->default_day_type,
            'weeklyWorkDays' => $row->weekly_work_days,
            'effectiveFrom' => $row->effective_from?->toDateString(),
            'effectiveTo' => $row->effective_to?->toDateString(),
            'notes' => $row->notes,
            'updatedAt' => optional($row->updated_at)->toIso8601String(),
        ];
    }
}
