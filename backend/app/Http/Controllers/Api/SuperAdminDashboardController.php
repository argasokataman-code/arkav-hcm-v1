<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\DashboardMetric;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminDashboardController extends Controller
{
    /**
     * GET /v1/saas/dashboard/kpi
     * Get top-level KPIs
     */
    public function getKpi(): JsonResponse
    {
        if (!$this->isHcmAdmin(request())) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $kpis = [
            'totalCompanies' => Company::count(),
            'totalUsers' => User::whereHas('companyMemberships')->count(),
            'mrr' => $this->calculateMRR(),
            'arr' => $this->calculateARR(),
            'activeSubscriptions' => Subscription::where('status', 'active')->count(),
            'churnRate' => $this->calculateChurnRate(),
            'customerLifetimeValue' => $this->calculateCLV(),
            'netRevenueRetention' => $this->calculateNRR(),
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
        if (!$this->isHcmAdmin(request())) {
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
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $companies = Company::withCount('users', 'subscriptions')
            ->latest('created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $companies->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'email' => $c->email,
                'userCount' => $c->users_count,
                'subscriptionCount' => $c->subscriptions_count,
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
        if (!$this->isHcmAdmin(request())) {
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
        if (!$this->isHcmAdmin(request())) {
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
        if (!$this->isHcmAdmin(request())) {
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
     * GET /v1/saas/dashboard/revenue/monthly
     * MRR trend (last 12 months)
     */
    public function getMonthlytRevenue(): JsonResponse
    {
        if (!$this->isHcmAdmin(request())) {
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
     * GET /v1/saas/dashboard/revenue/by-plan
     * Revenue breakdown by subscription plan
     */
    public function getRevenueByPlan(): JsonResponse
    {
        if (!$this->isHcmAdmin(request())) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $breakdown = Subscription::where('status', 'active')
            ->selectRaw('package_id, COUNT(*) as count, SUM(amount) as revenue')
            ->groupBy('package_id')
            ->with('package')
            ->get()
            ->map(fn ($s) => [
                'packageId' => $s->package_id,
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
        if (!$this->isHcmAdmin(request())) {
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
     * GET /v1/saas/dashboard/audit-logs
     * List audit logs
     */
    public function getAuditLogs(Request $request): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

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
     * Helper: Calculate MRR
     */
    private function calculateMRR(): float
    {
        return (float) Subscription::where('status', 'active')
            ->whereMonth('created_at', now()->month)
            ->sum('amount');
    }

    /**
     * Helper: Calculate ARR
     */
    private function calculateARR(): float
    {
        return (float) Subscription::where('status', 'active')->sum('amount') * 12;
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

    /**
     * Check if user is HCM admin (super admin)
     */
    private function isHcmAdmin(Request $request): bool
    {
        $user = $request->user();

        return $user ? $user->isHcmAdmin() : false;
    }
}
