<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\EmployeeProfile;
use App\Models\HcmScheduleTiming;
use App\Models\HcmShift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    use EnsuresHcmAdmin;

    private const TARGET_DAILY_MINUTES = 8 * 60;

    private const OVERTIME_THRESHOLD_MINUTES = 9 * 60;
    private const EARLY_PUNCH_OUT_REVIEW_MINUTES = 4 * 60;

    private function tz(): string
    {
        return config('app.timezone');
    }

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    private function applyTenantScope(Builder $query, ?int $companyId): Builder
    {
        if (! $companyId) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($companyId): void {
            $inner->where('company_id', $companyId)->orWhereNull('company_id');
        });
    }

    private function expectedCheckIn(string $dateYmd): Carbon
    {
        return Carbon::parse($dateYmd.' 09:00:00', $this->tz());
    }

    private function netProductionMinutes(
        ?Carbon $in,
        ?Carbon $out,
        int $breakMinutes,
        bool $useNowForOpenShift,
    ): ?int {
        if (! $in) {
            return null;
        }
        $end = $out;
        if (! $end && $useNowForOpenShift) {
            $end = Carbon::now($this->tz());
        }
        if (! $end) {
            return null;
        }
        $mins = $in->diffInMinutes($end);

        return max(0, $mins - $breakMinutes);
    }

    /**
     * @return array{label: string, hours: float|null, badge: string}
     */
    private function formatProduction(?int $netMinutes): array
    {
        if ($netMinutes === null) {
            return ['label' => '-', 'hours' => null, 'badge' => 'secondary'];
        }
        $hrs = round($netMinutes / 60, 2);
        $label = sprintf('%.2f Hrs', $hrs);
        $badge = $netMinutes >= self::TARGET_DAILY_MINUTES ? 'success' : 'danger';

        return ['label' => $label, 'hours' => $hrs, 'badge' => $badge];
    }

    private function formatTime(?Carbon $dt): string
    {
        if (! $dt) {
            return '-';
        }

        return $dt->copy()->timezone($this->tz())->format('h:i A');
    }

    /**
     * @return array{0: int|null, 1: string, 2: string}
     */
    private function overtimeForDisplay(?int $netMinutes): array
    {
        if ($netMinutes === null) {
            return [null, '-', 'secondary'];
        }
        $ot = max(0, $netMinutes - self::OVERTIME_THRESHOLD_MINUTES);
        if ($ot <= 0) {
            return [0, '-', 'secondary'];
        }

        return [$ot, $ot.' Min', 'success'];
    }

    /**
     * Shared filter/join scope for admin attendance (one row per user per work date).
     */
    private function adminAttendanceFilteredQuery(string $dateYmd, ?string $search, ?string $department, ?string $statusFilter, ?int $companyId)
    {
        $q = User::query()
            ->leftJoin('employee_profiles as ep', 'ep.user_id', '=', 'users.id')
            ->leftJoin('attendance_records as ar', function ($join) use ($dateYmd) {
                $join->on('ar.user_id', '=', 'users.id')
                    ->where('ar.work_date', '=', $dateYmd);
            });

        if ($companyId) {
            $q->where(function ($inner) use ($companyId): void {
                $inner->where('ep.company_id', $companyId)->orWhereNull('ep.company_id');
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
        $recordsQuery = AttendanceRecord::query();
        $this->applyTenantScope($recordsQuery, $activeCompanyId);
        $records = $recordsQuery
            ->whereIn('user_id', $userIds)
            ->where('work_date', $dateYmd)
            ->get()
            ->keyBy('user_id');

        $rows = [];
        foreach ($paginator->items() as $user) {
            /** @var User $user */
            /** @var AttendanceRecord|null $rec */
            $rec = $records->get($user->id);
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

            $rows[] = [
                'userId' => $user->id,
                'recordId' => $rec?->id,
                'employeeName' => $user->name,
                'team' => $team,
                'initial' => $initial,
                'statusKey' => $statusKey,
                'statusLabel' => $statusLabel,
                'statusBadgeClass' => $statusBadgeClass,
                'checkIn' => $this->formatTime($checkIn),
                'checkOut' => $this->formatTime($checkOut),
                'checkInTime24' => $checkIn ? $checkIn->copy()->timezone($this->tz())->format('H:i') : '',
                'checkOutTime24' => $checkOut ? $checkOut->copy()->timezone($this->tz())->format('H:i') : '',
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
            ];
        }

        $departments = EmployeeProfile::query()
            ->whereNotNull('team')
            ->where('team', '!=', '')
            ->distinct()
            ->orderBy('team')
            ->pluck('team')
            ->values();

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
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'checkOutTime must be after or equal to checkInTime.',
                    ],
                ], 422);
            }
        }

        if (! $hasIn && $late > 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'lateMinutes requires checkInTime.',
                ],
            ], 422);
        }

        if (! $hasIn && ! $hasOut && $break === 0 && $late === 0) {
            if ($rec) {
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
                if ($rec->check_in_at->greaterThan($expected)) {
                    $rec->late_minutes = (int) $expected->diffInMinutes($rec->check_in_at);
                } else {
                    $rec->late_minutes = 0;
                }
            }
            $rec->status = 'present';
        } else {
            $rec->check_in_at = null;
            $rec->late_minutes = 0;
            $rec->status = 'absent';
        }

        if ($hasOut) {
            $rec->check_out_at = Carbon::parse($workDate.' '.$validated['checkOutTime'], $tz);
        } else {
            $rec->check_out_at = null;
        }

        if ((string) $rec->correction_status === 'requested') {
            $rec->correction_status = 'approved';
            $rec->corrected_by_user_id = $request->user()?->id;
            $rec->corrected_at = Carbon::now($tz);
        }

        $rec->save();

        return response()->json([
            'success' => true,
            'data' => ['recordId' => $rec->id],
        ]);
    }

    public function meToday(Request $request): JsonResponse
    {
        $user = $request->user();
        $todayYmd = Carbon::now($this->tz())->toDateString();

        $profile = $user->employeeProfile;
        $recordsQuery = AttendanceRecord::query();
        $this->applyTenantScope($recordsQuery, $this->activeCompanyId($request));
        $rec = $recordsQuery
            ->where('user_id', $user->id)
            ->whereDate('work_date', $todayYmd)
            ->first();

        $checkIn = $rec?->check_in_at;
        $checkOut = $rec?->check_out_at;
        $breakMin = (int) ($rec?->break_minutes ?? 0);

        $net = $this->netProductionMinutes($checkIn, $checkOut, $breakMin, true);
        $prod = $this->formatProduction($net);
        $hoursSoFar = $prod['hours'] !== null ? sprintf('%.2f', $prod['hours']) : '0.00';
        [, $otSummaryLabel] = $this->overtimeForDisplay($net);

        $grossMinutes = null;
        if ($checkIn) {
            $spanEnd = $checkOut ?? Carbon::now($this->tz());
            $grossMinutes = max(0, (int) $checkIn->diffInMinutes($spanEnd));
        }

        $progress = 0;
        if ($net !== null && self::TARGET_DAILY_MINUTES > 0) {
            $progress = (int) min(100, round(($net / self::TARGET_DAILY_MINUTES) * 100));
        }

        $punchState = 'none';
        $buttonLabel = 'Punch In';
        if ($checkIn && ! $checkOut) {
            $punchState = 'in';
            $buttonLabel = 'Punch Out';
        } elseif ($checkIn && $checkOut) {
            $punchState = 'done';
            $buttonLabel = 'Completed';
        }
        $needsReview = (string) ($rec?->status ?? '') === 'needs_review'
            || ($checkIn && $checkOut && $net !== null && $net < self::EARLY_PUNCH_OUT_REVIEW_MINUTES);
        $breakInProgress = (bool) $rec?->break_started_at;
        $breakButtonLabel = $breakInProgress ? 'End Break' : 'Start Break';
        $breakButtonDisabled = ! $checkIn || (bool) $checkOut;
        $alertMessage = $needsReview
            ? 'Punch out terlalu cepat terdeteksi. Data ditandai Needs Review, silakan ajukan koreksi ke admin.'
            : null;

        $now = Carbon::now($this->tz());

        return response()->json([
            'success' => true,
            'data' => [
                'userName' => $user->name,
                'team' => $profile?->team ?: ($profile?->designation ?: ''),
                'nowLabel' => $now->format('h:i A').', '.$now->format('d M Y'),
                'productionHoursSoFar' => $hoursSoFar,
                'productionProgressPercent' => $progress,
                'productionBadge' => 'Production :  '.$hoursSoFar.' hrs',
                'punchState' => $punchState,
                'punchInAtFormatted' => $this->formatTime($checkIn),
                'punchOutAtFormatted' => $this->formatTime($checkOut),
                'punchLine' => $checkIn
                    ? ('Punch In at  '.$this->formatTime($checkIn))
                    : 'Belum punch in hari ini',
                'punchButtonLabel' => $buttonLabel,
                'punchButtonDisabled' => $punchState === 'done',
                'breakInProgress' => $breakInProgress,
                'breakStartedAtIso' => $rec?->break_started_at?->toIso8601String(),
                'breakButtonLabel' => $breakButtonLabel,
                'breakButtonDisabled' => $breakButtonDisabled,
                'attendanceStatus' => $rec?->status ?: ($checkIn ? 'present' : 'absent'),
                'needsReview' => $needsReview,
                'alertMessage' => $alertMessage,
                'correctionStatus' => (string) ($rec?->correction_status ?? 'none'),
                'greeting' => $this->greetingPrefix().', '.$user->name,
                'summaryTotalWorking' => $this->formatMinutesAsHm($grossMinutes),
                'summaryProductive' => $this->formatMinutesAsHm($net),
                'summaryBreak' => $breakMin > 0 ? $breakMin.'m' : '—',
                'summaryOvertime' => $otSummaryLabel,
                'checkInLatitude' => $rec?->check_in_latitude !== null ? (float) $rec->check_in_latitude : null,
                'checkInLongitude' => $rec?->check_in_longitude !== null ? (float) $rec->check_in_longitude : null,
                'checkOutLatitude' => $rec?->check_out_latitude !== null ? (float) $rec->check_out_latitude : null,
                'checkOutLongitude' => $rec?->check_out_longitude !== null ? (float) $rec->check_out_longitude : null,
            ],
        ]);
    }

    private function greetingPrefix(): string
    {
        $h = (int) Carbon::now($this->tz())->format('G');
        if ($h < 12) {
            return 'Good Morning';
        }
        if ($h < 17) {
            return 'Good Afternoon';
        }

        return 'Good Evening';
    }

    private function formatMinutesAsHm(?int $totalMinutes): string
    {
        if ($totalMinutes === null || $totalMinutes < 0) {
            return '—';
        }
        $h = intdiv($totalMinutes, 60);
        $min = $totalMinutes % 60;
        if ($h > 0) {
            return $h.'h '.sprintf('%02d', $min).'m';
        }

        return $min.'m';
    }

    public function meHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $days = (int) ($validated['days'] ?? 30);
        $user = $request->user();
        $tz = $this->tz();
        $end = Carbon::now($tz)->startOfDay();
        $start = $end->copy()->subDays($days - 1);

        $recordsQuery = AttendanceRecord::query();
        $this->applyTenantScope($recordsQuery, $this->activeCompanyId($request));
        $records = $recordsQuery
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('work_date')
            ->get();

        $todayYmd = Carbon::now($tz)->toDateString();

        $rows = $records->map(function (AttendanceRecord $rec) use ($todayYmd) {
            $checkIn = $rec->check_in_at;
            $checkOut = $rec->check_out_at;
            $breakMin = (int) $rec->break_minutes;
            $lateMin = (int) $rec->late_minutes;
            $isToday = $rec->work_date->toDateString() === $todayYmd;

            $net = $this->netProductionMinutes($checkIn, $checkOut, $breakMin, $isToday);
            $prod = $this->formatProduction($net);
            [, $otLabel, $otBadge] = $this->overtimeForDisplay($net);

            $hasIn = (bool) $checkIn;
            $statusLabel = $hasIn ? 'Present' : 'Absent';
            $statusClass = $hasIn ? 'success-transparent' : 'danger-transparent';
            $derivedNeedsReview = $checkIn && $checkOut && $net !== null && $net < self::EARLY_PUNCH_OUT_REVIEW_MINUTES;
            if ((string) $rec->status === 'needs_review' || $derivedNeedsReview) {
                $statusLabel = 'Needs Review';
                $statusClass = 'warning-transparent';
            }

            return [
                'dateLabel' => $rec->work_date->format('d M Y'),
                'checkIn' => $this->formatTime($checkIn),
                'checkOut' => $this->formatTime($checkOut),
                'statusLabel' => $statusLabel,
                'statusBadgeClass' => $statusClass,
                'break' => $breakMin > 0 ? $breakMin.' Min' : '-',
                'late' => $lateMin > 0 ? $lateMin.' Min' : '-',
                'overtime' => $otLabel,
                'overtimeBadgeClass' => $otBadge,
                'productionLabel' => $prod['label'],
                'productionBadgeClass' => $prod['badge'],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function meStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $tz = $this->tz();
        $now = Carbon::now($tz);

        $weekStart = $now->copy()->startOfWeek();
        $monthStart = $now->copy()->startOfMonth();

        $activeCompanyId = $this->activeCompanyId($request);

        $weekMinutes = $this->sumProductionMinutes($user->id, $weekStart, $now, $activeCompanyId);
        $monthMinutes = $this->sumProductionMinutes($user->id, $monthStart, $now, $activeCompanyId);

        $weekHours = round($weekMinutes / 60, 2);
        $monthHours = round($monthMinutes / 60, 2);
        $todayQuery = AttendanceRecord::query();
        $this->applyTenantScope($todayQuery, $activeCompanyId);
        $todayRec = $todayQuery
            ->where('user_id', $user->id)
            ->whereDate('work_date', $now->toDateString())
            ->first();
        $todayNet = $this->netProductionMinutes(
            $todayRec?->check_in_at,
            $todayRec?->check_out_at,
            (int) ($todayRec?->break_minutes ?? 0),
            true,
        );
        $todayHours = $todayNet !== null ? round($todayNet / 60, 2) : 0.0;

        $monthOt = $this->sumOvertimeMinutes($user->id, $monthStart, $now, $activeCompanyId);

        return response()->json([
            'success' => true,
            'data' => [
                'todayHours' => $todayHours,
                'todayTarget' => 9,
                'weekHours' => $weekHours,
                'weekTarget' => 40,
                'monthHours' => $monthHours,
                'monthTarget' => 98,
                'monthOvertimeHours' => round($monthOt / 60, 2),
                'monthOvertimeTarget' => 28,
            ],
        ]);
    }

    private function sumProductionMinutes(int $userId, Carbon $rangeStart, Carbon $rangeEnd, ?int $companyId): int
    {
        $total = 0;
        $todayYmd = Carbon::now($this->tz())->toDateString();

        $recordsQuery = AttendanceRecord::query();
        $this->applyTenantScope($recordsQuery, $companyId);
        $records = $recordsQuery
            ->where('user_id', $userId)
            ->whereBetween('work_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->get();

        foreach ($records as $rec) {
            $isToday = $rec->work_date->toDateString() === $todayYmd;
            $net = $this->netProductionMinutes(
                $rec->check_in_at,
                $rec->check_out_at,
                (int) $rec->break_minutes,
                $isToday,
            );
            if ($net !== null) {
                $total += $net;
            }
        }

        return $total;
    }

    private function sumOvertimeMinutes(int $userId, Carbon $rangeStart, Carbon $rangeEnd, ?int $companyId): int
    {
        $total = 0;
        $todayYmd = Carbon::now($this->tz())->toDateString();

        $recordsQuery = AttendanceRecord::query();
        $this->applyTenantScope($recordsQuery, $companyId);
        $records = $recordsQuery
            ->where('user_id', $userId)
            ->whereBetween('work_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->get();

        foreach ($records as $rec) {
            $isToday = $rec->work_date->toDateString() === $todayYmd;
            $net = $this->netProductionMinutes(
                $rec->check_in_at,
                $rec->check_out_at,
                (int) $rec->break_minutes,
                $isToday,
            );
            if ($net === null) {
                continue;
            }
            $total += max(0, $net - self::OVERTIME_THRESHOLD_MINUTES);
        }

        return $total;
    }

    public function punch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $lat = round((float) $validated['latitude'], 7);
        $lng = round((float) $validated['longitude'], 7);

        $user = $request->user();
        $todayYmd = Carbon::now($this->tz())->toDateString();
        $now = Carbon::now($this->tz());
        $activeCompanyId = $this->activeCompanyId($request);

        // Use whereDate + create instead of firstOrCreate: date column matching is unreliable
        // across drivers when the lookup attributes are normalized differently than stored values.
        $recQuery = AttendanceRecord::query();
        $this->applyTenantScope($recQuery, $activeCompanyId);
        $rec = $recQuery
            ->where('user_id', $user->id)
            ->whereDate('work_date', $todayYmd)
            ->first();

        if (! $rec) {
            $rec = AttendanceRecord::query()->create([
                'company_id' => $activeCompanyId,
                'user_id' => $user->id,
                'work_date' => $todayYmd,
                'status' => 'present',
                'correction_status' => 'none',
                'break_minutes' => 0,
                'late_minutes' => 0,
            ]);
        }

        if (! $rec->check_in_at) {
            $rec->check_in_at = $now;
            $rec->check_in_latitude = $lat;
            $rec->check_in_longitude = $lng;
            $expected = $this->expectedCheckIn($todayYmd);
            if ($now->greaterThan($expected)) {
                $rec->late_minutes = (int) $expected->diffInMinutes($now);
            } else {
                $rec->late_minutes = 0;
            }
            $rec->status = 'present';
            $rec->save();

            return response()->json([
                'success' => true,
                'data' => ['action' => 'in', 'message' => 'Punch in recorded.'],
            ]);
        }

        if (! $rec->check_out_at) {
            if ($rec->break_started_at) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'BREAK_IN_PROGRESS',
                        'message' => 'Please end break before punch out.',
                    ],
                ], 422);
            }
            $rec->check_out_at = $now;
            $rec->check_out_latitude = $lat;
            $rec->check_out_longitude = $lng;
            $net = $this->netProductionMinutes(
                $rec->check_in_at,
                $rec->check_out_at,
                (int) $rec->break_minutes,
                false
            );
            $needsReview = $net !== null && $net < self::EARLY_PUNCH_OUT_REVIEW_MINUTES;
            $rec->status = $needsReview ? 'needs_review' : 'present';
            $rec->correction_status = 'none';
            $rec->correction_reason = null;
            $rec->correction_requested_at = null;
            $rec->corrected_by_user_id = null;
            $rec->corrected_at = null;
            $rec->save();

            return response()->json([
                'success' => true,
                'data' => [
                    'action' => 'out',
                    'needsReview' => $needsReview,
                    'message' => $needsReview
                        ? 'Punch out recorded and marked as Needs Review.'
                        : 'Punch out recorded.',
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'ATTENDANCE_ALREADY_COMPLETE',
                'message' => 'Check-in and check-out already recorded for today.',
            ],
        ], 422);
    }

    public function toggleBreak(Request $request): JsonResponse
    {
        $user = $request->user();
        $todayYmd = Carbon::now($this->tz())->toDateString();
        $now = Carbon::now($this->tz());
        $activeCompanyId = $this->activeCompanyId($request);

        $recQuery = AttendanceRecord::query();
        $this->applyTenantScope($recQuery, $activeCompanyId);
        $rec = $recQuery
            ->where('user_id', $user->id)
            ->whereDate('work_date', $todayYmd)
            ->first();

        if (! $rec || ! $rec->check_in_at) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ATTENDANCE_NOT_STARTED',
                    'message' => 'Punch in before starting break.',
                ],
            ], 422);
        }
        if ($rec->check_out_at) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ATTENDANCE_ALREADY_COMPLETE',
                    'message' => 'Cannot update break after punch out.',
                ],
            ], 422);
        }

        if (! $rec->break_started_at) {
            $rec->break_started_at = $now;
            $rec->save();

            return response()->json([
                'success' => true,
                'data' => [
                    'action' => 'break_start',
                    'breakMinutes' => (int) $rec->break_minutes,
                ],
            ]);
        }

        $delta = (int) $rec->break_started_at->diffInMinutes($now);
        $rec->break_minutes = max(0, (int) $rec->break_minutes + $delta);
        $rec->break_started_at = null;
        $rec->save();

        return response()->json([
            'success' => true,
            'data' => [
                'action' => 'break_end',
                'breakMinutes' => (int) $rec->break_minutes,
            ],
        ]);
    }

    public function requestCorrection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workDate' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $user = $request->user();
        $tz = $this->tz();
        $workDate = Carbon::parse($validated['workDate'], $tz)->toDateString();
        $recQuery = AttendanceRecord::query();
        $this->applyTenantScope($recQuery, $this->activeCompanyId($request));
        $rec = $recQuery
            ->where('user_id', $user->id)
            ->whereDate('work_date', $workDate)
            ->first();

        if (! $rec) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ATTENDANCE_NOT_FOUND',
                    'message' => 'Attendance record not found for selected date.',
                ],
            ], 404);
        }

        if (! $rec->check_in_at || ! $rec->check_out_at) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ATTENDANCE_NOT_COMPLETE',
                    'message' => 'Correction can be requested after check-out is recorded.',
                ],
            ], 422);
        }

        $rec->correction_status = 'requested';
        $rec->correction_reason = trim((string) $validated['reason']);
        $rec->correction_requested_at = Carbon::now($tz);
        $rec->corrected_by_user_id = null;
        $rec->corrected_at = null;
        $rec->save();

        return response()->json([
            'success' => true,
            'data' => [
                'correctionStatus' => $rec->correction_status,
            ],
        ]);
    }

    private function timesheetWorkedMinutesSql(): string
    {
        return DB::connection()->getDriverName() === 'mysql'
            ? '(CASE WHEN attendance_records.check_in_at IS NOT NULL AND attendance_records.check_out_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, attendance_records.check_in_at, attendance_records.check_out_at) - COALESCE(attendance_records.break_minutes, 0) ELSE -1 END)'
            : '(CASE WHEN attendance_records.check_in_at IS NOT NULL AND attendance_records.check_out_at IS NOT NULL THEN (strftime(\'%s\', attendance_records.check_out_at) - strftime(\'%s\', attendance_records.check_in_at)) / 60.0 - COALESCE(attendance_records.break_minutes, 0) ELSE -1 END)';
    }

    private function timesheetProjectLabelSql(): string
    {
        return DB::connection()->getDriverName() === 'mysql'
            ? "TRIM(CONCAT(COALESCE(employee_profiles.team, 'General'), ' Ops'))"
            : "trim(COALESCE(employee_profiles.team, 'General') || ' Ops')";
    }

    private function timesheetProjectFilterSql(): string
    {
        return DB::connection()->getDriverName() === 'mysql'
            ? "LOWER(CONCAT(COALESCE(employee_profiles.team, 'General'), ' ops'))"
            : "lower(trim(COALESCE(employee_profiles.team, 'General') || ' ops'))";
    }

    public function timesheetsIndex(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date'],
            'project' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', 'in:employee_asc,employee_desc,date_desc,date_asc,worked_desc,worked_asc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $tz = $this->tz();
        $dateTo = $validated['dateTo'] ?? Carbon::now($tz)->toDateString();
        $dateFrom = $validated['dateFrom'] ?? Carbon::parse($dateTo, $tz)->subDays(29)->toDateString();
        $projectFilter = strtolower(trim((string) ($validated['project'] ?? '')));
        $sort = $validated['sort'] ?? 'date_desc';
        $perPage = min(200, (int) ($validated['perPage'] ?? 50));

        $projectLabelExpr = $this->timesheetProjectLabelSql();
        $activeCompanyId = $this->activeCompanyId($request);
        $projects = AttendanceRecord::query()
            ->join('users', 'users.id', '=', 'attendance_records.user_id')
            ->leftJoin('employee_profiles', 'employee_profiles.user_id', '=', 'users.id')
            ->whereBetween('attendance_records.work_date', [$dateFrom, $dateTo])
            ->when($activeCompanyId, function ($q, $cid): void {
                $q->where(function ($inner) use ($cid): void {
                    $inner->where('attendance_records.company_id', $cid)->orWhereNull('attendance_records.company_id');
                });
            })
            ->selectRaw('DISTINCT '.$projectLabelExpr.' as project')
            ->orderBy('project')
            ->pluck('project')
            ->values();

        $workedExpr = $this->timesheetWorkedMinutesSql();

        $query = AttendanceRecord::query()
            ->join('users', 'users.id', '=', 'attendance_records.user_id')
            ->leftJoin('employee_profiles', 'employee_profiles.user_id', '=', 'users.id')
            ->whereBetween('attendance_records.work_date', [$dateFrom, $dateTo])
            ->when($activeCompanyId, function ($q, $cid): void {
                $q->where(function ($inner) use ($cid): void {
                    $inner->where('attendance_records.company_id', $cid)->orWhereNull('attendance_records.company_id');
                });
            })
            ->select('attendance_records.*');

        if ($projectFilter !== '') {
            $query->whereRaw($this->timesheetProjectFilterSql().' LIKE ?', ['%'.$projectFilter.'%']);
        }

        match ($sort) {
            'employee_asc' => $query->orderBy('users.name')->orderByDesc('attendance_records.work_date')->orderBy('attendance_records.id'),
            'employee_desc' => $query->orderByDesc('users.name')->orderByDesc('attendance_records.work_date')->orderBy('attendance_records.id'),
            'date_asc' => $query->orderBy('attendance_records.work_date')->orderBy('users.name')->orderBy('attendance_records.id'),
            'worked_desc' => $query->orderByRaw($workedExpr.' DESC')->orderByDesc('attendance_records.work_date')->orderBy('users.name'),
            'worked_asc' => $query->orderByRaw($workedExpr.' ASC')->orderByDesc('attendance_records.work_date')->orderBy('users.name'),
            default => $query->orderByDesc('attendance_records.work_date')->orderBy('users.name')->orderBy('attendance_records.id'),
        };

        $paginator = $query
            ->with(['user:id,name', 'user.employeeProfile:id,user_id,team'])
            ->paginate($perPage);

        $mapped = $paginator->getCollection()->map(function (AttendanceRecord $rec) use ($tz) {
            $user = $rec->user;
            $team = (string) ($user?->employeeProfile?->team ?: 'General');
            $project = $team.' Ops';
            $net = $this->netProductionMinutes(
                $rec->check_in_at,
                $rec->check_out_at,
                (int) $rec->break_minutes,
                false
            );
            $worked = $net !== null ? round($net / 60, 2) : 0.0;

            return [
                'employeeName' => (string) ($user?->name ?: 'Unknown'),
                'date' => $rec->work_date->toDateString(),
                'dateLabel' => $rec->work_date->copy()->timezone($tz)->format('d M Y'),
                'project' => $project,
                'assignedHours' => 8.0,
                'workedHours' => $worked,
            ];
        });
        $paginator->setCollection($mapped);

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'projects' => $projects,
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'totalPages' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function scheduleTimingIndex(Request $request): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', 'in:name_asc,name_desc,start_asc,start_desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $searchRaw = trim((string) ($validated['search'] ?? ''));
        $sort = $validated['sort'] ?? 'name_asc';
        $perPage = min(100, (int) ($validated['perPage'] ?? 50));
        $tz = $this->tz();
        $since = Carbon::now($tz)->subDays(30)->toDateString();
        $activeCompanyId = $this->activeCompanyId($request);

        $usersQuery = User::query()->with(['employeeProfile:id,user_id,designation']);
        if ($activeCompanyId) {
            $usersQuery->whereHas('employeeProfile', function ($query) use ($activeCompanyId): void {
                $query->where(function ($inner) use ($activeCompanyId): void {
                    $inner->where('company_id', $activeCompanyId)->orWhereNull('company_id');
                });
            });
        }
        if ($searchRaw !== '') {
            $usersQuery->where(function ($q) use ($searchRaw) {
                $q->where('name', 'like', '%'.$searchRaw.'%')
                    ->orWhereHas('employeeProfile', fn ($p) => $p->where('designation', 'like', '%'.$searchRaw.'%'));
            });
        }

        if ($sort === 'name_desc') {
            $usersQuery->orderByDesc('name')->orderBy('id');
        } else {
            $usersQuery->orderBy('name')->orderBy('id');
        }

        $paginator = $usersQuery->paginate($perPage);
        $users = collect($paginator->items());

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
            $recs = $records->get($u->id) ?? collect();
            $startMinutes = [];
            $endMinutes = [];
            foreach ($recs as $rec) {
                $ci = $rec->check_in_at ? $rec->check_in_at->copy()->timezone($tz) : null;
                $co = $rec->check_out_at ? $rec->check_out_at->copy()->timezone($tz) : null;
                if ($ci && $co) {
                    $startMinutes[] = ((int) $ci->format('H')) * 60 + ((int) $ci->format('i'));
                    $endMinutes[] = ((int) $co->format('H')) * 60 + ((int) $co->format('i'));
                }
            }

            $defaultStart = 9 * 60;
            $defaultEnd = 18 * 60;
            $avgStart = count($startMinutes) ? (int) round(array_sum($startMinutes) / count($startMinutes)) : $defaultStart;
            $avgEnd = count($endMinutes) ? (int) round(array_sum($endMinutes) / count($endMinutes)) : $defaultEnd;

            $source = 'auto';
            if ($overrides->has($u->id)) {
                $ov = $overrides->get($u->id);
                $avgStart = ((int) substr((string) $ov->start_time, 0, 2)) * 60 + ((int) substr((string) $ov->start_time, 3, 2));
                $avgEnd = ((int) substr((string) $ov->end_time, 0, 2)) * 60 + ((int) substr((string) $ov->end_time, 3, 2));
                $source = (string) ($ov->source ?: 'manual');
            }
            $slot = sprintf('%02d:%02d - %02d:%02d', intdiv($avgStart, 60), $avgStart % 60, intdiv($avgEnd, 60), $avgEnd % 60);
            $designation = (string) ($u->employeeProfile?->designation ?: 'Employee');
            $name = (string) $u->name;

            $ov = $overrides->get($u->id);

            return [
                'userId' => $u->id,
                'name' => $name,
                'jobTitle' => $designation,
                'availableTimings' => $slot,
                'startMinutes' => $avgStart,
                'endMinutes' => $avgEnd,
                'source' => $source,
                'shiftId' => $ov?->hcm_shift_id,
                'shiftName' => $ov?->shift?->name,
            ];
        })->values();

        if ($sort === 'start_asc') {
            $rows = $rows->sortBy('startMinutes')->values();
        } elseif ($sort === 'start_desc') {
            $rows = $rows->sortByDesc('startMinutes')->values();
        }

        return response()->json([
            'success' => true,
            'data' => $rows->all(),
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

    public function scheduleTimingUpsert(Request $request, int $userId): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        $validated = $request->validate([
            'shiftId' => ['nullable', 'integer', 'exists:hcm_shifts,id'],
            'startTime' => ['required_without:shiftId', 'nullable', 'date_format:H:i'],
            'endTime' => ['required_without:shiftId', 'nullable', 'date_format:H:i'],
        ]);

        $activeCompanyId = $this->activeCompanyId($request);

        $shiftId = isset($validated['shiftId']) ? (int) $validated['shiftId'] : null;
        $startStr = null;
        $endStr = null;
        $hcmShiftId = null;

        if ($shiftId) {
            $shiftQuery = HcmShift::query()->whereKey($shiftId)->where('is_active', true);
            $this->applyTenantScope($shiftQuery, $activeCompanyId);
            $shift = $shiftQuery->first();
            if (! $shift) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Shift not found or inactive.',
                    ],
                ], 422);
            }
            $startStr = Carbon::parse($shift->start_time)->format('H:i');
            $endStr = Carbon::parse($shift->end_time)->format('H:i');
            $hcmShiftId = $shift->id;
        } else {
            $startStr = $validated['startTime'];
            $endStr = $validated['endTime'];
        }

        if ($endStr <= $startStr) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'endTime must be after startTime.',
                ],
            ], 422);
        }

        User::query()->findOrFail($userId);
        $row = HcmScheduleTiming::query()->updateOrCreate(
            ['user_id' => $userId],
            [
            'company_id' => $activeCompanyId,
                'hcm_shift_id' => $hcmShiftId,
                'start_time' => $startStr,
                'end_time' => $endStr,
                'source' => 'manual',
                'updated_by_user_id' => $request->user()?->id,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $row->id,
            ],
        ]);
    }

    public function scheduleTimingDestroy(Request $request, int $userId): JsonResponse
    {
        $forbidden = $this->ensureHcmAdmin($request);
        if ($forbidden) {
            return $forbidden;
        }

        User::query()->findOrFail($userId);
        $deleteQuery = HcmScheduleTiming::query()->where('user_id', $userId);
        $this->applyTenantScope($deleteQuery, $this->activeCompanyId($request));
        $deleteQuery->delete();

        return response()->json(['success' => true]);
    }
}
