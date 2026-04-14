<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\EmployeeProfile;
use App\Models\HcmLeaveTypeSetting;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\HcmPromotion;
use App\Models\HcmResignation;
use App\Models\HcmTermination;
use App\Models\HcmTraining;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PerformanceReview;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HcmDashboardController extends Controller
{
    use EnsuresHcmAdmin;

    private const EMPLOYEE_TARGET_DAILY_MINUTES = 9 * 60;

    public function summary(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $today = Carbon::today('Asia/Jakarta');
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        $activeProfiles = EmployeeProfile::query()
            ->whereIn('employment_status', ['active', 'probation'])
            ->get(['id', 'user_id', 'employment_status', 'contract_type', 'contract_end_date', 'hire_date']);

        $activeEmployeeCount = $activeProfiles->count();
        $activeUserIds = $activeProfiles->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->values();

        $probationCount = $activeProfiles->filter(function (EmployeeProfile $profile): bool {
            return strtolower((string) $profile->employment_status) === 'probation';
        })->count();

        $pkwtDue30 = $activeProfiles->filter(function (EmployeeProfile $profile) use ($today): bool {
            $contractType = strtolower((string) ($profile->contract_type ?? ''));
            if (! in_array($contractType, ['contract', 'pkwt'], true)) {
                return false;
            }
            if (! $profile->contract_end_date) {
                return false;
            }
            $days = $today->diffInDays(Carbon::parse($profile->contract_end_date), false);
            return $days >= 0 && $days <= 30;
        })->count();

        $attendanceToday = AttendanceRecord::query()
            ->whereDate('work_date', $today->toDateString())
            ->when($activeUserIds->isNotEmpty(), fn ($q) => $q->whereIn('user_id', $activeUserIds->all()))
            ->get(['user_id', 'check_in_at', 'late_minutes']);

        $presentToday = $attendanceToday->filter(fn (AttendanceRecord $rec): bool => $rec->check_in_at !== null)->count();
        $lateToday = $attendanceToday->filter(fn (AttendanceRecord $rec): bool => (int) ($rec->late_minutes ?? 0) > 0 && $rec->check_in_at !== null)->count();
        $noCheckInToday = max(0, $activeEmployeeCount - $presentToday);

        $pendingLeave = LeaveRequest::query()->where('status', 'pending')->count();
        $pendingOvertime = OvertimeRequest::query()->where('status', 'pending')->count();
        $pendingResignationOrTermination = HcmResignation::query()->where('status', 'pending')->count()
            + HcmTermination::query()->where('status', 'pending')->count();
        $pendingPromotionReview = PerformanceReview::query()->whereIn('status', ['submitted', 'manager_reviewed'])->count();

        $activePeriod = HcmPayrollPeriod::query()
            ->where('status', HcmPayrollPeriod::STATUS_OPEN)
            ->where(function ($q) use ($today): void {
                $q->where('period_year', '<', $today->year)
                    ->orWhere(function ($q2) use ($today): void {
                        $q2->where('period_year', $today->year)
                            ->where('period_month', '<=', $today->month);
                    });
            })
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->first();

        if (! $activePeriod) {
            $activePeriod = HcmPayrollPeriod::query()->orderByDesc('period_year')->orderByDesc('period_month')->first();
        }

        $latestRun = null;
        $runEmployeeCount = 0;
        $runPaidCount = 0;
        $runUnpaidCount = 0;
        $runDraftCount = 0;
        $runLineCount = 0;
        $runPaymentStatus = 'unpaid';

        if ($activePeriod) {
            $latestRun = HcmPayrollRun::query()
                ->where('hcm_payroll_period_id', $activePeriod->id)
                ->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY)
                ->orderByDesc('id')
                ->first();

            if ($latestRun) {
                $runLines = HcmPayrollLine::query()
                    ->where('hcm_payroll_run_id', $latestRun->id)
                    ->get(['user_id', 'meta']);

                $runLineCount = $runLines->count();
                $paymentByUser = [];
                foreach ($runLines as $line) {
                    $userId = (int) ($line->user_id ?? 0);
                    if ($userId <= 0) {
                        continue;
                    }
                    $meta = is_array($line->meta) ? $line->meta : [];
                    $state = strtolower((string) ($meta['paymentStatus'] ?? 'unpaid'));
                    if (! isset($paymentByUser[$userId])) {
                        $paymentByUser[$userId] = 'unpaid';
                    }
                    if ($state === 'paid') {
                        $paymentByUser[$userId] = 'paid';
                    }
                }

                $runEmployeeCount = count($paymentByUser);
                $runPaidCount = count(array_filter($paymentByUser, fn ($state) => $state === 'paid'));
                $runUnpaidCount = max(0, $runEmployeeCount - $runPaidCount);
                $runDraftCount = $latestRun->status === HcmPayrollRun::STATUS_DRAFT ? $runEmployeeCount : 0;

                if ($runEmployeeCount === 0 || $runPaidCount === 0) {
                    $runPaymentStatus = 'unpaid';
                } elseif ($runPaidCount < $runEmployeeCount) {
                    $runPaymentStatus = 'partial';
                } else {
                    $runPaymentStatus = 'paid';
                }
            }
        }

        $joinerThisMonth = EmployeeProfile::query()
            ->whereBetween('hire_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $resignationThisMonth = HcmResignation::query()
            ->whereBetween('resignation_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $promotionThisMonth = HcmPromotion::query()
            ->whereBetween('promotion_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $overtimeMinutesThisMonth = (int) OvertimeRequest::query()
            ->where('status', 'approved')
            ->whereBetween('work_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('minutes');

        $attendanceAnomalyMissingCheckIn = AttendanceRecord::query()
            ->whereDate('work_date', $today->toDateString())
            ->when($activeUserIds->isNotEmpty(), fn ($q) => $q->whereIn('user_id', $activeUserIds->all()))
            ->whereNull('check_in_at')
            ->count();

        $attendanceAnomalyDoubleShift = OvertimeRequest::query()
            ->where('status', 'approved')
            ->whereDate('work_date', $today->toDateString())
            ->where('minutes', '>=', 480)
            ->distinct('user_id')
            ->count('user_id');

        return response()->json([
            'success' => true,
            'data' => [
                'executive' => [
                    'activeEmployees' => $activeEmployeeCount,
                    'probationEmployees' => $probationCount,
                    'pkwtDueIn30Days' => $pkwtDue30,
                    'attendanceToday' => [
                        'present' => $presentToday,
                        'late' => $lateToday,
                        'noCheckIn' => $noCheckInToday,
                    ],
                    'pendingLeaveRequests' => $pendingLeave,
                    'payrollActiveMonth' => [
                        'draft' => $runDraftCount,
                        'paid' => $runPaidCount,
                        'unpaid' => $runUnpaidCount,
                    ],
                ],
                'payrollCommandCenter' => [
                    'periodId' => $activePeriod?->id,
                    'periodYear' => $activePeriod?->period_year,
                    'periodMonth' => $activePeriod?->period_month,
                    'periodStatus' => $activePeriod?->status ?? '—',
                    'latestRunStatus' => $latestRun?->status ?? '—',
                    'latestRunPaymentStatus' => $runPaymentStatus,
                    'employeeLineCount' => $runLineCount,
                ],
                'approvalInbox' => [
                    'pendingLeaveRequest' => $pendingLeave,
                    'pendingOvertimeRequest' => $pendingOvertime,
                    'pendingResignationOrTermination' => $pendingResignationOrTermination,
                    'pendingPromotionReview' => $pendingPromotionReview,
                ],
                'workforceAndAlerts' => [
                    'joinerThisMonth' => $joinerThisMonth,
                    'resignationThisMonth' => $resignationThisMonth,
                    'promotionThisMonth' => $promotionThisMonth,
                    'overtimeTotalMinutesThisMonth' => $overtimeMinutesThisMonth,
                    'attendanceAnomaly' => [
                        'clockInMissing' => $attendanceAnomalyMissingCheckIn,
                        'doubleShift' => $attendanceAnomalyDoubleShift,
                    ],
                ],
            ],
        ]);
    }

    public function employeeSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'AUTH_UNAUTHORIZED',
                    'message' => 'Unauthorized.',
                ],
            ], 401);
        }

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $now = Carbon::now('Asia/Jakarta');
        $today = $now->copy()->startOfDay();
        $referenceDate = ! empty($validated['date'] ?? null)
            ? Carbon::parse((string) $validated['date'], 'Asia/Jakarta')->startOfDay()
            : $today->copy();
        $isCurrentDay = $referenceDate->toDateString() === $today->toDateString();
        $rangeEnd = $isCurrentDay ? $now->copy() : $referenceDate->copy()->endOfDay();
        $weekStart = $referenceDate->copy()->startOfWeek();
        $monthStart = $referenceDate->copy()->startOfMonth();
        $monthEnd = $referenceDate->copy()->endOfMonth();

        $user->loadMissing([
            'employeeProfile' => function ($q) {
                $q->select('id', 'user_id', 'team', 'designation', 'department_id', 'phone', 'hire_date', 'date_of_birth', 'profile_photo_path')
                    ->with(['department:id,name', 'designationRef:id,name']);
            },
        ]);
        $profile = $user->employeeProfile;

        $todayRecord = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $referenceDate->toDateString())
            ->first();

        $todayProductiveMinutes = $this->productiveMinutes(
            $todayRecord?->check_in_at,
            $todayRecord?->check_out_at,
            (int) ($todayRecord?->break_minutes ?? 0),
            $isCurrentDay,
        );
        $todayProgress = $todayProductiveMinutes === null
            ? 0
            : (int) min(100, round(($todayProductiveMinutes / self::EMPLOYEE_TARGET_DAILY_MINUTES) * 100));

        $weekProductiveMinutes = $this->sumProductiveMinutes($user->id, $weekStart, $rangeEnd);
        $monthProductiveMinutes = $this->sumProductiveMinutes($user->id, $monthStart, $rangeEnd);

        $monthOvertimeMinutes = (int) OvertimeRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('minutes');

        $leaveQuery = LeaveRequest::query()->where('user_id', $user->id);
        $leavePending = (clone $leaveQuery)->where('status', 'pending')->count();
        $leaveApproved = (clone $leaveQuery)->where('status', 'approved')->count();
        $leaveDeclined = (clone $leaveQuery)->where('status', 'declined')->count();
        $leaveTotal = (clone $leaveQuery)->count();

        $otQuery = OvertimeRequest::query()->where('user_id', $user->id);
        $otPending = (clone $otQuery)->where('status', 'pending')->count();
        $otApprovedMonth = (clone $otQuery)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $latestRun = HcmPayrollRun::query()
            ->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY)
            ->where('status', HcmPayrollRun::STATUS_FINALIZED)
            ->whereHas('lines', fn ($q) => $q->where('user_id', $user->id))
            ->with('period')
            ->orderByDesc('id')
            ->first();

        $latestNetPay = 0.0;
        $latestPaymentStatus = 'unpaid';
        $latestPeriodLabel = 'Belum ada slip payroll';
        if ($latestRun) {
            $latestPeriodLabel = sprintf('%02d/%d', (int) ($latestRun->period?->period_month ?? 0), (int) ($latestRun->period?->period_year ?? 0));

            $lineRows = HcmPayrollLine::query()
                ->where('hcm_payroll_run_id', $latestRun->id)
                ->where('user_id', $user->id)
                ->get(['kind', 'amount', 'meta']);

            $earningTotal = (float) $lineRows->filter(fn (HcmPayrollLine $line) => strtolower((string) $line->kind) === 'earning')->sum('amount');
            $deductionTotal = (float) $lineRows->filter(fn (HcmPayrollLine $line) => strtolower((string) $line->kind) === 'deduction')->sum('amount');
            $latestNetPay = max(0.0, $earningTotal - $deductionTotal);

            $paymentStates = $lineRows->map(function (HcmPayrollLine $line): string {
                $meta = is_array($line->meta) ? $line->meta : [];
                return strtolower((string) ($meta['paymentStatus'] ?? 'unpaid'));
            });

            if ($paymentStates->contains('paid')) {
                $latestPaymentStatus = $paymentStates->every(fn (string $state) => $state === 'paid') ? 'paid' : 'partial';
            }
        }

        $designation = $profile?->designationRef?->name ?: $profile?->designation ?: 'Employee';
        $team = $profile?->department?->name ?: $profile?->team ?: 'General';

        $todayCheckIn = $todayRecord?->check_in_at;
        $todayCheckOut = $todayRecord?->check_out_at;
        $breakMinutes = (int) ($todayRecord?->break_minutes ?? 0);
        $grossMinutes = null;
        if ($todayCheckIn) {
            $spanEnd = $todayCheckOut ?? $now;
            $grossMinutes = max(0, (int) $todayCheckIn->diffInMinutes($spanEnd));
        }

        $punchState = 'none';
        if ($todayCheckIn && ! $todayCheckOut) {
            $punchState = 'in';
        } elseif ($todayCheckIn && $todayCheckOut) {
            $punchState = 'done';
        }

        $needsReview = (string) ($todayRecord?->status ?? '') === 'needs_review';
        $teamMembers = $this->teamMembersPayload($user, $profile);

        return response()->json([
            'success' => true,
            'data' => [
                'profile' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'designation' => $designation,
                    'team' => $team,
                    'phone' => $profile?->phone ?: '-',
                    'joinDate' => optional($profile?->hire_date)?->toDateString(),
                    'reportOffice' => '-',
                    'greeting' => $this->employeeGreeting($now),
                ],
                'attendanceToday' => [
                    'nowLabel' => $isCurrentDay
                        ? $now->format('H:i').', '.$referenceDate->format('d M Y')
                        : $referenceDate->format('d M Y'),
                    'progressPercent' => $todayProgress,
                    'productionHours' => $todayProductiveMinutes !== null ? round($todayProductiveMinutes / 60, 2) : 0,
                    'punchInAt' => $todayCheckIn ? $todayCheckIn->timezone('Asia/Jakarta')->format('H:i') : '-',
                    'punchOutAt' => $todayCheckOut ? $todayCheckOut->timezone('Asia/Jakarta')->format('H:i') : '-',
                    'punchState' => $punchState,
                    'canPunch' => $isCurrentDay,
                    'needsReview' => $needsReview,
                    'summaryTotalWorking' => $this->formatMinutesAsHm($grossMinutes),
                    'summaryProductive' => $this->formatMinutesAsHm($todayProductiveMinutes),
                    'summaryBreak' => $breakMinutes > 0 ? $breakMinutes.'m' : '-',
                    'summaryOvertime' => $this->formatMinutesAsHm(max(0, (int) ($todayProductiveMinutes ?? 0) - self::EMPLOYEE_TARGET_DAILY_MINUTES)),
                    'checkInLatitude' => $todayRecord?->check_in_latitude !== null ? (float) $todayRecord->check_in_latitude : null,
                    'checkInLongitude' => $todayRecord?->check_in_longitude !== null ? (float) $todayRecord->check_in_longitude : null,
                    'checkOutLatitude' => $todayRecord?->check_out_latitude !== null ? (float) $todayRecord->check_out_latitude : null,
                    'checkOutLongitude' => $todayRecord?->check_out_longitude !== null ? (float) $todayRecord->check_out_longitude : null,
                ],
                'attendanceStats' => [
                    'todayHours' => round(($todayProductiveMinutes ?? 0) / 60, 2),
                    'todayTarget' => 9,
                    'weekHours' => round($weekProductiveMinutes / 60, 2),
                    'weekTarget' => 40,
                    'monthHours' => round($monthProductiveMinutes / 60, 2),
                    'monthTarget' => 98,
                    'monthOvertimeHours' => round($monthOvertimeMinutes / 60, 2),
                    'monthOvertimeTarget' => 28,
                ],
                'leave' => [
                    'total' => $leaveTotal,
                    'pending' => $leavePending,
                    'approved' => $leaveApproved,
                    'declined' => $leaveDeclined,
                ],
                'overtime' => [
                    'pending' => $otPending,
                    'approvedThisMonth' => $otApprovedMonth,
                    'approvedHoursThisMonth' => round($monthOvertimeMinutes / 60, 2),
                ],
                'payroll' => [
                    'latestPeriod' => $latestPeriodLabel,
                    'latestRunStatus' => $latestRun?->status ?? '-',
                    'paymentStatus' => $latestPaymentStatus,
                    'latestNetPay' => $latestNetPay,
                ],
                'ui' => [
                    'referenceDate' => $referenceDate->toDateString(),
                    'referenceYear' => $referenceDate->year,
                    'isCurrentDay' => $isCurrentDay,
                ],
                'nextHoliday' => $this->nextHolidayPayload($referenceDate),
                'leavePolicy' => $this->leavePolicyPayload(),
                'teamBirthday' => $this->teamBirthdayPayload($teamMembers, $referenceDate),
                'teamMembers' => $teamMembers,
                'performance' => $this->performancePayload($user),
                'mySkills' => $this->mySkillsPayload($user),
            ],
        ]);
    }

    private function nextHolidayPayload(Carbon $referenceDate): array
    {
        $holiday = Holiday::query()
            ->where('is_active', true)
            ->whereDate('holiday_date', '>=', $referenceDate->toDateString())
            ->orderBy('holiday_date')
            ->first();

        if (! $holiday) {
            return [
                'title' => 'No upcoming holiday',
                'dateLabel' => '-',
                'daysLeft' => null,
            ];
        }

        $daysLeft = $referenceDate->diffInDays($holiday->holiday_date, false);

        return [
            'title' => (string) $holiday->title,
            'dateLabel' => $holiday->holiday_date->format('d M Y'),
            'daysLeft' => $daysLeft,
        ];
    }

    private function leavePolicyPayload(): array
    {
        $updatedAt = HcmLeaveTypeSetting::query()->max('updated_at');
        $updatedLabel = $updatedAt
            ? Carbon::parse((string) $updatedAt, 'Asia/Jakarta')->format('d M Y')
            : '-';

        return [
            'lastUpdated' => $updatedLabel,
        ];
    }

    private function teamMembersPayload(User $user, ?EmployeeProfile $profile): array
    {
        $teamName = $profile?->department?->name ?: $profile?->team;
        $departmentId = $profile?->department_id;

        if (! $teamName && ! $departmentId) {
            return [];
        }

        $query = User::query()
            ->with([
                'employeeProfile:id,user_id,team,designation,department_id,phone,date_of_birth,profile_photo_path',
                'employeeProfile.department:id,name',
                'employeeProfile.designationRef:id,name',
            ])
            ->where('id', '!=', $user->id)
            ->whereHas('employeeProfile', function ($q) use ($teamName, $departmentId): void {
                $q->where(function ($inner) use ($teamName, $departmentId): void {
                    if ($teamName) {
                        $inner->orWhere('team', $teamName);
                    }
                    if ($departmentId) {
                        $inner->orWhere('department_id', $departmentId);
                    }
                });
            })
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'email']);

        return $query->map(function (User $member): array {
            $memberProfile = $member->employeeProfile;
            $designation = $memberProfile?->designationRef?->name ?: $memberProfile?->designation ?: 'Employee';

            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => (string) ($member->email ?? ''),
                'phone' => (string) ($memberProfile?->phone ?? ''),
                'designation' => $designation,
                'photoUrl' => $this->profilePhotoUrl($memberProfile?->profile_photo_path),
                'birthday' => optional($memberProfile?->date_of_birth)?->toDateString(),
            ];
        })->values()->all();
    }

    private function teamBirthdayPayload(array $teamMembers, Carbon $referenceDate): array
    {
        $teamMembers = collect($teamMembers);

        $candidates = $teamMembers
            ->filter(fn (array $row): bool => ! empty($row['birthday']))
            ->map(function (array $row) use ($referenceDate): array {
                $birth = Carbon::parse((string) $row['birthday'], 'Asia/Jakarta');
                $next = Carbon::create(
                    $referenceDate->year,
                    (int) $birth->month,
                    (int) $birth->day,
                    0,
                    0,
                    0,
                    'Asia/Jakarta'
                );
                if ($next->lt($referenceDate->copy()->startOfDay())) {
                    $next->addYear();
                }

                $row['birthdayLabel'] = $next->format('d M');
                $row['birthdayDaysLeft'] = $referenceDate->diffInDays($next, false);
                return $row;
            })
            ->sortBy('birthdayDaysLeft')
            ->values();

        $picked = $candidates->first();
        if (! $picked) {
            return [
                'name' => '-',
                'designation' => '-',
                'birthdayLabel' => '-',
                'daysLeft' => null,
                'photoUrl' => '/build/img/users/user-35.jpg',
            ];
        }

        return [
            'name' => (string) ($picked['name'] ?? '-'),
            'designation' => (string) ($picked['designation'] ?? '-'),
            'birthdayLabel' => (string) ($picked['birthdayLabel'] ?? '-'),
            'daysLeft' => $picked['birthdayDaysLeft'] ?? null,
            'photoUrl' => (string) ($picked['photoUrl'] ?? '/build/img/users/user-35.jpg'),
        ];
    }

    private function performancePayload(User $user): array
    {
        $reviews = PerformanceReview::query()
            ->with('cycle:id,name,period_end')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        if ($reviews->isEmpty()) {
            return [
                'currentPercent' => 0,
                'vsLastPercent' => 0,
                'series' => [],
            ];
        }

        $scores = $reviews->map(function (PerformanceReview $row): array {
            $score = (float) ($row->final_total_score ?? $row->manager_total_score ?? $row->self_total_score ?? 0);
            return [
                'label' => (string) ($row->cycle?->name ?: optional($row->cycle?->period_end)->format('M Y') ?: ('Review #'.$row->id)),
                'score' => round($score, 2),
            ];
        })->values();

        $latest = (float) ($scores->first()['score'] ?? 0);
        $previous = (float) ($scores->get(1)['score'] ?? $latest);

        return [
            'currentPercent' => round($latest, 2),
            'vsLastPercent' => round($latest - $previous, 2),
            'series' => $scores->reverse()->values()->all(),
        ];
    }

    private function mySkillsPayload(User $user): array
    {
        $trainings = HcmTraining::query()
            ->with('type:id,name')
            ->whereHas('participants', fn ($q) => $q->where('users.id', $user->id))
            ->orderByDesc('end_date')
            ->get(['id', 'training_type_id', 'start_date', 'end_date']);

        if ($trainings->isEmpty()) {
            return [];
        }

        $grouped = $trainings->groupBy(function (HcmTraining $row): string {
            return (string) ($row->type?->name ?: 'General Skill');
        });

        return $grouped->map(function ($rows, string $skillName): array {
            /** @var \Illuminate\Support\Collection $rows */
            $latest = $rows->sortByDesc('end_date')->first();
            $count = $rows->count();
            $level = (int) min(95, 45 + ($count * 10));

            return [
                'name' => $skillName,
                'level' => $level,
                'updatedAt' => optional($latest?->end_date)->format('d M Y') ?: '-',
            ];
        })->sortByDesc('level')->take(5)->values()->all();
    }

    private function profilePhotoUrl(?string $path): string
    {
        if (! $path) {
            return '/build/img/users/user-01.jpg';
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        return '/storage/'.$normalized;
    }

    private function productiveMinutes($checkIn, $checkOut, int $breakMinutes, bool $useNowForOpenShift): ?int
    {
        if (! $checkIn) {
            return null;
        }

        $end = $checkOut;
        if (! $end && $useNowForOpenShift) {
            $end = Carbon::now('Asia/Jakarta');
        }
        if (! $end) {
            return null;
        }

        $mins = (int) $checkIn->diffInMinutes($end);
        return max(0, $mins - $breakMinutes);
    }

    private function sumProductiveMinutes(int $userId, Carbon $start, Carbon $end): int
    {
        $todayYmd = Carbon::now('Asia/Jakarta')->toDateString();
        $rows = AttendanceRecord::query()
            ->where('user_id', $userId)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get(['work_date', 'check_in_at', 'check_out_at', 'break_minutes']);

        $total = 0;
        foreach ($rows as $row) {
            $isToday = optional($row->work_date)?->toDateString() === $todayYmd;
            $mins = $this->productiveMinutes($row->check_in_at, $row->check_out_at, (int) ($row->break_minutes ?? 0), $isToday);
            if ($mins !== null) {
                $total += $mins;
            }
        }

        return $total;
    }

    private function employeeGreeting(Carbon $now): string
    {
        $hour = (int) $now->format('G');
        if ($hour < 12) {
            return 'Good Morning';
        }
        if ($hour < 17) {
            return 'Good Afternoon';
        }

        return 'Good Evening';
    }

    private function formatMinutesAsHm(?int $totalMinutes): string
    {
        if ($totalMinutes === null || $totalMinutes < 0) {
            return '-';
        }

        $h = intdiv($totalMinutes, 60);
        $m = $totalMinutes % 60;
        if ($h > 0) {
            return $h.'h '.sprintf('%02d', $m).'m';
        }

        return $m.'m';
    }
}
