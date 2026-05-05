<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\DashboardMetric;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class SuperAdminDashboardController extends Controller
{
    /**
     * GET /v1/saas/dashboard/kpi
     * Get top-level KPIs
     */
    public function getKpi(): JsonResponse
    {
        $user = request()->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $this->recordAuditLog($user->id, 'view_dashboard', 'dashboard', null, [
            'page' => 'kpi',
        ]);

        $kpis = [
            'totalCompanies' => $this->resolveDashboardMetric('total_companies', fn (): float => (float) Company::count()),
            'totalUsers' => $this->resolveDashboardMetric('total_users', fn (): float => (float) User::whereHas('companyMemberships')->count()),
            'mrr' => $this->resolveDashboardMetric('mrr', fn (): float => $this->calculateMRR(), ['currency' => 'IDR']),
            'arr' => $this->resolveDashboardMetric('arr', fn (): float => $this->calculateARR(), ['currency' => 'IDR']),
            'activeSubscriptions' => $this->resolveDashboardMetric('active_subscriptions', fn (): float => (float) Subscription::where('status', 'active')->count()),
            'churnRate' => $this->resolveDashboardMetric('churn_rate', fn (): float => $this->calculateChurnRate(), ['unit' => 'percent']),
            'customerLifetimeValue' => $this->resolveDashboardMetric('customer_lifetime_value', fn (): float => $this->calculateCLV(), ['currency' => 'IDR']),
            'netRevenueRetention' => $this->resolveDashboardMetric('net_revenue_retention', fn (): float => $this->calculateNRR(), ['unit' => 'percent']),
        ];

        return response()->json([
            'success' => true,
            'data' => $kpis,
        ]);
    }

    /**
     * GET /v1/saas/dashboard/kpi/{metric_key}
     * Get specific metric with trend
     */
    public function getMetricTrend(string $metricKey): JsonResponse
    {
        $user = request()->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $trend = DashboardMetric::getTrend($metricKey, 12);
        $currentMetric = DashboardMetric::getMetric($metricKey);

        return response()->json([
            'success' => true,
            'data' => [
                'metricKey' => $metricKey,
                'currentValue' => $currentMetric?->metric_value,
                'metadata' => $currentMetric?->metric_metadata,
                'trend' => $trend->map(fn ($m) => [
                    'date' => $m->metric_date->toDateString(),
                    'value' => $m->metric_value,
                ]),
            ],
        ]);
    }

    /**
     * GET /v1/saas/dashboard/companies
     * List companies with stats
     */
    public function getCompanies(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $perPage = max(1, min((int) $request->integer('per_page', 15), 100));

        $companies = Company::withCount('users', 'subscriptions')
            ->withSum('subscriptions', 'amount')
            ->latest('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $companies->map(fn ($c) => [
                'id' => $c->id,
                'uuid' => $c->uuid,
                'code' => $c->code,
                'name' => $c->name,
                'email' => $c->email,
                'userCount' => $c->users_count,
                'subscriptionCount' => $c->subscriptions_count,
                'totalRevenue' => (float) ($c->subscriptions_sum_amount ?? 0),
                'createdAt' => $c->created_at->toIso8601String(),
            ]),
            'pagination' => [
                'total' => $companies->total(),
                'per_page' => $companies->perPage(),
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
            ],
        ]);
    }

    /**
     * GET /v1/saas/dashboard/companies/top-performers
     * Top 10 companies by revenue
     */
    public function getTopCompanies(): JsonResponse
    {
        $user = request()->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $topCompanies = Subscription::where('status', 'active')
            ->selectRaw('company_id, SUM(amount) as total_revenue, COUNT(*) as subscription_count')
            ->groupBy('company_id')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->with('company')
            ->get()
            ->map(function ($sub) {
                return [
                    'id' => $sub->company->id,
                    'name' => $sub->company->name,
                    'code' => $sub->company->code,
                    'totalRevenue' => (float) $sub->total_revenue,
                    'subscriptionCount' => (int) $sub->subscription_count,
                    'avgRevenuePerSubscription' => (float) ($sub->total_revenue / $sub->subscription_count),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $topCompanies,
        ]);
    }

    /**
     * GET /v1/saas/dashboard/companies/{id}/details
     * Deep dive company metrics
     */
    public function getCompanyDetails(Company $company): JsonResponse
    {
        $user = request()->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $subscriptions = $company->subscriptions()
            ->selectRaw('status, COUNT(*) as count, SUM(amount) as revenue')
            ->groupBy('status')
            ->get();

        $totalRevenue = $subscriptions->sum('revenue');
        $userCount = $company->users()->count();
        $activeSubscriptions = $subscriptions->firstWhere('status', 'active')?->count ?? 0;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $company->id,
                'code' => $company->code,
                'name' => $company->name,
                'email' => $company->email,
                'country' => $company->country,
                'industry' => $company->industry,
                'userCount' => $userCount,
                'totalRevenue' => (float) $totalRevenue,
                'activeSubscriptions' => (int) $activeSubscriptions,
                'subscriptionsByStatus' => $subscriptions->mapWithKeys(fn ($s) => [
                    $s->status => [
                        'count' => (int) $s->count,
                        'revenue' => (float) $s->revenue,
                    ],
                ]),
                'createdAt' => $company->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /v1/saas/dashboard/users
     * User statistics
     */
    public function getUserStats(): JsonResponse
    {
        $user = request()->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $totalUsers = User::whereHas('companyMemberships')->count();
        $verifiedUsers = User::whereHas('companyMemberships')
            ->whereNotNull('email_verified_at')
            ->count();
        $newUsersThisMonth = User::whereHas('companyMemberships')
            ->whereMonth('created_at', now()->month)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'totalUsers' => $totalUsers,
                'verifiedUsers' => $verifiedUsers,
                'unverifiedUsers' => $totalUsers - $verifiedUsers,
                'newUsersThisMonth' => $newUsersThisMonth,
                'verificationRate' => $totalUsers > 0 ? round(($verifiedUsers / $totalUsers) * 100, 2) : 0,
            ],
        ]);
    }

    /**
     * GET /v1/saas/dashboard/users/retention
     * Retention summary based on tenant membership lifecycle
     */
    public function getUserRetention(): JsonResponse
    {
        $user = request()->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $currentMonthStart = now()->startOfMonth();
        $previousMonthEnd = $currentMonthStart->copy()->subSecond();

        $previousCohortUsers = CompanyUser::query()
            ->whereNotNull('joined_at')
            ->whereDate('joined_at', '<=', $previousMonthEnd->toDateString())
            ->distinct('user_id')
            ->count('user_id');

        $retainedUsers = CompanyUser::query()
            ->whereNotNull('joined_at')
            ->whereDate('joined_at', '<=', $previousMonthEnd->toDateString())
            ->where('status', 'active')
            ->distinct('user_id')
            ->count('user_id');

        $activeUsersCurrent = CompanyUser::query()
            ->where('status', 'active')
            ->distinct('user_id')
            ->count('user_id');

        $newUsersThisMonth = max(0, $activeUsersCurrent - $retainedUsers);

        return response()->json([
            'success' => true,
            'data' => [
                'cohortMonth' => $currentMonthStart->copy()->subMonth()->format('Y-m'),
                'previousCohortUsers' => $previousCohortUsers,
                'retainedUsers' => $retainedUsers,
                'churnedUsers' => max(0, $previousCohortUsers - $retainedUsers),
                'newUsersThisMonth' => $newUsersThisMonth,
                'activeUsersCurrent' => $activeUsersCurrent,
                'retentionRate' => $previousCohortUsers > 0 ? round(($retainedUsers / $previousCohortUsers) * 100, 2) : 0,
            ],
        ]);
    }

    /**
     * GET /v1/saas/dashboard/revenue/monthly
     * MRR trend (last 12 months)
     */
    public function getMonthlytRevenue(): JsonResponse
    {
        $user = request()->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $mrr = Subscription::where('status', 'active')
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->sum('amount');

            $data[] = [
                'month' => $date->format('Y-m'),
                'mrr' => (float) $mrr,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * GET /v1/saas/dashboard/revenue/forecast
     * Short-term revenue projection based on recent monthly trend
     */
    public function getRevenueForecast(): JsonResponse
    {
        $user = request()->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $history = $this->buildMonthlyRevenueSeries(6);
        $historyValues = $history->pluck('mrr')->values();
        $deltas = collect();

        for ($i = 1; $i < $historyValues->count(); $i++) {
            $deltas->push((float) $historyValues[$i] - (float) $historyValues[$i - 1]);
        }

        $averageDelta = $deltas->isNotEmpty() ? round((float) $deltas->avg(), 2) : 0.0;
        $lastMonth = now()->startOfMonth();
        $lastValue = (float) ($historyValues->last() ?? 0);

        $forecast = collect(range(1, 3))->map(function (int $step) use ($lastMonth, $lastValue, $averageDelta) {
            return [
                'month' => $lastMonth->copy()->addMonths($step)->format('Y-m'),
                'projectedMrr' => round(max(0, $lastValue + ($averageDelta * $step)), 2),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'method' => 'average_delta_last_6_months',
                'history' => $history->values(),
                'forecast' => $forecast->values(),
            ],
        ]);
    }

    /**
     * GET /v1/saas/dashboard/revenue/by-plan
     * Revenue breakdown by subscription plan
     */
    public function getRevenueByPlan(): JsonResponse
    {
        $user = request()->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $breakdown = Subscription::where('status', 'active')
            ->selectRaw('package_uuid, COUNT(*) as count, SUM(amount) as revenue')
            ->groupBy('package_uuid')
            ->with('package')
            ->get()
            ->map(fn ($s) => [
                'packageId' => $s->package_uuid,
                'packageName' => $s->package?->name ?? 'Unknown',
                'subscriptionCount' => (int) $s->count,
                'revenue' => (float) $s->revenue,
            ]);

        return response()->json([
            'success' => true,
            'data' => $breakdown,
        ]);
    }

    /**
     * GET /v1/saas/dashboard/subscriptions/status
     * Breakdown by status
     */
    public function getSubscriptionStatus(): JsonResponse
    {
        $user = request()->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $breakdown = Subscription::selectRaw('status, COUNT(*) as count, SUM(amount) as revenue')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($s) => [
                $s->status => [
                    'count' => (int) $s->count,
                    'revenue' => (float) $s->revenue,
                ],
            ]);

        return response()->json([
            'success' => true,
            'data' => $breakdown,
        ]);
    }

    /**
     * GET /v1/saas/dashboard/subscriptions/health
     * Subscription portfolio health summary
     */
    public function getSubscriptionHealth(): JsonResponse
    {
        $user = request()->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $breakdown = Subscription::selectRaw('status, COUNT(*) as count, SUM(amount) as revenue')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($subscription) => [
                $subscription->status => [
                    'count' => (int) $subscription->count,
                    'revenue' => (float) $subscription->revenue,
                ],
            ]);

        $totalSubscriptions = Subscription::count();
        $activeSubscriptions = (int) ($breakdown['active']['count'] ?? 0);
        $expiringSoon = Subscription::query()
            ->whereIn('status', ['active', 'trial'])
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now(), now()->copy()->addDays(14)])
            ->count();

        $autoRenewDisabled = Subscription::query()
            ->whereIn('status', ['active', 'trial'])
            ->where('auto_renew', false)
            ->count();

        $expiredButNotClosed = Subscription::query()
            ->whereIn('status', ['active', 'trial'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->count();

        $activeRatio = $totalSubscriptions > 0 ? $activeSubscriptions / $totalSubscriptions : 0;
        $expiringPenalty = $activeSubscriptions > 0 ? $expiringSoon / $activeSubscriptions : 0;
        $autoRenewPenalty = $activeSubscriptions > 0 ? $autoRenewDisabled / $activeSubscriptions : 0;
        $healthScore = round(max(0, min(100, ($activeRatio * 70 + (1 - $expiringPenalty) * 20 + (1 - $autoRenewPenalty) * 10) * 100)), 2);

        return response()->json([
            'success' => true,
            'data' => [
                'healthScore' => $healthScore,
                'totalSubscriptions' => $totalSubscriptions,
                'activeSubscriptions' => $activeSubscriptions,
                'expiringSoon' => $expiringSoon,
                'autoRenewDisabled' => $autoRenewDisabled,
                'expiredButNotClosed' => $expiredButNotClosed,
                'breakdown' => $breakdown,
            ],
        ]);
    }

    /**
     * GET /v1/saas/dashboard/reports/custom
     * Custom summary report using date range and grouping filters
     */
    public function getCustomReport(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $groupBy = $request->string('group_by', 'month')->toString();
        if (! in_array($groupBy, ['month', 'plan', 'status'], true)) {
            $groupBy = 'month';
        }

        $fromDate = $request->date('from')?->startOfDay() ?? now()->copy()->subDays(90)->startOfDay();
        $toDate = $request->date('to')?->endOfDay() ?? now()->endOfDay();

        $subscriptionQuery = Subscription::query()->whereBetween('created_at', [$fromDate, $toDate]);
        $companyQuery = Company::query()->whereBetween('created_at', [$fromDate, $toDate]);
        $membershipQuery = CompanyUser::query()->whereBetween('joined_at', [$fromDate, $toDate]);

        $summary = [
            'companiesCreated' => $companyQuery->count(),
            'userMembershipsAdded' => $membershipQuery->count(),
            'subscriptionsCreated' => $subscriptionQuery->count(),
            'activeSubscriptions' => (clone $subscriptionQuery)->where('status', 'active')->count(),
            'cancelledSubscriptions' => (clone $subscriptionQuery)->where('status', 'cancelled')->count(),
            'totalRevenue' => round((float) ((clone $subscriptionQuery)->sum('amount')), 2),
        ];

        $breakdown = match ($groupBy) {
            'plan' => Subscription::query()
                ->selectRaw('package_uuid, COUNT(*) as subscription_count, SUM(amount) as revenue')
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->groupBy('package_uuid')
                ->with('package')
                ->get()
                ->map(fn ($subscription) => [
                    'packageId' => $subscription->package_uuid,
                    'packageName' => $subscription->package?->name ?? 'Unknown',
                    'subscriptionCount' => (int) $subscription->subscription_count,
                    'revenue' => (float) $subscription->revenue,
                ]),
            'status' => Subscription::query()
                ->selectRaw('status, COUNT(*) as subscription_count, SUM(amount) as revenue')
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->groupBy('status')
                ->get()
                ->map(fn ($subscription) => [
                    'status' => $subscription->status,
                    'subscriptionCount' => (int) $subscription->subscription_count,
                    'revenue' => (float) $subscription->revenue,
                ]),
            default => $this->buildMonthlyRevenueSeriesBetween($fromDate, $toDate),
        };

        return response()->json([
            'success' => true,
            'data' => [
                'filters' => [
                    'from' => $fromDate->toDateString(),
                    'to' => $toDate->toDateString(),
                    'groupBy' => $groupBy,
                ],
                'summary' => $summary,
                'breakdown' => $breakdown->values(),
            ],
        ]);
    }

    /**
     * GET /v1/saas/dashboard/audit-logs
     * List audit logs
     */
    public function getAuditLogs(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $this->recordAuditLog($user->id, 'view_audit_logs', 'dashboard', null, [
            'filter' => (string) $request->get('action', 'all'),
            'targetType' => (string) $request->get('target_type', ''),
            'dateFrom' => (string) $request->get('date_from', ''),
            'dateTo' => (string) $request->get('date_to', ''),
        ]);

        $query = AuditLog::with('superAdmin');

        if ($request->has('super_admin_id')) {
            $query->where('super_admin_id', $request->get('super_admin_id'));
        }
        if ($request->has('action')) {
            $query->where('action', $request->get('action'));
        }
        if ($request->has('target_type')) {
            $query->where('target_type', $request->get('target_type'));
        }
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        $logs = $query->latest('created_at')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $logs->map(fn ($log) => [
                'id' => $log->id,
                'superAdminId' => $log->super_admin_id,
                'superAdminName' => $log->superAdmin?->name,
                'action' => $log->action,
                'actionLabel' => $log->getActionLabel(),
                'targetType' => $log->target_type,
                'targetId' => $log->target_id,
                'details' => $log->details,
                'ipAddress' => $log->ip_address,
                'createdAt' => $log->created_at->toIso8601String(),
            ]),
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    /**
     * GET /v1/saas/dashboard/audit-logs/{auditLog}
     * Show audit log detail
     */
    public function getAuditLogDetail(string $auditLog): JsonResponse
    {
        $user = request()->user();
        if (! $user || ! $user->isGlobalHcmAdmin()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $this->recordAuditLog($user->id, 'view_audit_logs', 'audit_log', null, [
            'auditLog' => $auditLog,
            'mode' => 'detail',
        ]);

        $auditLogRecord = AuditLog::query()
            ->with('superAdmin')
            ->where(function ($query) use ($auditLog): void {
                if (Schema::hasColumn('audit_logs', 'uuid')) {
                    $query->where('uuid', $auditLog);

                    if (ctype_digit($auditLog)) {
                        $query->orWhere('id', (int) $auditLog);
                    }

                    return;
                }

                $query->where('id', ctype_digit($auditLog) ? (int) $auditLog : 0);
            })
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $auditLogRecord->id,
                'uuid' => $auditLogRecord->uuid,
                'superAdminId' => $auditLogRecord->super_admin_id,
                'superAdminName' => $auditLogRecord->superAdmin?->name,
                'action' => $auditLogRecord->action,
                'actionLabel' => $auditLogRecord->getActionLabel(),
                'targetType' => $auditLogRecord->target_type,
                'targetId' => $auditLogRecord->target_id,
                'details' => $auditLogRecord->details,
                'ipAddress' => $auditLogRecord->ip_address,
                'userAgent' => $auditLogRecord->user_agent,
                'isSensitiveAction' => $auditLogRecord->isSensitiveAction(),
                'createdAt' => $auditLogRecord->created_at?->toIso8601String(),
                'updatedAt' => $auditLogRecord->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Helper: Calculate MRR
     */
    private function calculateMRR(): float
    {
        $monthly = (float) Subscription::where('status', 'active')
            ->where(fn ($q) => $q->where('billing_cycle', 'monthly')->orWhereNull('billing_cycle'))
            ->sum('amount');

        $yearly = (float) Subscription::where('status', 'active')
            ->where('billing_cycle', 'yearly')
            ->sum('amount');

        return round($monthly + ($yearly / 12), 2);
    }

    /**
     * Helper: Calculate ARR
     */
    private function calculateARR(): float
    {
        $monthly = (float) Subscription::where('status', 'active')
            ->where(fn ($q) => $q->where('billing_cycle', 'monthly')->orWhereNull('billing_cycle'))
            ->sum('amount');

        $yearly = (float) Subscription::where('status', 'active')
            ->where('billing_cycle', 'yearly')
            ->sum('amount');

        return round(($monthly * 12) + $yearly, 2);
    }

    /**
     * Helper: Calculate Churn Rate
     */
    private function calculateChurnRate(): float
    {
        $currentMonth = now();
        $prevMonth = now()->subMonth();

        $cancelledThisMonth = Subscription::where('status', 'cancelled')
            ->whereMonth('updated_at', $currentMonth->month)
            ->count();

        $activeLastMonth = Subscription::where('status', 'active')
            ->whereMonth('created_at', '<=', $prevMonth->endOfMonth())
            ->count();

        return $activeLastMonth > 0 ? round(($cancelledThisMonth / $activeLastMonth) * 100, 2) : 0;
    }

    /**
     * Helper: Calculate Customer Lifetime Value
     */
    private function calculateCLV(): float
    {
        $totalRevenue = (float) Subscription::sum('amount');
        $companyCount = Company::count();

        return $companyCount > 0 ? round($totalRevenue / $companyCount, 2) : 0;
    }

    /**
     * Helper: Calculate Net Revenue Retention
     */
    private function calculateNRR(): float
    {
        $startingRevenue = Subscription::where('status', 'active')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->sum('amount');

        $currentRevenue = Subscription::where('status', 'active')
            ->whereMonth('created_at', now()->month)
            ->sum('amount');

        return $startingRevenue > 0 ? round(($currentRevenue / $startingRevenue) * 100, 2) : 0;
    }

    private function buildMonthlyRevenueSeries(int $months): \Illuminate\Support\Collection
    {
        $startDate = now()->startOfMonth()->subMonths($months - 1);

        return $this->buildMonthlyRevenueSeriesBetween($startDate, now()->endOfMonth());
    }

    private function buildMonthlyRevenueSeriesBetween(\Carbon\CarbonInterface $fromDate, \Carbon\CarbonInterface $toDate): \Illuminate\Support\Collection
    {
        $cursor = $fromDate->copy()->startOfMonth();
        $endMonth = $toDate->copy()->startOfMonth();
        $series = collect();

        while ($cursor <= $endMonth) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();

            $series->push([
                'month' => $cursor->format('Y-m'),
                'mrr' => round((float) Subscription::query()
                    ->where('status', 'active')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->sum('amount'), 2),
            ]);

            $cursor->addMonth();
        }

        return $series;
    }

    private function resolveDashboardMetric(string $metricKey, callable $fallbackResolver, array $metadata = []): float|int
    {
        $metric = DashboardMetric::getMetric($metricKey);

        if ($metric && ! $metric->needsRecalculation()) {
            return $this->normalizeMetricValue($metric->metric_value);
        }

        $value = $this->normalizeMetricValue($fallbackResolver());

        $values = [
            'metric_value' => $value,
            'metric_metadata' => Arr::where($metadata, static fn ($metadataValue): bool => $metadataValue !== null),
            'calculated_at' => now(),
            'next_calculation_at' => now()->addHour(),
        ];

        if (Schema::hasColumn('dashboard_metrics', 'company_id')) {
            $values['company_id'] = null;
        }

        if ($metric) {
            $metric->fill($values);
            $metric->save();
        } else {
            DashboardMetric::create(array_merge([
                'metric_date' => now()->toDateString(),
                'metric_key' => $metricKey,
            ], $values));
        }

        return $value;
    }

    private function normalizeMetricValue(mixed $value): float|int
    {
        if (is_int($value)) {
            return $value;
        }

        $numericValue = (float) $value;

        return fmod($numericValue, 1.0) === 0.0 ? (int) $numericValue : round($numericValue, 2);
    }

    /**
     * Check if user is HCM admin (super admin)
     */
    private function isHcmAdmin(Request $request): bool
    {
        $user = $request->user();

        return $user ? $user->isHcmAdmin() : false;
    }

    private function recordAuditLog(
        int $superAdminId,
        string $action,
        string $targetType,
        ?int $targetId,
        array $details = []
    ): void {
        try {
            AuditLog::query()->create([
                'super_admin_id' => $superAdminId,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'details' => $details,
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
            ]);
        } catch (\Throwable) {
            // Do not block dashboard responses when audit persistence fails.
        }
    }
}
