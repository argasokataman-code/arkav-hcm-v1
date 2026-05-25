<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Models\AttendanceRecord;
use App\Models\HcmScheduleTiming;
use App\Models\HcmShift;
use App\Models\User;
use App\Support\Exports\TabularExportResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceScheduleController extends BaseAttendanceController
{
    public function scheduleTimingIndex(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'search'     => ['nullable', 'string', 'max:100'],
            'sort'       => ['nullable', 'string', 'in:name_asc,name_desc,start_asc,start_desc'],
            'page'       => ['nullable', 'integer', 'min:1'],
            'perPage'    => ['nullable', 'integer', 'min:1', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
        ]);

        $searchRaw        = trim((string) ($validated['search'] ?? ''));
        $sort             = $validated['sort'] ?? 'name_asc';
        $page             = max(1, (int) ($validated['page'] ?? 1));
        $perPage          = min(100, (int) ($validated['perPage'] ?? 50));
        $departmentFilter = trim((string) ($validated['department'] ?? ''));
        $tz               = $this->tz();
        $since            = Carbon::now($tz)->subDays(30)->toDateString();
        $activeCompanyId  = $this->activeCompanyId($request);

        $usersQuery = User::query()->with([
            'employeeProfile:id,user_id,designation,department_id',
            'employeeProfile.department:id,name',
        ]);

        if ($activeCompanyId) {
            $usersQuery->whereHas('employeeProfile', function ($query) use ($activeCompanyId): void {
                $query->where(function ($inner) use ($activeCompanyId): void {
                    $inner->where('company_id', $activeCompanyId)->orWhereNull('company_id');
                });
            });
        }

        if ($searchRaw !== '') {
            $usersQuery->where(function ($q) use ($searchRaw) {
                $q->where('name', 'like', '%' . $searchRaw . '%')
                    ->orWhereHas('employeeProfile', fn ($p) => $p->where('designation', 'like', '%' . $searchRaw . '%'));
            });
        }

        if ($departmentFilter !== '') {
            $usersQuery->whereHas('employeeProfile.department', function ($q) use ($departmentFilter): void {
                $q->where('name', $departmentFilter);
            });
        }

        if ($sort === 'name_desc') {
            $usersQuery->orderByDesc('name')->orderBy('id');
        } else {
            $usersQuery->orderBy('name')->orderBy('id');
        }

        $buildRows = function ($users) use ($activeCompanyId, $since, $tz) {
            $users = collect($users)->values();
            if ($users->isEmpty()) {
                return collect();
            }

            $recordsQuery = AttendanceRecord::query();
            $this->applyTenantScope($recordsQuery, $activeCompanyId);
            $records = $recordsQuery
                ->whereDate('work_date', '>=', $since)
                ->whereIn('user_id', $users->pluck('id'))
                ->whereNotNull('check_in_at')
                ->whereNotNull('check_out_at')
                ->get()
                ->groupBy('user_id');

            $overridesQuery = HcmScheduleTiming::query();
            $this->applyTenantScope($overridesQuery, $activeCompanyId);
            $overrides = $overridesQuery
                ->with(['shift:id,name'])
                ->whereIn('user_id', $users->pluck('id'))
                ->get()
                ->keyBy('user_id');

            return $users->map(function (User $u) use ($records, $tz, $overrides) {
                $recs         = $records->get($u->id) ?? collect();
                $startMinutes = [];
                $endMinutes   = [];

                foreach ($recs as $rec) {
                    $ci = $rec->check_in_at ? $rec->check_in_at->copy()->timezone($tz) : null;
                    $co = $rec->check_out_at ? $rec->check_out_at->copy()->timezone($tz) : null;
                    if ($ci && $co) {
                        $startMinutes[] = ((int) $ci->format('H')) * 60 + ((int) $ci->format('i'));
                        $endMinutes[]   = ((int) $co->format('H')) * 60 + ((int) $co->format('i'));
                    }
                }

                $defaultStart = 9 * 60;
                $defaultEnd   = 18 * 60;
                $avgStart     = count($startMinutes)
                    ? (int) round(array_sum($startMinutes) / count($startMinutes))
                    : $defaultStart;
                $avgEnd       = count($endMinutes)
                    ? (int) round(array_sum($endMinutes) / count($endMinutes))
                    : $defaultEnd;

                $source = 'auto';
                if ($overrides->has($u->id)) {
                    $ov       = $overrides->get($u->id);
                    $avgStart = ((int) substr((string) $ov->start_time, 0, 2)) * 60 + ((int) substr((string) $ov->start_time, 3, 2));
                    $avgEnd   = ((int) substr((string) $ov->end_time, 0, 2)) * 60 + ((int) substr((string) $ov->end_time, 3, 2));
                    $source   = (string) ($ov->source ?: 'manual');
                }

                $slot        = sprintf('%02d:%02d - %02d:%02d', intdiv($avgStart, 60), $avgStart % 60, intdiv($avgEnd, 60), $avgEnd % 60);
                $designation = (string) ($u->employeeProfile?->designation ?: 'Employee');
                $department  = (string) ($u->employeeProfile?->department?->name ?: '');
                $ov          = $overrides->get($u->id);

                return [
                    'userId'           => $u->id,
                    'name'             => (string) $u->name,
                    'department'       => $department,
                    'jobTitle'         => $designation,
                    'availableTimings' => $slot,
                    'startMinutes'     => $avgStart,
                    'endMinutes'       => $avgEnd,
                    'source'           => $source,
                    'shiftId'          => $ov?->hcm_shift_id,
                    'shiftName'        => $ov?->shift?->name,
                ];
            })->values();
        };

        if ($sort === 'start_asc' || $sort === 'start_desc') {
            $allUsers    = $usersQuery->get();
            $rows        = $buildRows($allUsers);
            $rows        = $sort === 'start_asc'
                ? $rows->sortBy('startMinutes')->values()
                : $rows->sortByDesc('startMinutes')->values();

            $total      = $rows->count();
            $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
            $safePage   = min($page, $totalPages);
            $pagedRows  = $rows->forPage($safePage, $perPage)->values();

            return response()->json([
                'success' => true,
                'data'    => $pagedRows->all(),
                'meta'    => [
                    'pagination' => [
                        'page'       => $safePage,
                        'perPage'    => $perPage,
                        'total'      => $total,
                        'totalPages' => $totalPages,
                    ],
                ],
            ]);
        }

        $paginator = $usersQuery->paginate($perPage, ['*'], 'page', $page);
        $rows      = $buildRows($paginator->items());

        return response()->json([
            'success' => true,
            'data'    => $rows->all(),
            'meta'    => [
                'pagination' => [
                    'page'       => $paginator->currentPage(),
                    'perPage'    => $paginator->perPage(),
                    'total'      => $paginator->total(),
                    'totalPages' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function scheduleTimingExport(Request $request): StreamedResponse|JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'search'     => ['nullable', 'string', 'max:100'],
            'sort'       => ['nullable', 'string', 'in:name_asc,name_desc,start_asc,start_desc'],
            'department' => ['nullable', 'string', 'max:100'],
            'format'     => ['nullable', 'string', 'in:csv,xlsx'],
        ]);

        $searchRaw        = trim((string) ($validated['search'] ?? ''));
        $sort             = $validated['sort'] ?? 'name_asc';
        $departmentFilter = trim((string) ($validated['department'] ?? ''));
        $format           = strtolower((string) ($validated['format'] ?? 'xlsx'));
        $format           = in_array($format, ['csv', 'xlsx'], true) ? $format : 'xlsx';

        $tz              = $this->tz();
        $since           = Carbon::now($tz)->subDays(30)->toDateString();
        $activeCompanyId = $this->activeCompanyId($request);

        $usersQuery = User::query()->with([
            'employeeProfile:id,user_id,designation,department_id',
            'employeeProfile.department:id,name',
        ]);

        if ($activeCompanyId) {
            $usersQuery->whereHas('employeeProfile', function ($query) use ($activeCompanyId): void {
                $query->where(function ($inner) use ($activeCompanyId): void {
                    $inner->where('company_id', $activeCompanyId)->orWhereNull('company_id');
                });
            });
        }

        if ($searchRaw !== '') {
            $usersQuery->where(function ($q) use ($searchRaw) {
                $q->where('name', 'like', '%' . $searchRaw . '%')
                    ->orWhereHas('employeeProfile', fn ($p) => $p->where('designation', 'like', '%' . $searchRaw . '%'));
            });
        }

        if ($departmentFilter !== '') {
            $usersQuery->whereHas('employeeProfile.department', function ($q) use ($departmentFilter): void {
                $q->where('name', $departmentFilter);
            });
        }

        $sort === 'name_desc'
            ? $usersQuery->orderByDesc('name')->orderBy('id')
            : $usersQuery->orderBy('name')->orderBy('id');

        $users = $usersQuery->get()->values();

        $recordsQuery = AttendanceRecord::query();
        $this->applyTenantScope($recordsQuery, $activeCompanyId);
        $records = $recordsQuery
            ->whereDate('work_date', '>=', $since)
            ->whereIn('user_id', $users->pluck('id'))
            ->whereNotNull('check_in_at')
            ->whereNotNull('check_out_at')
            ->get()
            ->groupBy('user_id');

        $overridesQuery = HcmScheduleTiming::query();
        $this->applyTenantScope($overridesQuery, $activeCompanyId);
        $overrides = $overridesQuery
            ->with(['shift:id,name'])
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $rows = $users->map(function (User $u) use ($records, $tz, $overrides) {
            $recs         = $records->get($u->id) ?? collect();
            $startMinutes = [];
            $endMinutes   = [];

            foreach ($recs as $rec) {
                $ci = $rec->check_in_at ? $rec->check_in_at->copy()->timezone($tz) : null;
                $co = $rec->check_out_at ? $rec->check_out_at->copy()->timezone($tz) : null;
                if ($ci && $co) {
                    $startMinutes[] = ((int) $ci->format('H')) * 60 + ((int) $ci->format('i'));
                    $endMinutes[]   = ((int) $co->format('H')) * 60 + ((int) $co->format('i'));
                }
            }

            $defaultStart = 9 * 60;
            $defaultEnd   = 18 * 60;
            $avgStart     = count($startMinutes)
                ? (int) round(array_sum($startMinutes) / count($startMinutes))
                : $defaultStart;
            $avgEnd       = count($endMinutes)
                ? (int) round(array_sum($endMinutes) / count($endMinutes))
                : $defaultEnd;

            $source = 'auto';
            if ($overrides->has($u->id)) {
                $ov       = $overrides->get($u->id);
                $avgStart = ((int) substr((string) $ov->start_time, 0, 2)) * 60 + ((int) substr((string) $ov->start_time, 3, 2));
                $avgEnd   = ((int) substr((string) $ov->end_time, 0, 2)) * 60 + ((int) substr((string) $ov->end_time, 3, 2));
                $source   = (string) ($ov->source ?: 'manual');
            }

            $slot        = sprintf('%02d:%02d - %02d:%02d', intdiv($avgStart, 60), $avgStart % 60, intdiv($avgEnd, 60), $avgEnd % 60);
            $designation = (string) ($u->employeeProfile?->designation ?: 'Employee');
            $department  = (string) ($u->employeeProfile?->department?->name ?: '—');
            $ov          = $overrides->get($u->id);

            return [
                'name'             => (string) $u->name,
                'department'       => $department,
                'jobTitle'         => $designation,
                'availableTimings' => $slot,
                'shiftName'        => (string) ($ov?->shift?->name ?: '—'),
                'sourceLabel'      => $source === 'manual' ? 'Manual Override' : 'Auto',
                'startMinutes'     => $avgStart,
            ];
        })->values();

        if ($sort === 'start_asc') {
            $rows = $rows->sortBy('startMinutes')->values();
        } elseif ($sort === 'start_desc') {
            $rows = $rows->sortByDesc('startMinutes')->values();
        }

        $headers    = ['Name', 'Department', 'Job Title', 'Available Timings', 'Shift', 'Source'];
        $exportRows = $rows->map(fn (array $row): array => [
            $row['name'],
            $row['department'],
            $row['jobTitle'],
            $row['availableTimings'],
            $row['shiftName'],
            $row['sourceLabel'],
        ])->all();

        $fileBase = 'schedule-timing-' . Carbon::now($tz)->format('Ymd-His');

        return TabularExportResponse::download($headers, $exportRows, $fileBase, $format, 'Schedule Timing');
    }

    public function scheduleTimingUpsert(Request $request, int $userId): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'shiftId'   => ['nullable', 'integer', 'exists:hcm_shifts,id'],
            'startTime' => ['required_without:shiftId', 'nullable', 'date_format:H:i'],
            'endTime'   => ['required_without:shiftId', 'nullable', 'date_format:H:i'],
        ]);

        $activeCompanyId = $this->activeCompanyId($request);

        if (! $this->userBelongsToActiveCompany($userId, $activeCompanyId)) {
            return $this->userNotInCompanyResponse();
        }

        $shiftId  = isset($validated['shiftId']) ? (int) $validated['shiftId'] : null;
        $startStr = null;
        $endStr   = null;
        $hcmShiftId = null;

        if ($shiftId) {
            $shiftQuery = HcmShift::query()->whereKey($shiftId)->where('is_active', true);
            $this->applyTenantScope($shiftQuery, $activeCompanyId);
            $shift = $shiftQuery->first();

            if (! $shift) {
                return response()->json([
                    'success' => false,
                    'error'   => [
                        'code'    => 'VALIDATION_ERROR',
                        'message' => 'Shift not found or inactive.',
                    ],
                ], 422);
            }

            $startStr   = Carbon::parse($shift->start_time)->format('H:i');
            $endStr     = Carbon::parse($shift->end_time)->format('H:i');
            $hcmShiftId = $shift->id;
        } else {
            $startStr = $validated['startTime'];
            $endStr   = $validated['endTime'];
        }

        if ($endStr === $startStr) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => 'endTime cannot be equal to startTime.',
                ],
            ], 422);
        }

        User::query()->findOrFail($userId);

        $upsertQuery = HcmScheduleTiming::query()->where('user_id', $userId);
        if ($activeCompanyId !== null) {
            $upsertQuery->where('company_id', $activeCompanyId);
        } else {
            $upsertQuery->whereNull('company_id');
        }

        $row = $upsertQuery->first();
        if ($row) {
            $row->fill([
                'company_id'         => $activeCompanyId,
                'hcm_shift_id'       => $hcmShiftId,
                'start_time'         => $startStr,
                'end_time'           => $endStr,
                'source'             => 'manual',
                'updated_by_user_id' => $request->user()?->id,
            ])->save();
        } else {
            $row = HcmScheduleTiming::query()->create([
                'company_id'         => $activeCompanyId,
                'user_id'            => $userId,
                'hcm_shift_id'       => $hcmShiftId,
                'start_time'         => $startStr,
                'end_time'           => $endStr,
                'source'             => 'manual',
                'updated_by_user_id' => $request->user()?->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => ['id' => $row->id],
        ]);
    }

    public function scheduleTimingDestroy(Request $request, int $userId): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $activeCompanyId = $this->activeCompanyId($request);

        if (! $this->userBelongsToActiveCompany($userId, $activeCompanyId)) {
            return $this->userNotInCompanyResponse();
        }

        User::query()->findOrFail($userId);

        $deleteQuery = HcmScheduleTiming::query()->where('user_id', $userId);
        if ($activeCompanyId !== null) {
            $deleteQuery->where('company_id', $activeCompanyId);
        } else {
            $deleteQuery->whereNull('company_id');
        }
        $deleteQuery->delete();

        return response()->json(['success' => true]);
    }
}
