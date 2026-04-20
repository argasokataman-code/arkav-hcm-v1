<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\HcmLeaveCustomPolicy;
use App\Models\HcmLeaveTypeSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HcmLeaveSettingController extends Controller
{
    use ChecksPermissions;

    private const SIMPLE_CODES = ['sick_leave', 'hospitalisation', 'maternity', 'paternity', 'lop'];

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    private function userBelongsToActiveCompany(int $userId, ?int $companyId): bool
    {
        if (! $companyId) {
            return true;
        }

        return DB::table('company_users')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->exists();
    }

    private function resolveUserIdentifier(mixed $identifier): ?User
    {
        if ($identifier === null || $identifier === '') {
            return null;
        }

        $raw = trim((string) $identifier);
        if ($raw === '') {
            return null;
        }

        return User::query()
            ->where(function (Builder $query) use ($raw): void {
                if (ctype_digit($raw)) {
                    $query->where('id', (int) $raw)
                        ->orWhere('uuid', $raw);

                    return;
                }

                $query->where('uuid', $raw);
            })
            ->first();
    }

    private function resolveScopedAssigneeIdsOrFail(Request $request, array $identifiers): array
    {
        $companyId = $this->activeCompanyId($request);
        $resolved = [];

        foreach (array_values($identifiers) as $index => $identifier) {
            $user = $this->resolveUserIdentifier($identifier);
            if (! $user || ! $this->userBelongsToActiveCompany((int) $user->id, $companyId)) {
                throw ValidationException::withMessages([
                    "assigneeUserIds.{$index}" => ['The selected assignee user is invalid for the active company.'],
                ]);
            }

            $resolved[] = (int) $user->id;
        }

        return array_values(array_unique($resolved));
    }

    private function typePayload(HcmLeaveTypeSetting $t): array
    {
        return [
            'code' => $t->code,
            'name' => $t->name,
            'isEnabled' => (bool) $t->is_enabled,
            'days' => $t->days !== null ? (float) $t->days : null,
            'carryForward' => (bool) $t->carry_forward,
            'maxCarryDays' => $t->max_carry_days,
            'earnedLeave' => (bool) $t->earned_leave,
            'settingsMode' => in_array($t->code, self::SIMPLE_CODES, true) ? 'simple' : 'full',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'leave.settings');
        if ($forbidden) {
            return $forbidden;
        }

        $types = HcmLeaveTypeSetting::query()->orderBy('sort_order')->orderBy('id')->get();
        $custom = HcmLeaveCustomPolicy::query()->orderByDesc('id')->get();

        $grouped = [];
        foreach ($custom as $p) {
            $grouped[$p->leave_type_code] ??= [];
            $grouped[$p->leave_type_code][] = [
                'id' => $p->id,
                'leaveTypeCode' => $p->leave_type_code,
                'name' => $p->name,
                'days' => (float) $p->days,
                'assigneeUserIds' => $p->assignee_user_ids ?? [],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'types' => $types->map(fn (HcmLeaveTypeSetting $t) => $this->typePayload($t))->values(),
                'customPoliciesByType' => $grouped,
            ],
        ]);
    }

    public function updateType(Request $request, string $code): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'leave.settings');
        if ($forbidden) {
            return $forbidden;
        }

        $type = HcmLeaveTypeSetting::query()->where('code', $code)->firstOrFail();

        $validated = $request->validate([
            'isEnabled' => ['sometimes', 'boolean'],
            'days' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'carryForward' => ['sometimes', 'boolean'],
            'maxCarryDays' => ['nullable', 'integer', 'min:0', 'max:366'],
            'earnedLeave' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('isEnabled', $validated)) {
            $type->is_enabled = $validated['isEnabled'];
        }
        if (array_key_exists('days', $validated)) {
            $type->days = $validated['days'];
        }
        if (array_key_exists('carryForward', $validated)) {
            $type->carry_forward = $validated['carryForward'];
        }
        if (array_key_exists('maxCarryDays', $validated)) {
            $type->max_carry_days = $validated['maxCarryDays'];
        }
        if (array_key_exists('earnedLeave', $validated)) {
            $type->earned_leave = $validated['earnedLeave'];
        }

        $type->save();

        return response()->json(['success' => true, 'data' => $this->typePayload($type->fresh())]);
    }


    private function ensureLeaveTypeCode(string $baseName): string
    {
        $base = Str::of($baseName)->lower()->slug('_')->toString();
        if ($base === '') {
            $base = 'leave_type';
        }
        $code = $base;
        $i = 2;
        while (HcmLeaveTypeSetting::query()->where('code', $code)->exists()) {
            $code = $base.'_'.$i;
            $i++;
        }

        return $code;
    }
    public function storeCustomPolicy(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'leave.settings');
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'leaveTypeCode' => ['nullable', 'string', 'max:64'],
            'leaveTypeName' => ['nullable', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:200'],
            'days' => ['required', 'numeric', 'min:0.5', 'max:366'],
            'assigneeUserIds' => ['nullable', 'array'],
        ]);
        $validated['assigneeUserIds'] = $this->resolveScopedAssigneeIdsOrFail($request, $validated['assigneeUserIds'] ?? []);

        $leaveTypeCode = $validated['leaveTypeCode'] ?? null;
        $leaveTypeName = isset($validated['leaveTypeName']) ? trim((string) $validated['leaveTypeName']) : '';

        if (! $leaveTypeCode && $leaveTypeName === '') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Leave type is required.',
                ],
            ], 422);
        }

        if ($leaveTypeCode) {
            $type = HcmLeaveTypeSetting::query()->where('code', $leaveTypeCode)->first();
            if (! $type) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Selected leave type not found.',
                    ],
                ], 422);
            }
            $leaveTypeCode = $type->code;
        } else {
            $sort = (int) (HcmLeaveTypeSetting::query()->max('sort_order') ?? 0);
            $newCode = $this->ensureLeaveTypeCode($leaveTypeName);
            HcmLeaveTypeSetting::query()->create([
                'code' => $newCode,
                'name' => $leaveTypeName,
                'is_enabled' => true,
                'days' => null,
                'carry_forward' => false,
                'max_carry_days' => null,
                'earned_leave' => false,
                'sort_order' => $sort + 1,
            ]);
            $leaveTypeCode = $newCode;
            $type = HcmLeaveTypeSetting::query()->where('code', $leaveTypeCode)->first();
        }

        $p = HcmLeaveCustomPolicy::query()->create([
            'leave_type_code' => $leaveTypeCode,
            'leave_type_id' => $type?->leave_type_id,
            'name' => $validated['name'],
            'days' => $validated['days'],
            'assignee_user_ids' => $validated['assigneeUserIds'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $p->id,
                'leaveTypeCode' => $p->leave_type_code,
                'name' => $p->name,
                'days' => (float) $p->days,
                'assigneeUserIds' => $p->assignee_user_ids ?? [],
            ],
        ], 201);
    }

    public function updateCustomPolicy(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'leave.settings');
        if ($forbidden) {
            return $forbidden;
        }

        $p = HcmLeaveCustomPolicy::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'days' => ['sometimes', 'numeric', 'min:0.5', 'max:366'],
            'assigneeUserIds' => ['nullable', 'array'],
        ]);
        if (array_key_exists('assigneeUserIds', $validated)) {
            $validated['assigneeUserIds'] = $this->resolveScopedAssigneeIdsOrFail($request, $validated['assigneeUserIds'] ?? []);
        }

        if (array_key_exists('name', $validated)) {
            $p->name = $validated['name'];
        }
        if (array_key_exists('days', $validated)) {
            $p->days = $validated['days'];
        }
        if (array_key_exists('assigneeUserIds', $validated)) {
            $p->assignee_user_ids = $validated['assigneeUserIds'] ?? [];
        }

        $p->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $p->id,
                'leaveTypeCode' => $p->leave_type_code,
                'name' => $p->name,
                'days' => (float) $p->days,
                'assigneeUserIds' => $p->assignee_user_ids ?? [],
            ],
        ]);
    }

    public function destroyCustomPolicy(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'leave.settings');
        if ($forbidden) {
            return $forbidden;
        }

        HcmLeaveCustomPolicy::query()->whereKey($id)->delete();

        return response()->json(['success' => true]);
    }
}
