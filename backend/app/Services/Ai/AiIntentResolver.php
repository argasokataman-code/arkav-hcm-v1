<?php

namespace App\Services\Ai;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeeProfile;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollRun;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\PackageFeature;
use App\Models\PlatformRevenueTransaction;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use App\Services\BillingTaxCalculationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Resolves an intent to structured data via direct Eloquent/DB queries.
 *
 * Avoids internal HTTP self-calls (which deadlock on single-threaded php artisan serve
 * and add unnecessary network overhead in production).
 *
 * Every resolver method:
 * 1. Uses the authenticated User and company context (no token forwarding needed).
 * 2. Returns an array with 'data' (for LLM context) and 'source' (for provenance).
 * 3. Returns null if data is unavailable.
 *
 * @phpstan-type ResolverResult array{data: array<string, mixed>, source: array{label: string, endpoint: string, retrieved_at: string}}
 */
class AiIntentResolver
{

    /**
     * Resolve intent to data.
     *
     * @return array{data: array<string, mixed>, source: array<string, string>}|null
     */
    public function resolve(string $intent, User $user, ?int $companyId, string $bearerToken): ?array
    {
        return match ($intent) {
            'leave.balance.self'         => $this->leaveBalanceSelf($user, $companyId),
            'leave.history.self'         => $this->leaveHistorySelf($user, $companyId),
            'attendance.today.self'      => $this->attendanceTodaySelf($user, $companyId),
            'attendance.history.self'    => $this->attendanceHistorySelf($user, $companyId),
            'payslip.latest.self'        => $this->payslipLatestSelf($user, $companyId),
            'payslip.history.self'       => $this->payslipHistorySelf($user, $companyId),
            'ticket.status.self',
            'ticket.list.self'           => $this->ticketListSelf($user, $companyId),
            'profile.info.self'          => $this->profileInfoSelf($user, $companyId),
            'subscription.features.current' => $this->subscriptionFeaturesCurrent($companyId),
            'payroll.run.status',
            'payroll.run.summary'        => $this->payrollRunStatus($companyId),
            'leave.summary.company'      => $this->leaveSummaryCompany($companyId),
            'attendance.summary.company' => $this->attendanceSummaryCompany($companyId),
            'employee.list.company'      => $this->employeeListCompany($companyId),
            'saas.company.summary'       => $this->saasCompanySummary(),
            'saas.billing.summary'       => $this->saasBillingSummary(),
            'saas.tax.monthly'           => $this->saasTaxMonthlySummary(),
            'leave.balance.other'        => $this->leaveBalanceOther($companyId),
            'leave.history.other'        => $this->leaveHistoryOther($companyId),
            'department.info'            => $this->departmentInfo($companyId),
            'ticket.list.all'            => $this->ticketListAll($companyId),
            'general.fallback.global'    => $this->generalFallbackGlobal(),
            'general.fallback.company'   => $this->generalFallbackCompany($companyId),
            default                      => null,
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function now(): string
    {
        return now()->toIso8601String();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Employee self-service resolvers
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function leaveBalanceSelf(User $user, ?int $companyId): ?array
    {
        $balances = EmployeeLeaveBalance::where('employee_id', $user->id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('year', now()->year)
            ->get(['leave_type_id', 'balance', 'used', 'carried_forward'])
            ->toArray();

        if (empty($balances)) {
            return null;
        }

        return [
            'data'   => ['balances' => $balances],
            'source' => [
                'label'        => 'Leave Balance',
                'endpoint'     => 'local:EmployeeLeaveBalance',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function leaveHistorySelf(User $user, ?int $companyId): ?array
    {
        $leaves = LeaveRequest::where('user_id', $user->id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->latest()
            ->limit(10)
            ->get(['leave_type', 'date_from', 'date_to', 'days', 'status', 'notes'])
            ->toArray();

        if (empty($leaves)) {
            return null;
        }

        return [
            'data'   => ['recent_leaves' => $leaves],
            'source' => [
                'label'        => 'Leave History',
                'endpoint'     => 'local:LeaveRequest',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function attendanceTodaySelf(User $user, ?int $companyId): ?array
    {
        $record = AttendanceRecord::where('user_uuid', $user->uuid)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereDate('work_date', today())
            ->first(['work_date', 'status', 'check_in_at', 'check_out_at', 'late_minutes', 'break_minutes']);

        if ($record === null) {
            return [
                'data'   => ['status' => 'no_record', 'date' => today()->toDateString()],
                'source' => [
                    'label'        => 'Attendance Today',
                    'endpoint'     => 'local:AttendanceRecord',
                    'retrieved_at' => $this->now(),
                ],
            ];
        }

        return [
            'data'   => $record->toArray(),
            'source' => [
                'label'        => 'Attendance Today',
                'endpoint'     => 'local:AttendanceRecord',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function attendanceHistorySelf(User $user, ?int $companyId): ?array
    {
        $records = AttendanceRecord::where('user_uuid', $user->uuid)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->latest('work_date')
            ->limit(30)
            ->get(['work_date', 'status', 'check_in_at', 'check_out_at', 'late_minutes'])
            ->toArray();

        if (empty($records)) {
            return null;
        }

        return [
            'data'   => ['history' => $records],
            'source' => [
                'label'        => 'Attendance History',
                'endpoint'     => 'local:AttendanceRecord',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function payslipLatestSelf(User $user, ?int $companyId): ?array
    {
        $run = HcmPayrollRun::when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereIn('status', ['finalized', 'paid'])
            ->latest()
            ->first();

        if ($run === null) {
            return null;
        }

        $lines = HcmPayrollLine::where('user_id', $user->id)
            ->where('hcm_payroll_run_id', $run->id)
            ->get(['component_name', 'kind', 'category', 'amount'])
            ->toArray();

        if (empty($lines)) {
            return null;
        }

        $net = collect($lines)->where('kind', 'earning')->sum('amount')
             - collect($lines)->where('kind', 'deduction')->sum('amount');

        return [
            'data'   => [
                'run_status' => $run->status,
                'period'     => $run->created_at?->format('Y-m'),
                'lines'      => $lines,
                'net_pay'    => $net,
            ],
            'source' => [
                'label'        => 'Payslip Latest',
                'endpoint'     => 'local:HcmPayrollLine',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function payslipHistorySelf(User $user, ?int $companyId): ?array
    {
        $runIds = HcmPayrollRun::when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereIn('status', ['finalized', 'paid'])
            ->latest()
            ->limit(6)
            ->pluck('id');

        if ($runIds->isEmpty()) {
            return null;
        }

        $summary = HcmPayrollLine::where('user_id', $user->id)
            ->whereIn('hcm_payroll_run_id', $runIds)
            ->select('hcm_payroll_run_id', 'kind', DB::raw('SUM(amount) as total'))
            ->groupBy('hcm_payroll_run_id', 'kind')
            ->get()
            ->groupBy('hcm_payroll_run_id')
            ->map(fn ($g) => [
                'run_id'   => $g->first()->hcm_payroll_run_id,
                'earnings' => $g->where('kind', 'earning')->sum('total'),
                'deductions' => $g->where('kind', 'deduction')->sum('total'),
            ])
            ->values()
            ->toArray();

        if (empty($summary)) {
            return null;
        }

        return [
            'data'   => ['payslips' => $summary],
            'source' => [
                'label'        => 'Payslip History',
                'endpoint'     => 'local:HcmPayrollLine',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function ticketListSelf(User $user, ?int $companyId): ?array
    {
        $tickets = Ticket::where('user_id', $user->id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->latest()
            ->limit(10)
            ->get(['code', 'subject', 'status', 'priority', 'created_at'])
            ->toArray();

        if (empty($tickets)) {
            return null;
        }

        return [
            'data'   => ['tickets' => $tickets],
            'source' => [
                'label'        => 'My Tickets',
                'endpoint'     => 'local:Ticket',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function profileInfoSelf(User $user, ?int $companyId): ?array
    {
        $profile = EmployeeProfile::where('user_uuid', $user->uuid)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->first([
                'hire_date', 'employment_status', 'designation', 'contract_type',
                'contract_start_date', 'contract_end_date', 'gender',
            ]);

        return [
            'data'   => array_merge(
                ['name' => $user->name],
                $profile ? $profile->toArray() : [],
            ),
            'source' => [
                'label'        => 'Employee Profile',
                'endpoint'     => 'local:EmployeeProfile',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function subscriptionFeaturesCurrent(?int $companyId): ?array
    {
        if ($companyId === null || $companyId <= 0) {
            return null;
        }

        $subscription = Subscription::query()
            ->with(['package.features'])
            ->where('company_id', $companyId)
            ->whereIn('status', ['active', 'trial'])
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at')
            ->first();

        if ($subscription === null || $subscription->package === null) {
            return null;
        }

        $catalogByCode = collect(config('saas_package_feature_catalog.groups', []))
            ->filter(fn (mixed $group): bool => is_array($group))
            ->flatMap(function (array $group): array {
                $module = (string) ($group['module'] ?? 'other');
                $features = is_array($group['features'] ?? null) ? $group['features'] : [];

                $out = [];
                foreach ($features as $feature) {
                    if (! is_array($feature)) {
                        continue;
                    }

                    $code = strtolower(trim((string) ($feature['code'] ?? '')));
                    if ($code === '') {
                        continue;
                    }

                    $out[$code] = [
                        'name' => (string) ($feature['name'] ?? ''),
                        'module' => $module,
                    ];
                }

                return $out;
            });

        $features = $subscription->package->features
            ->filter(fn (PackageFeature $feature): bool => $feature->isIncluded())
            ->map(function (PackageFeature $feature) use ($catalogByCode): array {
                $code = (string) $feature->feature_code;
                $normalizedCode = strtolower(trim($code));
                $catalogFeature = $catalogByCode->get($normalizedCode, []);
                $name = (string) ($catalogFeature['name'] ?? $feature->feature_name ?? $code);

                return [
                    'code' => $code,
                    'name' => $name,
                    'module' => (string) ($catalogFeature['module'] ?? 'other'),
                    'limit' => $feature->limit,
                    'is_unlimited' => $feature->isUnlimited(),
                ];
            })
            ->sortBy(['module', 'name'])
            ->values()
            ->all();

        return [
            'data' => [
                'company_id' => $companyId,
                'subscription_status' => $subscription->status,
                'package_code' => $subscription->package->code,
                'package_name' => $subscription->package->name,
                'billing_cycle' => $subscription->billing_cycle,
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'ends_at' => $subscription->ends_at?->toIso8601String(),
                'feature_count' => count($features),
                'included_features' => $features,
            ],
            'source' => [
                'label' => 'Current Subscription Features',
                'endpoint' => 'local:Subscription + local:PackageFeature',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin resolvers
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function payrollRunStatus(?int $companyId): ?array
    {
        $runs = HcmPayrollRun::when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->latest()
            ->limit(3)
            ->get(['uuid', 'status', 'purpose', 'created_at', 'finalized_at'])
            ->toArray();

        if (empty($runs)) {
            return null;
        }

        return [
            'data'   => ['runs' => $runs],
            'source' => [
                'label'        => 'Payroll Run Status',
                'endpoint'     => 'local:HcmPayrollRun',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function leaveSummaryCompany(?int $companyId): ?array
    {
        $counts = LeaveRequest::when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $recent = LeaveRequest::when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('status', 'pending')
            ->with('user:id,name')
            ->latest()
            ->limit(10)
            ->get(['id', 'user_id', 'leave_type', 'date_from', 'date_to', 'days', 'status'])
            ->map(fn ($r) => [
                'user_name'  => $r->user?->name ?? 'Unknown',
                'leave_type' => $r->leave_type,
                'date_from'  => $r->date_from,
                'date_to'    => $r->date_to,
                'days'       => $r->days,
                'status'     => $r->status,
            ])
            ->toArray();

        $todayActive = LeaveRequest::when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereIn('status', ['approved', 'active'])
            ->where('date_from', '<=', today()->toDateString())
            ->where('date_to', '>=', today()->toDateString())
            ->with('user:id,name')
            ->limit(20)
            ->get(['id', 'user_id', 'leave_type', 'date_from', 'date_to', 'days'])
            ->map(fn ($r) => [
                'user_name'  => $r->user?->name ?? 'Unknown',
                'leave_type' => $r->leave_type,
                'date_from'  => $r->date_from,
                'date_to'    => $r->date_to,
                'days'       => $r->days,
            ])
            ->toArray();

        return [
            'data'   => [
                'status_counts'   => $counts,
                'pending_requests' => $recent,
                'on_leave_today'  => $todayActive,
            ],
            'source' => [
                'label'        => 'Company Leave Summary',
                'endpoint'     => 'local:LeaveRequest',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function attendanceSummaryCompany(?int $companyId): ?array
    {
        $today = AttendanceRecord::when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereDate('work_date', today())
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return [
            'data'   => ['date' => today()->toDateString(), 'status_counts' => $today],
            'source' => [
                'label'        => 'Company Attendance Summary',
                'endpoint'     => 'local:AttendanceRecord',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function employeeListCompany(?int $companyId): ?array
    {
        $total = EmployeeProfile::when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('employment_status', 'active')
            ->count();

        $sample = EmployeeProfile::when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('employment_status', 'active')
            ->limit(5)
            ->get(['designation', 'hire_date', 'employment_status'])
            ->toArray();

        if ($total === 0) {
            return null;
        }

        return [
            'data'   => ['total_active' => $total, 'sample' => $sample],
            'source' => [
                'label'        => 'Employee List',
                'endpoint'     => 'local:EmployeeProfile',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Global Admin (SaaS) resolvers
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function saasCompanySummary(): ?array
    {
        $total    = Company::count();
        $active   = Company::where('status', 'active')->count();
        $trial    = Company::where('status', 'trial')->count();
        $inactive = Company::whereNotIn('status', ['active', 'trial'])->count();

        $recent = Company::latest()
            ->limit(5)
            ->get(['name', 'status', 'created_at'])
            ->toArray();

        return [
            'data'   => [
                'total_companies'    => $total,
                'active_companies'   => $active,
                'trial_companies'    => $trial,
                'inactive_companies' => $inactive,
                'recent'             => $recent,
            ],
            'source' => [
                'label'        => 'SaaS Company Summary',
                'endpoint'     => 'local:Company',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function saasBillingSummary(): ?array
    {
        $totalRevenue  = Invoice::where('is_paid', true)->sum('amount_due');
        $unpaidCount   = Invoice::where('is_paid', false)->count();
        $unpaidAmount  = Invoice::where('is_paid', false)->sum('amount_due');

        $activeSubs    = Subscription::where('status', 'active')->count();
        $trialSubs     = Subscription::where('status', 'trial')->count();

        $monthlyRev = Invoice::where('is_paid', true)
            ->whereYear('paid_date', now()->year)
            ->whereMonth('paid_date', now()->month)
            ->sum('amount_due');

        $taxMonthly = $this->saasTaxMonthlySummary();

        return [
            'data'   => [
                'total_revenue_paid'   => $totalRevenue,
                'monthly_revenue'      => $monthlyRev,
                'unpaid_invoice_count' => $unpaidCount,
                'unpaid_invoice_amount' => $unpaidAmount,
                'active_subscriptions' => $activeSubs,
                'trial_subscriptions'  => $trialSubs,
                'tax_monthly_summary'  => $taxMonthly['data'] ?? [],
            ],
            'source' => [
                'label'        => 'SaaS Billing Summary',
                'endpoint'     => 'local:Invoice + local:Subscription',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function saasTaxMonthlySummary(): ?array
    {
        $month = now()->format('Y-m');

        $baseQuery = PlatformRevenueTransaction::query()
            ->forMonth($month)
            ->where('status', PlatformRevenueTransaction::STATUS_POSTED);

        $totalTaxObserved = (float) (clone $baseQuery)->sum('tax_amount');
        $taxCleared = (float) (clone $baseQuery)
            ->where('clearing_status', PlatformRevenueTransaction::CLEARING_CLEARED)
            ->sum('tax_amount');
        $taxUncleared = (float) (clone $baseQuery)
            ->where('clearing_status', PlatformRevenueTransaction::CLEARING_UNCLEARED)
            ->sum('tax_amount');
        $taxDisputed = (float) (clone $baseQuery)
            ->where('clearing_status', PlatformRevenueTransaction::CLEARING_DISPUTED)
            ->sum('tax_amount');
        $taxReversed = (float) (clone $baseQuery)
            ->where('clearing_status', PlatformRevenueTransaction::CLEARING_REVERSED)
            ->sum('tax_amount');

        $taxDueFromComplianceReport = 0.0;
        try {
            $report = app(BillingTaxCalculationService::class)->generateCrossTenantMonthlyReport($month);
            $taxDueFromComplianceReport = (float) ($report['summary']['total_tax_due'] ?? 0);
        } catch (\Throwable) {
            // Best effort only. AI should still return available runtime tax metrics.
        }

        return [
            'data'   => [
                'month' => $month,
                'government_tax_paid_this_month' => round($taxCleared, 2),
                'government_tax_due_this_month' => round($taxDueFromComplianceReport, 2),
                'observed_tax_amount_this_month' => round($totalTaxObserved, 2),
                'unpaid_tax_liability_this_month' => round($taxUncleared, 2),
                'disputed_tax_liability_this_month' => round($taxDisputed, 2),
                'reversed_tax_amount_this_month' => round($taxReversed, 2),
            ],
            'source' => [
                'label'        => 'SaaS Government Tax Summary',
                'endpoint'     => 'local:PlatformRevenueTransaction + local:BillingTaxCalculationService',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin company-wide resolvers (previously missing)
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function leaveBalanceOther(?int $companyId): ?array
    {
        $summary = EmployeeLeaveBalance::when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('year', now()->year)
            ->selectRaw('leave_type_id, SUM(balance) as total_balance, SUM(used) as total_used, COUNT(DISTINCT employee_id) as employee_count')
            ->groupBy('leave_type_id')
            ->get()
            ->toArray();

        if (empty($summary)) {
            return null;
        }

        return [
            'data'   => ['year' => now()->year, 'summary_by_type' => $summary],
            'source' => [
                'label'        => 'Company Leave Balance Summary',
                'endpoint'     => 'local:EmployeeLeaveBalance',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function leaveHistoryOther(?int $companyId): ?array
    {
        $previousPeriodStart = now()->subMonthNoOverflow()->startOfMonth();
        $previousPeriodEnd = now()->subMonthNoOverflow()->endOfMonth();

        $byStatus = LeaveRequest::when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->selectRaw('status, COUNT(*) as count, SUM(days) as total_days')
            ->groupBy('status')
            ->get()
            ->toArray();

        $byType = LeaveRequest::when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereYear('date_from', now()->year)
            ->selectRaw('leave_type, COUNT(*) as count, SUM(days) as total_days')
            ->groupBy('leave_type')
            ->get()
            ->toArray();

        $recentRows = LeaveRequest::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->with('user:id,name')
            ->latest()
            ->limit(10)
            ->get(['id', 'user_id', 'leave_type', 'date_from', 'date_to', 'days', 'status']);

        $recent = $recentRows->map(function (LeaveRequest $row): array {
            return [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'user_name' => (string) ($row->user?->name ?? '-'),
                'leave_type' => (string) $row->leave_type,
                'date_from' => $row->date_from?->toDateString(),
                'date_to' => $row->date_to?->toDateString(),
                'days' => (float) $row->days,
                'status' => (string) $row->status,
            ];
        })->values()->toArray();

        $previousPeriodRows = LeaveRequest::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->with('user:id,name')
            ->where(function ($query) use ($previousPeriodStart, $previousPeriodEnd): void {
                $query->whereBetween('date_from', [$previousPeriodStart->toDateString(), $previousPeriodEnd->toDateString()])
                    ->orWhereBetween('date_to', [$previousPeriodStart->toDateString(), $previousPeriodEnd->toDateString()])
                    ->orWhere(function ($overlap) use ($previousPeriodStart, $previousPeriodEnd): void {
                        $overlap->whereDate('date_from', '<=', $previousPeriodStart->toDateString())
                            ->whereDate('date_to', '>=', $previousPeriodEnd->toDateString());
                    });
            })
            ->orderByDesc('date_from')
            ->get(['id', 'user_id', 'leave_type', 'date_from', 'date_to', 'days', 'status']);

        $previousPeriodApplicants = $previousPeriodRows
            ->groupBy('user_id')
            ->map(function ($rows, $userId): array {
                /** @var LeaveRequest $first */
                $first = $rows->first();

                return [
                    'user_id' => (int) $userId,
                    'user_name' => (string) ($first?->user?->name ?? '-'),
                    'request_count' => (int) $rows->count(),
                    'total_days' => (float) $rows->sum('days'),
                    'latest_request_date' => $rows
                        ->pluck('date_from')
                        ->filter(fn ($date): bool => $date instanceof Carbon)
                        ->sortDesc()
                        ->map(fn (Carbon $date): string => $date->toDateString())
                        ->first(),
                ];
            })
            ->values()
            ->sortByDesc('request_count')
            ->values()
            ->toArray();

        if (empty($byStatus)) {
            return null;
        }

        return [
            'data'   => [
                'by_status'  => $byStatus,
                'by_type_this_year' => $byType,
                'recent'     => $recent,
                'previous_period' => [
                    'from' => $previousPeriodStart->toDateString(),
                    'to' => $previousPeriodEnd->toDateString(),
                    'total_requests' => $previousPeriodRows->count(),
                    'applicants' => $previousPeriodApplicants,
                ],
            ],
            'source' => [
                'label'        => 'Company Leave History',
                'endpoint'     => 'local:LeaveRequest',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function departmentInfo(?int $companyId): ?array
    {
        $departments = Department::when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        if (empty($departments)) {
            return null;
        }

        return [
            'data'   => ['total' => count($departments), 'departments' => $departments],
            'source' => [
                'label'        => 'Department Info',
                'endpoint'     => 'local:Department',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function ticketListAll(?int $companyId): ?array
    {
        $tickets = Ticket::when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->latest()
            ->limit(20)
            ->get(['code', 'subject', 'status', 'priority', 'created_at'])
            ->toArray();

        $summary = Ticket::when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->toArray();

        if (empty($tickets)) {
            return null;
        }

        return [
            'data'   => ['summary_by_status' => $summary, 'recent' => $tickets],
            'source' => [
                'label'        => 'All Tickets',
                'endpoint'     => 'local:Ticket',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // General fallback resolvers (broad context for unanswered questions)
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function generalFallbackGlobal(): ?array
    {
        $companySummary  = $this->saasCompanySummary();
        $billingSummary  = $this->saasBillingSummary();
        $totalEmployees  = EmployeeProfile::count();
        $activeEmployees = EmployeeProfile::where('employment_status', 'active')->count();

        return [
            'data'   => [
                'companies'       => $companySummary['data'] ?? [],
                'billing'         => $billingSummary['data'] ?? [],
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
            ],
            'source' => [
                'label'        => 'Global Admin Context',
                'endpoint'     => 'local:Company + local:Invoice + local:EmployeeProfile',
                'retrieved_at' => $this->now(),
            ],
        ];
    }

    /** @return array{data: array<string, mixed>, source: array<string, string>}|null */
    private function generalFallbackCompany(?int $companyId): ?array
    {
        $employees    = $this->employeeListCompany($companyId);
        $leaveSummary = $this->leaveSummaryCompany($companyId);
        $payroll      = $this->payrollRunStatus($companyId);
        $attendance   = $this->attendanceSummaryCompany($companyId);
        $departments  = $this->departmentInfo($companyId);

        return [
            'data'   => [
                'employees'   => $employees['data'] ?? [],
                'leave'       => $leaveSummary['data'] ?? [],
                'payroll'     => $payroll['data'] ?? [],
                'attendance'  => $attendance['data'] ?? [],
                'departments' => $departments['data'] ?? [],
            ],
            'source' => [
                'label'        => 'Company Admin Context',
                'endpoint'     => 'local:EmployeeProfile + local:LeaveRequest + local:HcmPayrollRun + local:AttendanceRecord + local:Department',
                'retrieved_at' => $this->now(),
            ],
        ];
    }
}

