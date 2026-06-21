<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\HcmShift;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HcmShiftController extends Controller
{
    use ChecksPermissions;

    public function index(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'schedule.view');
        if ($forbidden) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);

        $query = HcmShift::query();
        $this->applyTenantScope($query, $activeCompanyId);

        $rows = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (HcmShift $s) => $this->serializeShift($s))
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'schedule.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i'],
            'shiftType' => ['nullable', 'string', 'in:morning,afternoon,night,custom'],
            'description' => ['nullable', 'string', 'max:500'],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        if ($validated['endTime'] === $validated['startTime']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'endTime cannot be equal to startTime.',
                ],
            ], 422);
        }

        if (! empty($validated['code'])) {
            $existsQuery = HcmShift::query()->where('code', $validated['code']);
            $this->applyTenantScope($existsQuery, $activeCompanyId);
            if ($existsQuery->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'code already exists.',
                    ],
                ], 422);
            }
        }

        $code = $this->uniqueCode($validated['code'] ?? null, $validated['name'], $activeCompanyId);

        $shift = HcmShift::query()->create([
            'company_id' => $activeCompanyId,
            'code' => $code,
            'name' => $validated['name'],
            'start_time' => $validated['startTime'],
            'end_time' => $validated['endTime'],
            'shift_type' => $validated['shiftType'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $shift->id]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'schedule.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        $shiftQuery = HcmShift::query()->whereKey($id);
        $this->applyTenantScope($shiftQuery, $activeCompanyId);
        $shift = $shiftQuery->first();
        if (! $shift) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SHIFT_NOT_FOUND',
                    'message' => 'Shift not found.',
                ],
            ], 404);
        }

        if (! auth()->user()?->isGlobalHcmAdmin() && $shift->company_id === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'Global shift templates can only be updated by global admin.',
                ],
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_\-]+$/'],
            'startTime' => ['required', 'date_format:H:i'],
            'endTime' => ['required', 'date_format:H:i'],
            'shiftType' => ['nullable', 'string', 'in:morning,afternoon,night,custom'],
            'description' => ['nullable', 'string', 'max:500'],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        if ($validated['endTime'] === $validated['startTime']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'endTime cannot be equal to startTime.',
                ],
            ], 422);
        }

        $code = $validated['code'] ?? $shift->code;
        if ($code !== $shift->code) {
            $existsQuery = HcmShift::query()->where('code', $code)->whereKeyNot($shift->id);
            $this->applyTenantScope($existsQuery, $activeCompanyId);
            if ($existsQuery->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'code already exists.',
                    ],
                ], 422);
            }
        }

        $shift->update([
            'company_id' => $shift->company_id ?: $activeCompanyId,
            'code' => $code,
            'name' => $validated['name'],
            'start_time' => $validated['startTime'],
            'end_time' => $validated['endTime'],
            'shift_type' => $validated['shiftType'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['isActive'] ?? true),
            'sort_order' => (int) ($validated['sortOrder'] ?? $shift->sort_order),
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $forbidden = $this->ensurePermission($request, 'schedule.manage');
        if ($forbidden) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);
        $shiftQuery = HcmShift::query()->whereKey($id);
        $this->applyTenantScope($shiftQuery, $activeCompanyId);
        $shift = $shiftQuery->first();
        if (! $shift) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SHIFT_NOT_FOUND',
                    'message' => 'Shift not found.',
                ],
            ], 404);
        }

        if (! auth()->user()?->isGlobalHcmAdmin() && $shift->company_id === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_FORBIDDEN',
                    'message' => 'Global shift templates can only be deleted by global admin.',
                ],
            ], 403);
        }

        $shift->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeShift(HcmShift $s): array
    {
        $start = $this->formatTimeString($s->start_time);
        $end = $this->formatTimeString($s->end_time);
        $isOvernight = $end <= $start;

        return [
            'id' => $s->id,
            'code' => $s->code,
            'name' => $s->name,
            'startTime' => $start,
            'endTime' => $end,
            'shiftType' => $s->shift_type,
            'isOvernight' => $isOvernight,
            'description' => $s->description ?? '',
            'isActive' => (bool) $s->is_active,
            'sortOrder' => (int) $s->sort_order,
            'slotLabel' => $start.' - '.$end.($isOvernight ? ' (+1d)' : ''),
        ];
    }

    private function formatTimeString(mixed $v): string
    {
        if ($v === null) {
            return '00:00';
        }
        if (is_string($v) && preg_match('/^(\d{2}):(\d{2})/', $v, $m)) {
            return $m[1].':'.$m[2];
        }

        return Carbon::parse((string) $v)->format('H:i');
    }

    private function uniqueCode(?string $requested, string $name, ?int $companyId): string
    {
        $base = $requested ?: Str::slug($name, '_');
        if ($base === '') {
            $base = 'shift';
        }
        $base = Str::limit($base, 60, '');
        $code = $base;
        $i = 0;
        while ($this->applyTenantScope(HcmShift::query()->where('code', $code), $companyId)->exists()) {
            $i++;
            $suffix = '_'.$i;
            $code = Str::limit($base, 64 - strlen($suffix), '').$suffix;
        }

        return $code;
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    private function applyTenantScope(Builder $query, ?int $companyId): Builder
    {
        // Global Super Admin bypasses tenant scoping on shift queries.
        if (auth()->user()?->isGlobalHcmAdmin()) {
            return $query;
        }

        if (! $companyId) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($companyId): void {
            $inner->where('company_id', $companyId)->orWhereNull('company_id');
        });
    }
}
