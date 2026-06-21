<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\EmployeeProfile;
use App\Models\ReportSnapshot;
use App\Models\User;
use App\Notifications\AttendanceCorrectionApprovedNotification;
use App\Support\Exports\TabularExportResponse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceAdminController extends BaseAttendanceController
{
    private function adminAttendanceFilteredQuery(string $dateYmd, ?string $search, ?string $department, ?string $statusFilter, ?int $companyId): Builder
    {
        $q = User::query()
            ->leftJoin('employee_profiles as ep', 'ep.user_id', '=', 'users.id')
            ->leftJoin('attendance_records as ar', function ($join) use ($dateYmd) {
                $join->on('ar.user_id', '=', 'users.id')
                    ->where('ar.work_date', '=', $dateYmd);
            });

        if ($companyId) {
            $q->where(function ($inner) use ($companyId): void {
                $inner->whereExists(function ($membershipQuery) use ($companyId): void {
                    $membershipQuery->selectRaw('1')
                        ->from('company_users')
                        ->whereColumn('company_users.user_id', 'users.id')
                        ->where('company_users.company_id', $companyId)
                        ->where('company_users.status', 'active');
                })->orWhere('ep.company_id', $companyId);
            })
                ->whereNotExists(function ($ownerSub) use ($companyId): void {
                    $ownerSub->selectRaw('1')
                        ->from('company_users')
                        ->whereColumn('company_users.user_id', 'users.id')
                        ->where('company_users.company_id', $companyId)
                        ->where('company_users.status', 'active')
                        ->where('company_users.role', 'owner');
                });
        }

        if ($search) {
            $q->where(function ($inner) use ($search) {
                $inner->where('users.name', 'like', '%'.$search.'%')
                    ->orWhere('users.email', 'like', '%'.$search.'%');
            });
        }

        if ($department !== null && $department !== '') {
            $q->where('ep.team', $department);
        }

        if ($statusFilter === 'present') {
            $q->whereNotNull('ar.check_in_at');
        } elseif ($statusFilter === 'absent') {
            $q->where(function ($inner) {
                $inner->whereNull('ar.id')->orWhereNull('ar.check_in_at');
            });
        } elseif ($statusFilter === 'needs_review') {
            $q->where('ar.status', 'needs_review');
        }

        return $q;
    }

    private function attendanceAdminProductionSortExpression(): string
    {
        return DB::connection()->getDriverName() === 'mysql'
            ? '(CASE WHEN ar.check_in_at IS NOT NULL AND ar.check_out_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, ar.check_in_at, ar.check_out_at) - COALESCE(ar.break_minutes, 0) ELSE -1 END)'
            : '(CASE WHEN ar.check_in_at IS NOT NULL AND ar.check_out_at IS NOT NULL THEN (strftime(\'%s\', ar.check_out_at) - strftime(\'%s\', ar.check_in_at)) / 60.0 - COALESCE(ar.break_minutes, 0) ELSE -1 END)';
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        Cache::forget('admin_attendance_'.Carbon::now($this->tz())->toDateString());

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:present,absent,needs_review'],
            'sort' => ['nullable', 'string', 'in:name_asc,name_desc,checkin_asc,checkin_desc,production_desc,production_asc'],
        ]);

        $dateYmd = $validated['date'] ?? Carbon::now($this->tz())->toDateString();
        $search = $validated['search'] ?? null;
        $department = $validated['department'] ?? null;
        $statusFilter = $validated['status'] ?? null;
        $sort = $validated['sort'] ?? 'name_asc';
        $perPage = min(100, (int) ($validated['perPage'] ?? 50));
        $activeCompanyId = $this->activeCompanyId($request);

        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required to load attendance report.',
                ],
            ], 422);
        }

        $todayYmd = Carbon::now($this->tz())->toDateString();
        $isToday = $dateYmd === $todayYmd;

        $base = $this->adminAttendanceFilteredQuery($dateYmd, $search, $department, $statusFilter, $activeCompanyId);

        $statsRow = (clone $base)
            ->selectRaw('
                COUNT(DISTINCT users.id) as total_employees,
                SUM(CASE WHEN ar.check_in_at IS NOT NULL THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN ar.id IS NULL OR ar.check_in_at IS NULL THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN ar.check_in_at IS NOT NULL AND ar.late_minutes > 0 THEN 1 ELSE 0 END) as late_login
            ')
            ->first();

        $listQuery = (clone $base)->select('users.*');
        $prodExpr = $this->attendanceAdminProductionSortExpression();

        match ($sort) {
            'name_desc' => $listQuery->orderByDesc('users.name')->orderBy('users.id'),
            'checkin_asc' => $listQuery->orderByRaw('ar.check_in_at IS NULL, ar.check_in_at ASC')->orderBy('users.name'),
            'checkin_desc' => $listQuery->orderByRaw('ar.check_in_at IS NULL DESC, ar.check_in_at DESC')->orderBy('users.name'),
            'production_desc' => $listQuery->orderByRaw($prodExpr.' DESC')->orderBy('users.name'),
            'production_asc' => $listQuery->orderByRaw($prodExpr.' ASC')->orderBy('users.name'),
            default => $listQuery->orderBy('users.name')->orderBy('users.id'),
        };

        $paginator = $listQuery->with(['employeeProfile:id,user_id,team,designation'])->paginate($perPage);
        $userIds = collect($paginator->items())->pluck('id');

        $records = AttendanceRecord::query()
            ->whereIn('user_id', $userIds)
            ->whereDate('work_date', $dateYmd)
            ->get()
            ->keyBy('user_id');

        $selfieRecords = AttendanceRecord::query()
            ->whereIn('user_id', $userIds)
            ->whereDate('work_date', $dateYmd)
            ->whereNotNull('selfie_path')
            ->where('selfie_path', '!=', '')
            ->orderByDesc('id')
            ->get()
            ->groupBy('user_id');

        $rows = [];
        foreach ($paginator->items() as $user) {
            /** @var User $user */
            /** @var AttendanceRecord|null $rec */
            $rec = $records->get($user->id);
            $selfieRec = ($selfieRecords->get($user->id) ?? collect())->first();
            $selfieRecordId = $selfieRec?->id ?? $rec?->id;
            $profile = $user->employeeProfile;
            $team = $profile?->team ?: ($profile?->designation ?: '—');

            $checkIn = $rec?->check_in_at;
            $checkOut = $rec?->check_out_at;
            $breakMin = (int) ($rec?->break_minutes ?? 0);
            $lateMin = (int) ($rec?->late_minutes ?? 0);
            $rawStatus = (string) ($rec?->status ?? '');

            $net = $this->netProductionMinutes($checkIn, $checkOut, $breakMin, $isToday);
            $prod = $this->formatProduction($net);
            [, $otLabel] = $this->overtimeForDisplay($net);
            $derivedNeedsReview = $checkIn && $checkOut && $net !== null && $net < self::EARLY_PUNCH_OUT_REVIEW_MINUTES;

            $hasPunchIn = (bool) $checkIn;
            $statusKey = $hasPunchIn ? 'present' : 'absent';
            $statusBadgeClass = $hasPunchIn ? 'success-transparent' : 'danger-transparent';
            $statusLabel = $hasPunchIn ? 'Present' : 'Absent';
            if ($rawStatus === 'needs_review' || $derivedNeedsReview) {
                $statusKey = 'needs_review';
                $statusBadgeClass = 'warning-transparent';
                $statusLabel = 'Needs Review';
            }

            $initial = strtoupper(mb_substr((string) $user->name, 0, 1));
            $checkInLoc = $rec?->check_in_location_name;
            if (! $checkInLoc) {
                $checkInLoc = ($rec?->check_in_latitude && $rec?->check_in_longitude)
                    ? round((float) $rec->check_in_latitude, 4).', '.round((float) $rec->check_in_longitude, 4)
                    : '—';
            }

            $checkOutLoc = $rec?->check_out_location_name;
            if (! $checkOutLoc) {
                $checkOutLoc = ($rec?->check_out_latitude && $rec?->check_out_longitude)
                    ? round((float) $rec->check_out_latitude, 4).', '.round((float) $rec->check_out_longitude, 4)
                    : '—';
            }

            $rows[] = [
                'userId' => $user->id,
                'recordId' => $selfieRecordId,
                'employeeName' => $user->name,
                'employeeEmail' => (string) ($user->email ?? ''),
                'team' => $team,
                'initial' => $initial,
                'statusKey' => $statusKey,
                'statusLabel' => $statusLabel,
                'statusBadgeClass' => $statusBadgeClass,
                'checkIn' => $this->formatTime($checkIn),
                'checkOut' => $this->formatTime($checkOut),
                'checkInTime24' => $checkIn ? $checkIn->copy()->timezone($this->tz())->format('H:i') : '',
                'checkOutTime24' => $checkOut ? $checkOut->copy()->timezone($this->tz())->format('H:i') : '',
                'checkInLocation' => $checkInLoc,
                'checkOutLocation' => $checkOutLoc,
                'breakMinutesRaw' => $breakMin,
                'lateMinutesRaw' => $lateMin,
                'break' => $breakMin > 0 ? $breakMin.' Min' : '-',
                'late' => $lateMin > 0 ? $lateMin.' Min' : '-',
                'overtime' => $otLabel,
                'productionLabel' => $prod['label'],
                'productionBadgeClass' => $prod['badge'],
                'correctionStatus' => (string) ($rec?->correction_status ?? 'none'),
                'correctionReason' => (string) ($rec?->correction_reason ?? ''),
                'correctionRequestedAt' => $rec?->correction_requested_at
                    ? $rec->correction_requested_at->copy()->timezone($this->tz())->format('Y-m-d H:i:s')
                    : null,
                'hasSelfie' => (bool) $selfieRec,
            ];
        }

        $departments = EmployeeProfile::query()
            ->where('company_id', $activeCompanyId)
            ->whereNotNull('team')
            ->where('team', '!=', '')
            ->distinct()
            ->orderBy('team')
            ->pluck('team')
            ->values();

        $pendingCorrectionCount = AttendanceRecord::query()
            ->where('company_id', $activeCompanyId)
            ->whereDate('work_date', $dateYmd)
            ->where('correction_status', 'requested')
            ->count();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'date' => $dateYmd,
                'departments' => $departments,
                'summary' => [
                    'totalEmployees' => (int) ($statsRow->total_employees ?? 0),
                    'present' => (int) ($statsRow->present ?? 0),
                    'absent' => (int) ($statsRow->absent ?? 0),
                    'lateLogin' => (int) ($statsRow->late_login ?? 0),
                    'uninformed' => 0,
                    'permission' => 0,
                    'pendingCorrectionCount' => $pendingCorrectionCount,
                ],
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'totalPages' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function adminExport(Request $request): StreamedResponse|JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:present,absent,needs_review'],
            'sort' => ['nullable', 'string', 'in:name_asc,name_desc,checkin_asc,checkin_desc,production_desc,production_asc'],
            'format' => ['nullable', 'string', 'in:xlsx,csv'],
            'source' => ['nullable', 'string', 'in:live,archive'],
            'snapshotId' => ['nullable', 'integer', 'min:1'],
        ]);

        $dateYmd = $validated['date'] ?? Carbon::now($this->tz())->toDateString();
        $search = $validated['search'] ?? null;
        $department = $validated['department'] ?? null;
        $statusFilter = $validated['status'] ?? null;
        $sort = $validated['sort'] ?? 'name_asc';
        $format = strtolower((string) ($validated['format'] ?? 'xlsx'));
        $source = strtolower((string) ($validated['source'] ?? 'live'));
        $snapshotId = (int) ($validated['snapshotId'] ?? 0);
        $activeCompanyId = $this->activeCompanyId($request);

        if (! $activeCompanyId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required to export attendance report.',
                ],
            ], 422);
        }

        $headers = [
            'Employee', 'Department', 'Date', 'Check In', 'Check In Location',
            'Status', 'Check Out', 'Check Out Location', 'Break', 'Late',
            'Overtime', 'Production Hours', 'Correction',
        ];

        if ($source === 'archive') {
            if ($snapshotId <= 0) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'snapshotId is required for archive export.'],
                ], 422);
            }

            $snapshot = ReportSnapshot::query()
                ->where('company_id', $activeCompanyId)
                ->where('id', $snapshotId)
                ->with('dataBlocks')
                ->first();

            if (! $snapshot) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'SNAPSHOT_NOT_FOUND', 'message' => 'Snapshot not found.'],
                ], 404);
            }

            if (strtolower((string) $snapshot->report_type) !== 'attendance') {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'SNAPSHOT_TYPE_MISMATCH', 'message' => 'Snapshot is not an attendance report.'],
                ], 422);
            }

            if (strtolower((string) $snapshot->status) !== 'completed') {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'SNAPSHOT_NOT_READY', 'message' => 'Snapshot attendance is not completed yet.'],
                ], 422);
            }

            $moduleData = [];
            foreach ($snapshot->dataBlocks as $block) {
                if ((string) $block->module !== 'attendance') {
                    continue;
                }
                $moduleData[(string) $block->data_key] = $block->data_value;
            }

            $rows = [];
            foreach ($moduleData as $key => $item) {
                if (! str_starts_with((string) $key, 'user_') || ! is_array($item)) {
                    continue;
                }
                $presentCount = (int) ($item['present'] ?? 0);
                $absentCount = (int) ($item['absent'] ?? 0);
                $statusLabel = $presentCount >= $absentCount ? 'Present' : 'Absent';
                $lateLabel = ((int) ($item['total_late_minutes'] ?? 0)) > 0
                    ? ((int) $item['total_late_minutes']).' Min'
                    : '-';

                $rows[] = [
                    (string) ($item['user_name'] ?? 'Unknown'), 'Archive',
                    (string) ($snapshot->period_end?->toDateString() ?? $dateYmd),
                    '-', '-', $statusLabel, '-', '-', '-', $lateLabel, '-', '-',
                ];
            }

            return TabularExportResponse::download(
                $headers, $rows,
                'attendance-report-archive-'.$snapshot->id.'-'.now()->format('YmdHis'),
                $format, 'Attendance Report',
            );
        }

        $base = $this->adminAttendanceFilteredQuery($dateYmd, $search, $department, $statusFilter, $activeCompanyId);
        $listQuery = (clone $base)->select('users.*');
        $prodExpr = $this->attendanceAdminProductionSortExpression();

        match ($sort) {
            'name_desc' => $listQuery->orderByDesc('users.name')->orderBy('users.id'),
            'checkin_asc' => $listQuery->orderByRaw('ar.check_in_at IS NULL, ar.check_in_at ASC')->orderBy('users.name'),
            'checkin_desc' => $listQuery->orderByRaw('ar.check_in_at IS NULL DESC, ar.check_in_at DESC')->orderBy('users.name'),
            'production_desc' => $listQuery->orderByRaw($prodExpr.' DESC')->orderBy('users.name'),
            'production_asc' => $listQuery->orderByRaw($prodExpr.' ASC')->orderBy('users.name'),
            default => $listQuery->orderBy('users.name')->orderBy('users.id'),
        };

        $users = $listQuery->with(['employeeProfile:id,user_id,team,designation'])->get();
        $records = AttendanceRecord::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->whereDate('work_date', $dateYmd)
            ->get()
            ->keyBy('user_id');

        $todayYmd = Carbon::now($this->tz())->toDateString();
        $rows = [];
        foreach ($users as $user) {
            $rec = $records->get($user->id);
            $profile = $user->employeeProfile;
            $team = $profile?->team ?: ($profile?->designation ?: '—');
            $checkIn = $rec?->check_in_at;
            $checkOut = $rec?->check_out_at;
            $breakMin = (int) ($rec?->break_minutes ?? 0);
            $lateMin = (int) ($rec?->late_minutes ?? 0);
            $rawStatus = (string) ($rec?->status ?? '');

            $net = $this->netProductionMinutes($checkIn, $checkOut, $breakMin, $dateYmd === $todayYmd);
            $prod = $this->formatProduction($net);
            [, $otLabel] = $this->overtimeForDisplay($net);
            $derivedNeedsReview = $checkIn && $checkOut && $net !== null && $net < self::EARLY_PUNCH_OUT_REVIEW_MINUTES;

            $statusLabel = (bool) $checkIn ? 'Present' : 'Absent';
            if ($rawStatus === 'needs_review' || $derivedNeedsReview) {
                $statusLabel = 'Needs Review';
            }

            $checkInLoc = $rec?->check_in_location_name;
            if (! $checkInLoc) {
                $checkInLoc = ($rec?->check_in_latitude && $rec?->check_in_longitude)
                    ? round((float) $rec->check_in_latitude, 4).', '.round((float) $rec->check_in_longitude, 4)
                    : '—';
            }

            $checkOutLoc = $rec?->check_out_location_name;
            if (! $checkOutLoc) {
                $checkOutLoc = ($rec?->check_out_latitude && $rec?->check_out_longitude)
                    ? round((float) $rec->check_out_latitude, 4).', '.round((float) $rec->check_out_longitude, 4)
                    : '—';
            }

            $corrLabel = match ((string) ($rec?->correction_status ?? 'none')) {
                'approved' => 'Corrected',
                'dismissed' => 'Dismissed',
                'requested' => 'Pending',
                default => '-',
            };

            $rows[] = [
                (string) $user->name, (string) $team, $dateYmd,
                $this->formatTime($checkIn), (string) $checkInLoc, $statusLabel,
                $this->formatTime($checkOut), (string) $checkOutLoc,
                $breakMin > 0 ? $breakMin.' Min' : '-',
                $lateMin > 0 ? $lateMin.' Min' : '-',
                $otLabel, (string) $prod['label'], $corrLabel,
            ];
        }

        return TabularExportResponse::download(
            $headers, $rows,
            'attendance-report-live-'.now()->format('YmdHis'),
            $format, 'Attendance Report',
        );
    }

    public function adminUpsertRecord(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'userId' => ['required', 'integer', 'exists:users,id'],
            'workDate' => ['required', 'date'],
            'checkInTime' => ['nullable', 'date_format:H:i'],
            'checkOutTime' => ['nullable', 'date_format:H:i'],
            'breakMinutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            'lateMinutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
        ]);

        $tz = $this->tz();
        $workDate = Carbon::parse($validated['workDate'], $tz)->toDateString();
        $activeCompanyId = $this->activeCompanyId($request);

        if (! $this->userBelongsToActiveCompany($validated['userId'], $activeCompanyId)) {
            return $this->userNotInCompanyResponse();
        }

        $recQuery = AttendanceRecord::query();
        $this->applyTenantScope($recQuery, $activeCompanyId);
        $rec = $recQuery
            ->where('user_id', $validated['userId'])
            ->whereDate('work_date', $workDate)
            ->first();

        $hasIn = ! empty($validated['checkInTime']);
        $hasOut = ! empty($validated['checkOutTime']);
        $break = array_key_exists('breakMinutes', $validated)
            ? (int) $validated['breakMinutes']
            : (int) ($rec?->break_minutes ?? 0);
        $late = array_key_exists('lateMinutes', $validated)
            ? (int) $validated['lateMinutes']
            : (int) ($rec?->late_minutes ?? 0);

        if ($hasIn && $hasOut) {
            $inAt = Carbon::parse($workDate.' '.$validated['checkInTime'], $tz);
            $outAt = Carbon::parse($workDate.' '.$validated['checkOutTime'], $tz);
            if ($outAt->lt($inAt)) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'checkOutTime must be after or equal to checkInTime.'],
                ], 422);
            }
        }

        if (! $hasIn && $late > 0) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'lateMinutes requires checkInTime.'],
            ], 422);
        }

        if (! $hasIn && ! $hasOut && $break === 0 && $late === 0) {
            if ($rec) {
                if ((string) ($rec->correction_status ?? '') === 'requested') {
                    $company = Company::query()->find($activeCompanyId);
                    $companyUuid = (string) ($company?->uuid ?? '');
                    $employee = User::query()->find($rec->user_id);
                    $employee?->notify(new AttendanceCorrectionApprovedNotification(
                        companyUuid: $companyUuid,
                        workDate: $rec->work_date->toDateString(),
                        attendanceRecordId: (int) $rec->id,
                    ));
                }
                $rec->delete();
            }

            return response()->json(['success' => true, 'data' => ['deleted' => true]]);
        }

        if (! $rec) {
            $rec = new AttendanceRecord([
                'company_id' => $activeCompanyId,
                'user_id' => $validated['userId'],
                'work_date' => $workDate,
                'status' => 'present',
                'correction_status' => 'none',
                'break_minutes' => 0,
                'late_minutes' => 0,
            ]);
        }

        $rec->break_minutes = $break;

        if ($hasIn) {
            $rec->check_in_at = Carbon::parse($workDate.' '.$validated['checkInTime'], $tz);
            if (array_key_exists('lateMinutes', $validated) && $validated['lateMinutes'] !== null) {
                $rec->late_minutes = (int) $validated['lateMinutes'];
            } else {
                $expected = $this->expectedCheckIn($workDate);
                $rec->late_minutes = $rec->check_in_at->greaterThan($expected)
                    ? (int) $expected->diffInMinutes($rec->check_in_at)
                    : 0;
            }
            $rec->status = 'present';
        } else {
            $rec->check_in_at = null;
            $rec->late_minutes = 0;
            $rec->status = 'absent';
        }

        $rec->check_out_at = $hasOut
            ? Carbon::parse($workDate.' '.$validated['checkOutTime'], $tz)
            : null;

        $correctionWasPending = (string) $rec->correction_status === 'requested';
        if ($correctionWasPending) {
            $rec->correction_status = 'approved';
            $rec->corrected_by_user_id = $request->user()?->id;
            $rec->corrected_at = Carbon::now($tz);
        }

        $rec->save();

        if ($correctionWasPending) {
            $company = Company::query()->find($activeCompanyId);
            $companyUuid = (string) ($company?->uuid ?? '');
            $employee = User::query()->find($rec->user_id);
            $employee?->notify(new AttendanceCorrectionApprovedNotification(
                companyUuid: $companyUuid,
                workDate: $rec->work_date->toDateString(),
                attendanceRecordId: (int) $rec->id,
            ));
        }

        return response()->json(['success' => true, 'data' => ['recordId' => $rec->id]]);
    }
}
