<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Company;
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
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PerformanceReview;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Exports\TabularExportResponse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HcmDashboardController extends Controller
{
    use ChecksPermissions;

    private const EMPLOYEE_TARGET_DAILY_MINUTES = 8 * 60;

    private function activeCompanyId(Request $request): ?int
    {
        $value = $request->attributes->get('activeCompanyId');

        return is_numeric($value) ? (int) $value : null;
    }

    private function applyAttendanceTenantScope(Builder $query, ?int $companyId): Builder
    {
        if (! $companyId) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($companyId): void {
            $inner->where('company_id', $companyId)->orWhereNull('company_id');
        });
    }

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

    public function exportSummary(Request $request): JsonResponse|StreamedResponse
    {
        $validated = $request->validate([
            'format' => ['nullable', 'string', Rule::in(['xlsx', 'csv'])],
        ]);

        $summaryResponse = $this->summary($request);
        if ($summaryResponse->getStatusCode() !== 200) {
            return $summaryResponse;
        }

        $payload = $summaryResponse->getData(true);
        $data = is_array($payload) ? ($payload['data'] ?? []) : [];

        $executive = is_array($data['executive'] ?? null) ? $data['executive'] : [];
        $attendanceToday = is_array($executive['attendanceToday'] ?? null) ? $executive['attendanceToday'] : [];
        $payrollMonth = is_array($executive['payrollActiveMonth'] ?? null) ? $executive['payrollActiveMonth'] : [];

        $payrollCenter = is_array($data['payrollCommandCenter'] ?? null) ? $data['payrollCommandCenter'] : [];
        $approval = is_array($data['approvalInbox'] ?? null) ? $data['approvalInbox'] : [];

        $signals = is_array($data['workforceAndAlerts'] ?? null) ? $data['workforceAndAlerts'] : [];
        $anomaly = is_array($signals['attendanceAnomaly'] ?? null) ? $signals['attendanceAnomaly'] : [];

        $rows = [
            ['Executive', 'Total Employees', (string) ($executive['totalEmployees'] ?? 0)],
            ['Executive', 'Active Employees', (string) ($executive['activeEmployees'] ?? 0)],
            ['Executive', 'Probation Employees', (string) ($executive['probationEmployees'] ?? 0)],
            ['Executive', 'Inactive Employees', (string) ($executive['inactiveEmployees'] ?? 0)],
            ['Executive', 'PKWT Due In 30 Days', (string) ($executive['pkwtDueIn30Days'] ?? 0)],

            ['Attendance Today', 'Present', (string) ($attendanceToday['present'] ?? 0)],
            ['Attendance Today', 'Late', (string) ($attendanceToday['late'] ?? 0)],
            ['Attendance Today', 'No Check In', (string) ($attendanceToday['noCheckIn'] ?? 0)],

            ['Payroll Active Month', 'Draft', (string) ($payrollMonth['draft'] ?? 0)],
            ['Payroll Active Month', 'Paid', (string) ($payrollMonth['paid'] ?? 0)],
            ['Payroll Active Month', 'Unpaid', (string) ($payrollMonth['unpaid'] ?? 0)],

            ['Payroll Command Center', 'Period Status', (string) ($payrollCenter['periodStatus'] ?? '—')],
            ['Payroll Command Center', 'Latest Run Status', (string) ($payrollCenter['latestRunStatus'] ?? '—')],
            ['Payroll Command Center', 'Latest Run Payment Status', (string) ($payrollCenter['latestRunPaymentStatus'] ?? 'unpaid')],
            ['Payroll Command Center', 'Employee Line Count', (string) ($payrollCenter['employeeLineCount'] ?? 0)],

            ['Approval Inbox', 'Pending Leave Request', (string) ($approval['pendingLeaveRequest'] ?? 0)],
            ['Approval Inbox', 'Pending Overtime Request', (string) ($approval['pendingOvertimeRequest'] ?? 0)],
            ['Approval Inbox', 'Pending Resignation Or Termination', (string) ($approval['pendingResignationOrTermination'] ?? 0)],
            ['Approval Inbox', 'Pending Promotion Review', (string) ($approval['pendingPromotionReview'] ?? 0)],

            ['Workforce & Alerts', 'Joiner This Month', (string) ($signals['joinerThisMonth'] ?? 0)],
            ['Workforce & Alerts', 'Resignation This Month', (string) ($signals['resignationThisMonth'] ?? 0)],
            ['Workforce & Alerts', 'Promotion This Month', (string) ($signals['promotionThisMonth'] ?? 0)],
            ['Workforce & Alerts', 'Overtime Total Minutes This Month', (string) ($signals['overtimeTotalMinutesThisMonth'] ?? 0)],
            ['Workforce & Alerts', 'Attendance Anomaly - Clock In Missing', (string) ($anomaly['clockInMissing'] ?? 0)],
            ['Workforce & Alerts', 'Attendance Anomaly - Double Shift', (string) ($anomaly['doubleShift'] ?? 0)],
        ];

        $format = strtolower((string) ($validated['format'] ?? 'xlsx'));

        return TabularExportResponse::download(
            headers: ['Section', 'Metric', 'Value'],
            rows: $rows,
            filenameBase: 'dashboard-summary-'.now()->format('Ymd_His'),
            format: $format,
            sheetTitle: 'Dashboard Summary'
        );
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
        $activeCompanyId = $this->activeCompanyId($request);
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

        $todayRecordQuery = AttendanceRecord::query();
        $this->applyAttendanceTenantScope($todayRecordQuery, $activeCompanyId);
        $todayRecord = $todayRecordQuery
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

        $weekProductiveMinutes = $this->sumProductiveMinutes($user->id, $weekStart, $rangeEnd, $activeCompanyId);
        $monthProductiveMinutes = $this->sumProductiveMinutes($user->id, $monthStart, $rangeEnd, $activeCompanyId);

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
        $teamMembers = $this->teamMembersPayload($user, $profile, $activeCompanyId);

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
                    'profilePhotoUrl' => $this->profilePhotoUrl($profile?->profile_photo_path),
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
                    'todayTarget' => 8,
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

    private function teamMembersPayload(User $user, ?EmployeeProfile $profile, ?int $companyId = null): array
    {
        $teamName = $profile?->department?->name ?: $profile?->team;
        $departmentId = $profile?->department_id;
        // prefer explicit companyId if provided, otherwise derive from profile
        $companyId = $companyId ?? ($profile?->company_id ?? null);

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
            ->whereHas('employeeProfile', function ($q) use ($teamName, $departmentId, $companyId): void {
                $q->where(function ($inner) use ($teamName, $departmentId): void {
                    if ($teamName) {
                        $inner->orWhere('team', $teamName);
                    }
                    if ($departmentId) {
                        $inner->orWhere('department_id', $departmentId);
                    }
                });

                if ($companyId) {
                    $q->where('company_id', $companyId);
                }
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
            /** @var Collection $rows */
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

    private function sumProductiveMinutes(int $userId, Carbon $start, Carbon $end, ?int $companyId = null): int
    {
        $todayYmd = Carbon::now('Asia/Jakarta')->toDateString();
        $rowsQuery = AttendanceRecord::query();
        $this->applyAttendanceTenantScope($rowsQuery, $companyId);
        $rows = $rowsQuery
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

    public function globalEmployeeMonitor(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json(['success' => false, 'error' => ['code' => 'AUTH_FORBIDDEN', 'message' => 'Forbidden.']], 403);
        }

        $now = Carbon::now('Asia/Jakarta');
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();

        // Aggregate employee counts grouped by company
        $companyCounts = Company::query()
            ->select([
                'companies.id',
                'companies.code',
                'companies.name',
                'companies.status',
            ])
            ->selectRaw('COUNT(ep.id) as total_employees')
            ->selectRaw("SUM(CASE WHEN ep.employment_status IN ('active','probation') THEN 1 ELSE 0 END) as active_employees")
            ->selectRaw("SUM(CASE WHEN ep.employment_status = 'probation' THEN 1 ELSE 0 END) as probation_employees")
            ->selectRaw("SUM(CASE WHEN ep.employment_status IN ('resigned','terminated','inactive') THEN 1 ELSE 0 END) as inactive_employees")
            ->selectRaw('SUM(CASE WHEN ep.hire_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as new_hires_this_month', [$monthStart, $monthEnd])
            ->selectRaw('SUM(CASE WHEN ep.contract_end_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as expiring_contracts_30d', [$now->toDateString(), $now->copy()->addDays(30)->toDateString()])
            ->leftJoin('employee_profiles as ep', 'ep.company_id', '=', 'companies.id')
            ->groupBy('companies.id', 'companies.code', 'companies.name', 'companies.status')
            ->orderBy('companies.name')
            ->get();

        // Global summary
        $totalEmployees = $companyCounts->sum('total_employees');
        $totalActive = $companyCounts->sum('active_employees');
        $totalProbation = $companyCounts->sum('probation_employees');
        $totalInactive = $companyCounts->sum('inactive_employees');
        $totalNewHires = $companyCounts->sum('new_hires_this_month');
        $totalExpiringContracts = $companyCounts->sum('expiring_contracts_30d');
        $totalCompanies = $companyCounts->count();
        $totalActiveCompanies = $companyCounts->where('status', 'active')->count();

        // Global employment_status breakdown
        $statusBreakdown = EmployeeProfile::query()
            ->selectRaw('COALESCE(employment_status, "unknown") as status, COUNT(*) as count')
            ->groupBy('employment_status')
            ->pluck('count', 'status')
            ->toArray();

        // New hires trend (last 6 months)
        $hireTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthRef = $now->copy()->subMonths($i);
            $mStart = $monthRef->copy()->startOfMonth()->toDateString();
            $mEnd = $monthRef->copy()->endOfMonth()->toDateString();
            $count = EmployeeProfile::query()
                ->whereBetween('hire_date', [$mStart, $mEnd])
                ->count();
            $hireTrend[] = [
                'month' => $monthRef->format('M Y'),
                'count' => $count,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_companies' => $totalCompanies,
                    'total_active_companies' => $totalActiveCompanies,
                    'total_employees' => $totalEmployees,
                    'total_active' => $totalActive,
                    'total_probation' => $totalProbation,
                    'total_inactive' => $totalInactive,
                    'new_hires_this_month' => $totalNewHires,
                    'expiring_contracts_30d' => $totalExpiringContracts,
                    'month_label' => $now->format('F Y'),
                ],
                'status_breakdown' => $statusBreakdown,
                'hire_trend' => $hireTrend,
                'companies' => $companyCounts->map(fn ($c) => [
                    'id' => $c->id,
                    'code' => $c->code,
                    'name' => $c->name,
                    'status' => $c->status,
                    'total_employees' => (int) $c->total_employees,
                    'active_employees' => (int) $c->active_employees,
                    'probation_employees' => (int) $c->probation_employees,
                    'inactive_employees' => (int) $c->inactive_employees,
                    'new_hires_this_month' => (int) $c->new_hires_this_month,
                    'expiring_contracts_30d' => (int) $c->expiring_contracts_30d,
                ])->values()->all(),
            ],
        ]);
    }

    public function packageComplianceMonitor(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json(['success' => false, 'error' => ['code' => 'AUTH_FORBIDDEN', 'message' => 'Forbidden.']], 403);
        }

        // Load all active/trial/suspended subscriptions with package features and company
        $subscriptions = Subscription::query()
            ->with(['package.features', 'company'])
            ->whereIn('status', ['active', 'trial', 'suspended'])
            ->get();

        $rows = [];
        $summary = ['violation' => 0, 'warning' => 0, 'compliant' => 0, 'unlimited' => 0, 'total' => 0];

        foreach ($subscriptions as $sub) {
            if (! $sub->company || ! $sub->package) {
                continue;
            }

            $feature = $sub->package->features->firstWhere('feature_code', 'max_employees');
            $limit = $feature ? ($feature->limit === null ? null : (int) $feature->limit) : null;

            $actual = EmployeeProfile::query()
                ->where('company_id', $sub->company_id)
                ->whereNotIn('employment_status', ['terminated'])
                ->count();

            if ($limit === null) {
                $usagePct = 0;
                $status = 'unlimited';
                $summary['unlimited']++;
            } else {
                $usagePct = $limit > 0 ? round(($actual / $limit) * 100, 1) : ($actual > 0 ? 999 : 0);
                if ($actual > $limit) {
                    $status = 'violation';
                    $summary['violation']++;
                } elseif ($limit > 0 && ($actual / $limit) >= 0.80) {
                    $status = 'warning';
                    $summary['warning']++;
                } else {
                    $status = 'compliant';
                    $summary['compliant']++;
                }
            }
            $summary['total']++;

            $rows[] = [
                'company_id' => $sub->company_id,
                'company_name' => $sub->company->name,
                'company_code' => $sub->company->code,
                'company_status' => $sub->company->status,
                'package_name' => $sub->package->name,
                'plan_code' => $sub->plan_code,
                'sub_status' => $sub->status,
                'sub_ends_at' => $sub->ends_at ? $sub->ends_at->toDateString() : null,
                'limit' => $limit,
                'actual' => $actual,
                'excess' => max(0, $actual - ($limit ?? $actual)),
                'usage_pct' => $usagePct,
                'compliance_status' => $status,
            ];
        }

        // Sort: violations first, then warnings, then compliant, then unlimited
        usort($rows, function ($a, $b) {
            $order = ['violation' => 0, 'warning' => 1, 'compliant' => 2, 'unlimited' => 3];

            return ($order[$a['compliance_status']] ?? 9) <=> ($order[$b['compliance_status']] ?? 9);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'tenants' => $rows,
            ],
        ]);
    }

    public function packageComplianceEmployees(Request $request, int $companyId): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json(['success' => false, 'error' => ['code' => 'AUTH_FORBIDDEN', 'message' => 'Forbidden.']], 403);
        }

        $company = Company::query()
            ->with(['owner:id,name'])
            ->find($companyId);

        if (! $company) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'COMPANY_NOT_FOUND',
                    'message' => 'Company not found.',
                ],
            ], 404);
        }

        $subscription = Subscription::query()
            ->with('package.features')
            ->where('company_id', $company->id)
            ->whereIn('status', ['active', 'trial', 'suspended'])
            ->latest('starts_at')
            ->latest('id')
            ->first();

        $limit = null;
        $packageName = null;
        if ($subscription && $subscription->package) {
            $packageName = $subscription->package->name;
            $feature = $subscription->package->features->firstWhere('feature_code', 'max_employees');
            $limit = $feature ? ($feature->limit === null ? null : (int) $feature->limit) : null;
        }

        $profiles = EmployeeProfile::query()
            ->with(['user:id,name'])
            ->where('company_id', $company->id)
            ->whereNotIn('employment_status', ['terminated'])
            ->orderBy('id')
            ->get(['id', 'user_id', 'designation', 'employment_status']);

        $ownerUserId = $company->owner_user_id;
        $ownerName = $company->owner?->name;
        $ownerMasked = $ownerName ? $this->maskDisplayName($ownerName) : null;

        $employees = $profiles->map(function (EmployeeProfile $profile) use ($ownerUserId): array {
            $displayName = $profile->user?->name;

            return [
                'id' => (int) $profile->id,
                'user_id' => $profile->user_id !== null ? (int) $profile->user_id : null,
                'name_masked' => $displayName ? $this->maskDisplayName($displayName) : '***',
                'designation' => $profile->designation,
                'employment_status' => (string) ($profile->employment_status ?? '-'),
                'is_owner' => $ownerUserId !== null && (int) $profile->user_id === (int) $ownerUserId,
            ];
        })->values();

        $stats = [
            'total' => $employees->count(),
            'active' => $profiles->where('employment_status', 'active')->count(),
            'probation' => $profiles->where('employment_status', 'probation')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'company_name' => $company->name,
                'package_name' => $packageName,
                'limit' => $limit,
                'actual' => $employees->count(),
                'owner' => [
                    'user_id' => $ownerUserId !== null ? (int) $ownerUserId : null,
                    'name_masked' => $ownerMasked,
                ],
                'stats' => $stats,
                'employees' => $employees,
            ],
        ]);
    }

    private function maskDisplayName(?string $name): string
    {
        $normalized = trim((string) $name);
        if ($normalized === '') {
            return '***';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($normalized, 0, 1, 'UTF-8').'***';
        }

        return substr($normalized, 0, 1).'***';
    }
}
