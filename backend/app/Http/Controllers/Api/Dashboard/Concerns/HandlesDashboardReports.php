<?php

namespace App\Http\Controllers\Api\Dashboard\Concerns;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\HcmLeaveTypeSetting;
use App\Models\HcmManualActivity;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\HcmPromotion;
use App\Models\HcmResignation;
use App\Models\HcmTermination;
use App\Models\HcmTraining;
use App\Models\Holiday;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Subscription;
use App\Models\OvertimeRequest;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Support\Exports\TabularExportResponse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait HandlesDashboardReports
{
    public function summary(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensurePermission($request, 'dashboard.view')) {
            return $forbidden;
        }

        $today = Carbon::today('Asia/Jakarta');
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $companyId = $this->activeCompanyId($request);


        // --- Patch: Add totalEmployees and inactiveEmployees ---
        $allProfiles = EmployeeProfile::query()
            ->where('company_id', $companyId)
            ->get([
                'id',
                'user_id',
                'department_id',
                'designation_id',
                'designation',
                'team',
                'profile_photo_path',
                'date_of_birth',
                'employment_status',
                'contract_type',
                'contract_end_date',
                'hire_date',
            ]);

        $totalEmployeeCount = $allProfiles->count();
        $inactiveEmployeeCount = $allProfiles->filter(function (EmployeeProfile $profile): bool {
            return strtolower((string) $profile->employment_status) === 'inactive';
        })->count();

        $activeProfiles = $allProfiles->filter(function (EmployeeProfile $profile): bool {
            return in_array(strtolower((string) $profile->employment_status), ['active', 'probation'], true);
        });
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
            ->where('company_id', $companyId)
            ->whereDate('work_date', $today->toDateString())
            ->when($activeUserIds->isNotEmpty(), fn ($q) => $q->whereIn('user_id', $activeUserIds->all()))
            ->get(['user_id', 'check_in_at', 'late_minutes']);

        $presentToday = $attendanceToday->filter(fn (AttendanceRecord $rec): bool => $rec->check_in_at !== null)->count();
        $lateToday = $attendanceToday->filter(fn (AttendanceRecord $rec): bool => (int) ($rec->late_minutes ?? 0) > 0 && $rec->check_in_at !== null)->count();
        $noCheckInToday = max(0, $activeEmployeeCount - $presentToday);

        $pendingLeave = LeaveRequest::query()->where('company_id', $companyId)->where('status', 'pending')->count();
        $pendingOvertime = OvertimeRequest::query()->where('company_id', $companyId)->where('status', 'pending')->count();
        $pendingResignationOrTermination = HcmResignation::query()->where('company_id', $companyId)->where('status', 'pending')->count()
            + HcmTermination::query()->where('company_id', $companyId)->where('status', 'pending')->count();
        $pendingPromotionReview = PerformanceReview::query()->where('company_id', $companyId)->whereIn('status', ['submitted', 'manager_reviewed'])->count();

        $activePeriod = HcmPayrollPeriod::query()
            ->where('company_id', $companyId)
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
            $activePeriod = HcmPayrollPeriod::query()->where('company_id', $companyId)->orderByDesc('period_year')->orderByDesc('period_month')->first();
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
            ->where('company_id', $companyId)
            ->whereBetween('hire_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $resignationThisMonth = HcmResignation::query()
            ->where('company_id', $companyId)
            ->whereBetween('resignation_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $promotionThisMonth = HcmPromotion::query()
            ->where('company_id', $companyId)
            ->whereBetween('promotion_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $overtimeMinutesThisMonth = (int) OvertimeRequest::query()
            ->where('company_id', $companyId)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('minutes');

        $attendanceAnomalyMissingCheckIn = AttendanceRecord::query()
            ->where('company_id', $companyId)
            ->whereDate('work_date', $today->toDateString())
            ->when($activeUserIds->isNotEmpty(), fn ($q) => $q->whereIn('user_id', $activeUserIds->all()))
            ->whereNull('check_in_at')
            ->count();

        $attendanceAnomalyDoubleShift = OvertimeRequest::query()
            ->where('company_id', $companyId)
            ->where('status', 'approved')
            ->whereDate('work_date', $today->toDateString())
            ->where('minutes', '>=', 480)
            ->distinct('user_id')
            ->count('user_id');

        $userDirectory = User::query()
            ->whereIn('id', $activeUserIds->all())
            ->get(['id', 'name'])
            ->keyBy('id');

        $departmentNameById = Department::query()
            ->whereIn('id', $activeProfiles->pluck('department_id')->filter()->unique()->values()->all())
            ->pluck('name', 'id');

        $attendancePercentageBase = max(1, $activeEmployeeCount);
        $attendancePresentPct = (int) round(($presentToday / $attendancePercentageBase) * 100);
        $attendanceLatePct = (int) round(($lateToday / $attendancePercentageBase) * 100);
        $attendancePermissionPct = (int) round(($pendingLeave / $attendancePercentageBase) * 100);
        $attendanceAbsentPct = (int) round(($noCheckInToday / $attendancePercentageBase) * 100);

        $departmentBreakdown = $activeProfiles
            ->map(fn (EmployeeProfile $profile): int => (int) ($profile->department_id ?? 0))
            ->filter(fn (int $departmentId): bool => $departmentId > 0)
            ->countBy()
            ->map(function (int $count, int|string $departmentId) use ($departmentNameById): array {
                $resolvedName = trim((string) ($departmentNameById[(int) $departmentId] ?? ''));

                return [
                    'name' => $resolvedName !== '' ? $resolvedName : 'Unknown Department',
                    'count' => $count,
                ];
            })
            ->values();

        $unassignedDepartmentCount = $activeProfiles
            ->filter(fn (EmployeeProfile $profile): bool => (int) ($profile->department_id ?? 0) <= 0)
            ->count();

        if ($unassignedDepartmentCount > 0) {
            $departmentBreakdown->push([
                'name' => 'Unassigned Department',
                'count' => $unassignedDepartmentCount,
            ]);
        }

        $departmentBreakdown = $departmentBreakdown
            ->sort(function (array $left, array $right): int {
                $countCompare = ((int) ($right['count'] ?? 0)) <=> ((int) ($left['count'] ?? 0));
                if ($countCompare !== 0) {
                    return $countCompare;
                }

                return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
            })
            ->take(6)
            ->values();

        $monthAttendanceRows = AttendanceRecord::query()
            ->where('company_id', $companyId)
            ->whereBetween('work_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->whereNotNull('check_in_at')
            ->when($activeUserIds->isNotEmpty(), fn ($q) => $q->whereIn('user_id', $activeUserIds->all()))
            ->get(['user_id', 'late_minutes']);

        $monthDaySpan = max(1, (int) $today->day);
        $topPerformer = $monthAttendanceRows
            ->groupBy(fn (AttendanceRecord $record) => (int) ($record->user_id ?? 0))
            ->map(function ($rows, int $userId) use ($userDirectory, $activeProfiles): array {
                $presentDays = $rows->count();
                $averageLateMinutes = (float) $rows->avg(fn (AttendanceRecord $record) => (int) ($record->late_minutes ?? 0));
                $profile = $activeProfiles->first(fn (EmployeeProfile $item) => (int) ($item->user_id ?? 0) === $userId);

                return [
                    'userId' => $userId,
                    'name' => (string) ($userDirectory->get($userId)?->name ?? 'Employee'),
                    'role' => trim((string) ($profile?->designation ?? '')) !== ''
                        ? (string) $profile?->designation
                        : 'Team Member',
                    'presentDays' => $presentDays,
                    'averageLateMinutes' => $averageLateMinutes,
                ];
            })
            ->map(function (array $row) use ($monthDaySpan): array {
                $attendanceRate = min(1, $row['presentDays'] / $monthDaySpan);
                $punctualityRate = max(0, 1 - min(1, ((float) $row['averageLateMinutes']) / 30));
                $score = (int) round((($attendanceRate * 0.7) + ($punctualityRate * 0.3)) * 100);
                $row['score'] = max(0, min(100, $score));
                return $row;
            })
            ->sortByDesc('score')
            ->first();

        if (! $topPerformer) {
            $fallbackProfile = $activeProfiles->first();
            $fallbackUserId = (int) ($fallbackProfile?->user_id ?? 0);
            $topPerformer = [
                'userId' => $fallbackUserId,
                'name' => (string) ($userDirectory->get($fallbackUserId)?->name ?? 'Employee'),
                'role' => trim((string) ($fallbackProfile?->designation ?? '')) !== ''
                    ? (string) $fallbackProfile?->designation
                    : 'Team Member',
                'presentDays' => 0,
                'averageLateMinutes' => 0.0,
                'score' => 0,
            ];
        }

        $todayClockRows = AttendanceRecord::query()
            ->where('company_id', $companyId)
            ->whereDate('work_date', $today->toDateString())
            ->whereNotNull('check_in_at')
            ->when($activeUserIds->isNotEmpty(), fn ($q) => $q->whereIn('user_id', $activeUserIds->all()))
            ->orderBy('check_in_at')
            ->get(['user_id', 'check_in_at', 'check_out_at', 'late_minutes', 'break_minutes']);

        $clockInOut = $todayClockRows
            ->take(3)
            ->map(function (AttendanceRecord $record) use ($userDirectory, $activeProfiles): array {
                $userId = (int) ($record->user_id ?? 0);
                $profile = $activeProfiles->first(fn (EmployeeProfile $item) => (int) ($item->user_id ?? 0) === $userId);
                $checkIn = $record->check_in_at ? Carbon::parse($record->check_in_at, 'Asia/Jakarta') : null;
                $checkOut = $record->check_out_at ? Carbon::parse($record->check_out_at, 'Asia/Jakarta') : null;

                $productiveHours = 0.0;
                if ($checkIn && $checkOut && $checkOut->greaterThan($checkIn)) {
                    $minutes = max(0, $checkOut->diffInMinutes($checkIn) - (int) ($record->break_minutes ?? 0));
                    $productiveHours = round($minutes / 60, 2);
                }

                return [
                    'name' => (string) ($userDirectory->get($userId)?->name ?? 'Employee'),
                    'role' => trim((string) ($profile?->designation ?? '')) !== ''
                        ? (string) $profile?->designation
                        : 'Team Member',
                    'checkIn' => $checkIn?->format('H:i') ?? '-',
                    'checkOut' => $checkOut?->format('H:i') ?? '-',
                    'productiveHours' => number_format($productiveHours, 2),
                ];
            })
            ->values();

        $lateEmployees = $todayClockRows
            ->filter(fn (AttendanceRecord $record) => (int) ($record->late_minutes ?? 0) > 0)
            ->sortByDesc(fn (AttendanceRecord $record) => (int) ($record->late_minutes ?? 0))
            ->take(3)
            ->map(function (AttendanceRecord $record) use ($userDirectory, $activeProfiles): array {
                $userId = (int) ($record->user_id ?? 0);
                $profile = $activeProfiles->first(fn (EmployeeProfile $item) => (int) ($item->user_id ?? 0) === $userId);
                $checkIn = $record->check_in_at ? Carbon::parse($record->check_in_at, 'Asia/Jakarta') : null;

                return [
                    'name' => (string) ($userDirectory->get($userId)?->name ?? 'Employee'),
                    'role' => trim((string) ($profile?->designation ?? '')) !== ''
                        ? (string) $profile?->designation
                        : 'Team Member',
                    'lateMinutes' => (int) ($record->late_minutes ?? 0),
                    'checkIn' => $checkIn?->format('H:i') ?? '-',
                ];
            })
            ->values();

        $employeesList = EmployeeProfile::query()
            ->where('company_id', $companyId)
            ->whereIn('employment_status', ['active', 'probation'])
            ->with(['user:id,name', 'department:id,name'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'user_id', 'department_id', 'designation', 'team'])
            ->map(function (EmployeeProfile $profile): array {
                return [
                    'name' => (string) ($profile->user?->name ?? 'Employee'),
                    'designation' => trim((string) ($profile->designation ?? '')) !== ''
                        ? (string) $profile->designation
                        : 'Team Member',
                    'department' => (string) ($profile->department?->name ?? $profile->team ?? 'Unassigned'),
                ];
            })
            ->values();

        $invoices = Invoice::query()
            ->where('company_id', $companyId)
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'invoice_number', 'amount_due', 'is_paid', 'status'])
            ->map(function (Invoice $invoice): array {
                return [
                    'invoiceNumber' => (string) ($invoice->invoice_number ?? '-'),
                    'amountDue' => (float) ($invoice->amount_due ?? 0),
                    'status' => (bool) ($invoice->is_paid ?? false) || strtolower((string) ($invoice->status ?? '')) === 'paid'
                        ? 'paid'
                        : 'unpaid',
                ];
            })
            ->values();

        $recentActivities = HcmManualActivity::query()
            ->where('company_id', $companyId)
            ->with(['creator:id,name'])
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get(['id', 'title', 'activity_kind', 'status', 'updated_at', 'created_by_user_id'])
            ->map(function (HcmManualActivity $activity): array {
                return [
                    'actor' => (string) ($activity->creator?->name ?? 'System'),
                    'title' => (string) ($activity->title ?? 'Activity updated'),
                    'status' => (string) ($activity->status ?? 'updated'),
                    'time' => $activity->updated_at?->timezone('Asia/Jakarta')->format('H:i') ?? '-',
                ];
            })
            ->values();

        if ($recentActivities->isEmpty()) {
            $recentActivities = $todayClockRows
                ->take(6)
                ->map(function (AttendanceRecord $record) use ($userDirectory): array {
                    $userId = (int) ($record->user_id ?? 0);
                    $checkIn = $record->check_in_at ? Carbon::parse($record->check_in_at, 'Asia/Jakarta') : null;

                    return [
                        'actor' => (string) ($userDirectory->get($userId)?->name ?? 'Employee'),
                        'title' => 'Clock-in recorded',
                        'status' => 'attendance',
                        'time' => $checkIn?->format('H:i') ?? '-',
                    ];
                })
                ->values();
        }

        $todayMonthDay = $today->format('m-d');
        $tomorrowMonthDay = $today->copy()->addDay()->format('m-d');
        $birthdayProfiles = $activeProfiles->filter(fn (EmployeeProfile $profile) => $profile->date_of_birth !== null);

        $birthdaysToday = $birthdayProfiles
            ->filter(fn (EmployeeProfile $profile) => Carbon::parse((string) $profile->date_of_birth)->format('m-d') === $todayMonthDay)
            ->take(5)
            ->map(function (EmployeeProfile $profile) use ($userDirectory): array {
                $userId = (int) ($profile->user_id ?? 0);
                return [
                    'name' => (string) ($userDirectory->get($userId)?->name ?? 'Employee'),
                    'role' => trim((string) ($profile->designation ?? '')) !== ''
                        ? (string) $profile->designation
                        : 'Team Member',
                ];
            })
            ->values();

        $birthdaysTomorrow = $birthdayProfiles
            ->filter(fn (EmployeeProfile $profile) => Carbon::parse((string) $profile->date_of_birth)->format('m-d') === $tomorrowMonthDay)
            ->take(5)
            ->map(function (EmployeeProfile $profile) use ($userDirectory): array {
                $userId = (int) ($profile->user_id ?? 0);
                return [
                    'name' => (string) ($userDirectory->get($userId)?->name ?? 'Employee'),
                    'role' => trim((string) ($profile->designation ?? '')) !== ''
                        ? (string) $profile->designation
                        : 'Team Member',
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'executive' => [
                    'activeEmployees' => $activeEmployeeCount,
                    'probationEmployees' => $probationCount,
                    'pkwtDueIn30Days' => $pkwtDue30,
                    'totalEmployees' => $totalEmployeeCount,
                    'inactiveEmployees' => $inactiveEmployeeCount,
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
                'legacyWidgets' => [
                    'attendanceOverview' => [
                        'totalAttendance' => $presentToday,
                        'presentPct' => $attendancePresentPct,
                        'latePct' => $attendanceLatePct,
                        'permissionPct' => $attendancePermissionPct,
                        'absentPct' => $attendanceAbsentPct,
                        'absentTotal' => $noCheckInToday,
                    ],
                    'topPerformer' => $topPerformer,
                    'departmentBreakdown' => $departmentBreakdown,
                    'clockInOut' => $clockInOut,
                    'lateEmployees' => $lateEmployees,
                    'employees' => $employeesList,
                    'invoices' => $invoices,
                    'recentActivities' => $recentActivities,
                    'birthdays' => [
                        'today' => $birthdaysToday,
                        'tomorrow' => $birthdaysTomorrow,
                    ],
                ],
            ],
        ]);
    }
}
