<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Models\AttendanceRecord;
use App\Models\CompanySetting;
use App\Services\LocationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceEmployeeController extends BaseAttendanceController
{
    private function profilePhotoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        return '/storage/'.$normalized;
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

    private function weekdayCountInRange(Carbon $start, Carbon $end): int
    {
        $from = $start->copy()->startOfDay();
        $to = $end->copy()->startOfDay();
        if ($from->gt($to)) {
            return 0;
        }

        $count = 0;
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            if ($cursor->isWeekday()) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
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
                'profilePhotoUrl' => $this->profilePhotoUrl($profile?->profile_photo_path),
                'nowLabel' => $now->format('h:i A').', '.$now->format('d M Y'),
                'productionHoursSoFar' => $hoursSoFar,
                'productionProgressPercent' => $progress,
                'productionBadge' => $hoursSoFar.' hrs',
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
                'summaryBreak' => $this->formatMinutesAsHm($breakMin),
                'summaryOvertime' => $otSummaryLabel,
                'checkInLocationName' => $rec?->check_in_location_name ?? (
                    $rec?->check_in_latitude && $rec?->check_in_longitude
                        ? round((float) $rec->check_in_latitude, 4).', '.round((float) $rec->check_in_longitude, 4)
                        : null
                ),
                'checkInLocationAddress' => $rec?->check_in_location_address,
                'checkOutLocationName' => $rec?->check_out_location_name ?? (
                    $rec?->check_out_latitude && $rec?->check_out_longitude
                        ? round((float) $rec->check_out_latitude, 4).', '.round((float) $rec->check_out_longitude, 4)
                        : null
                ),
                'checkOutLocationAddress' => $rec?->check_out_location_address,
                'checkInLatitude' => $rec?->check_in_latitude !== null ? (float) $rec->check_in_latitude : null,
                'checkInLongitude' => $rec?->check_in_longitude !== null ? (float) $rec->check_in_longitude : null,
                'checkOutLatitude' => $rec?->check_out_latitude !== null ? (float) $rec->check_out_latitude : null,
                'checkOutLongitude' => $rec?->check_out_longitude !== null ? (float) $rec->check_out_longitude : null,
            ],
        ]);
    }

    public function meHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $days = (int) ($validated['days'] ?? 30);
        $user = $request->user();
        $tz = $this->tz();
        $activeCompanyId = $this->activeCompanyId($request);
        $end = Carbon::now($tz)->startOfDay();
        $start = $end->copy()->subDays($days - 1);

        $windowDays = (int) (CompanySetting::query()
            ->where('company_id', $activeCompanyId)
            ->where('key', 'attendance_correction_window_days')
            ->value('value') ?? 30);

        $recordsQuery = AttendanceRecord::query();
        $this->applyTenantScope($recordsQuery, $activeCompanyId);
        $records = $recordsQuery
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('work_date')
            ->get();

        $todayYmd = Carbon::now($tz)->toDateString();

        $rows = $records->map(function (AttendanceRecord $rec) use ($todayYmd, $tz, $windowDays) {
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

            $checkInLoc = $rec->check_in_location_name;
            if (! $checkInLoc) {
                $checkInLoc = ($rec->check_in_latitude && $rec->check_in_longitude)
                    ? round((float) $rec->check_in_latitude, 4).', '.round((float) $rec->check_in_longitude, 4)
                    : '-';
            }

            $checkOutLoc = $rec->check_out_location_name;
            if (! $checkOutLoc) {
                $checkOutLoc = ($rec->check_out_latitude && $rec->check_out_longitude)
                    ? round((float) $rec->check_out_latitude, 4).', '.round((float) $rec->check_out_longitude, 4)
                    : '-';
            }

            return [
                'dateLabel' => $rec->work_date->format('d M Y'),
                'workDate' => $rec->work_date->toDateString(),
                'checkIn' => $this->formatTime($checkIn),
                'checkOut' => $this->formatTime($checkOut),
                'checkInLocation' => $checkInLoc,
                'checkOutLocation' => $checkOutLoc,
                'statusLabel' => $statusLabel,
                'statusBadgeClass' => $statusClass,
                'break' => $breakMin > 0 ? $breakMin.' Min' : '-',
                'late' => $lateMin > 0 ? $lateMin.' Min' : '-',
                'overtime' => $otLabel,
                'overtimeBadgeClass' => $otBadge,
                'productionLabel' => $prod['label'],
                'productionBadgeClass' => $prod['badge'],
                'correctionStatus' => (string) ($rec->correction_status ?? 'none'),
                'correctionReason' => (string) ($rec->correction_reason ?? ''),
                'correctionEligible' => $this->isCorrectionEligible($rec, $statusLabel, $windowDays, $tz),
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
        $targetDailyHours = (int) (self::TARGET_DAILY_MINUTES / 60);
        $weekTargetHours = $this->weekdayCountInRange($weekStart->copy(), $weekStart->copy()->endOfWeek()) * $targetDailyHours;
        $monthTargetHours = $this->weekdayCountInRange($monthStart->copy(), $monthStart->copy()->endOfMonth()) * $targetDailyHours;

        return response()->json([
            'success' => true,
            'data' => [
                'todayHours' => $todayHours,
                'todayTarget' => $targetDailyHours,
                'weekHours' => $weekHours,
                'weekTarget' => $weekTargetHours,
                'monthHours' => $monthHours,
                'monthTarget' => $monthTargetHours,
                'monthOvertimeHours' => round($monthOt / 60, 2),
                'monthOvertimeTarget' => 28,
            ],
        ]);
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
        $now = Carbon::now('UTC');
        $activeCompanyId = $this->activeCompanyId($request);

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

            $locationData = LocationService::reverseGeocode($lat, $lng);
            $rec->check_in_location_name = $locationData['name'];
            $rec->check_in_location_address = $locationData['address'];
            $rec->check_in_location_source = $locationData['source'];

            $expected = $this->expectedCheckIn($todayYmd);
            $rec->late_minutes = $now->greaterThan($expected)
                ? (int) $expected->diffInMinutes($now)
                : 0;
            $rec->status = 'present';
            $rec->save();

            return response()->json([
                'success' => true,
                'data' => [
                    'action' => 'in',
                    'message' => 'Punch in recorded.',
                    'location' => $rec->check_in_location_name,
                ],
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

            $locationData = LocationService::reverseGeocode($lat, $lng);
            $rec->check_out_location_name = $locationData['name'];
            $rec->check_out_location_address = $locationData['address'];
            $rec->check_out_location_source = $locationData['source'];

            $net = $this->netProductionMinutes(
                $rec->check_in_at,
                $rec->check_out_at,
                (int) $rec->break_minutes,
                false,
            );
            $needsReview = $net !== null && $net < self::EARLY_PUNCH_OUT_REVIEW_MINUTES;
            $rec->status = $needsReview ? 'needs_review' : 'present';

            if ((string) ($rec->correction_status ?? 'none') === 'none') {
                $rec->correction_status = 'none';
                $rec->correction_reason = null;
                $rec->correction_requested_at = null;
                $rec->corrected_by_user_id = null;
                $rec->corrected_at = null;
            }
            $rec->save();

            return response()->json([
                'success' => true,
                'data' => [
                    'action' => 'out',
                    'needsReview' => $needsReview,
                    'message' => $needsReview
                        ? 'Punch out recorded and marked as Needs Review.'
                        : 'Punch out recorded.',
                    'location' => $rec->check_out_location_name,
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
}
