<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\HcmLeaveTypeSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HcmLeaveTypeController extends Controller
{
    use ChecksPermissions;

    private function payload(HcmLeaveTypeSetting $type): array
    {
        return [
            'id' => $type->id,
            'code' => $type->code,
            'name' => $type->name,
            'isEnabled' => (bool) $type->is_enabled,
            'days' => $type->days !== null ? (float) $type->days : null,
            'carryForward' => (bool) $type->carry_forward,
            'maxCarryDays' => $type->max_carry_days,
            'earnedLeave' => (bool) $type->earned_leave,
            'sortOrder' => (int) $type->sort_order,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'leave.type')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);

        $types = HcmLeaveTypeSetting::query()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $types->map(fn (HcmLeaveTypeSetting $type) => $this->payload($type))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'leave.type')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'name' => ['required', 'string', 'max:150'],
            'days' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'carryForward' => ['sometimes', 'boolean'],
            'maxCarryDays' => ['nullable', 'integer', 'min:0', 'max:366'],
            'earnedLeave' => ['sometimes', 'boolean'],
            'isEnabled' => ['sometimes', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        // Enforce uniqueness per company
        $exists = HcmLeaveTypeSetting::query()
            ->where('company_id', $companyId)
            ->where('code', Str::lower(trim((string) $validated['code'])))
            ->exists();
        if ($exists) {
            return $this->apiError('DUPLICATE_CODE', 'A leave type with this code already exists for this company.', 422);
        }

        $type = HcmLeaveTypeSetting::query()->create([
            'company_id'   => $companyId,
            'code'         => Str::lower(trim((string) $validated['code'])),
            'name'         => trim((string) $validated['name']),
            'is_enabled'   => (bool) ($validated['isEnabled'] ?? true),
            'days'         => array_key_exists('days', $validated) ? $validated['days'] : null,
            'carry_forward'  => (bool) ($validated['carryForward'] ?? false),
            'max_carry_days' => array_key_exists('maxCarryDays', $validated) ? $validated['maxCarryDays'] : null,
            'earned_leave'   => (bool) ($validated['earnedLeave'] ?? false),
            'sort_order'     => (int) ($validated['sortOrder'] ?? ((int) (HcmLeaveTypeSetting::query()->where('company_id', $companyId)->max('sort_order') ?? 0) + 1)),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->payload($type->fresh()),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'leave.type')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        $type = HcmLeaveTypeSetting::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'days' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'carryForward' => ['sometimes', 'boolean'],
            'maxCarryDays' => ['nullable', 'integer', 'min:0', 'max:366'],
            'earnedLeave' => ['sometimes', 'boolean'],
            'isEnabled' => ['sometimes', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        $type->name = trim((string) $validated['name']);
        $type->days = array_key_exists('days', $validated) ? $validated['days'] : null;
        $type->carry_forward = (bool) ($validated['carryForward'] ?? false);
        $type->max_carry_days = array_key_exists('maxCarryDays', $validated) ? $validated['maxCarryDays'] : null;
        $type->earned_leave = (bool) ($validated['earnedLeave'] ?? false);
        $type->is_enabled = (bool) ($validated['isEnabled'] ?? $type->is_enabled);
        if (array_key_exists('sortOrder', $validated) && $validated['sortOrder'] !== null) {
            $type->sort_order = (int) $validated['sortOrder'];
        }
        $type->save();

        return response()->json([
            'success' => true,
            'data' => $this->payload($type->fresh()),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'leave.type')) {
            return $forbidden;
        }

        $companyId = $this->activeCompanyId($request);
        $type = HcmLeaveTypeSetting::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);
        $type->update(['is_enabled' => false]);

        return response()->json([
            'success' => true,
            'data' => $this->payload($type->fresh()),
            'message' => 'Leave type disabled successfully.',
        ]);
    }

    private function apiError(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
