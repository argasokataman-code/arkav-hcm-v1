<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * GET /v1/saas/reports/revenue
     * Get revenue report (monthly/yearly) - Admin only
     */
    public function revenue(Request $request): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $period = $request->get('period', 'monthly'); // monthly, yearly
        $year = $request->get('year', now()->year);
        $company_id = $request->get('company_id');

        $query = Payment::where('status', 'completed');

        if ($company_id) {
            $query->where('company_id', $company_id);
        }

        if ($period === 'yearly') {
            // Yearly revenue breakdown by month
            $data = $query
                ->selectRaw('MONTH(paid_at) as month, SUM(amount) as total, COUNT(*) as count')
                ->whereYear('paid_at', $year)
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return [
                        'month' => \Carbon\Carbon::createFromFormat('m', $item->month)->format('F'),
                        'total' => (float) $item->total,
                        'count' => $item->count,
                    ];
                });
        } else {
            // Daily revenue for current month
            $data = $query
                ->selectRaw('DATE(paid_at) as date, SUM(amount) as total, COUNT(*) as count')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(function ($item) {
                    return [
                        'date' => $item->date,
                        'total' => (float) $item->total,
                        'count' => $item->count,
                    ];
                });
        }

        $totalRevenue = Payment::where('status', 'completed')
            ->when($company_id, fn($q) => $q->where('company_id', $company_id))
            ->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'year' => $year,
                'totalRevenue' => (float) $totalRevenue,
                'breakdown' => $data,
            ],
        ]);
    }

    /**
     * GET /v1/saas/reports/aging
     * Get aging report (overdue invoices) - Admin only
     */
    public function aging(Request $request): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $company_id = $request->get('company_id');

        $query = Invoice::where('is_paid', false)
            ->where('due_date', '<', now());

        if ($company_id) {
            $query->where('company_id', $company_id);
        }

        $overdueInvoices = $query->get()
            ->map(function ($invoice) {
                $daysOverdue = now()->diffInDays($invoice->due_date);
                return [
                    'id' => $invoice->id,
                    'invoiceNumber' => $invoice->invoice_number,
                    'company' => $invoice->company->name,
                    'amountDue' => (float) $invoice->amount_due,
                    'dueDate' => $invoice->due_date->toDateString(),
                    'daysOverdue' => $daysOverdue,
                    'agingBucket' => $this->getAgingBucket($daysOverdue),
                ];
            });

        // Bucket analysis
        $buckets = [
            'current' => $overdueInvoices->filter(fn($i) => $i['daysOverdue'] < 30)->count(),
            '30-60' => $overdueInvoices->filter(fn($i) => $i['daysOverdue'] >= 30 && $i['daysOverdue'] < 60)->count(),
            '60-90' => $overdueInvoices->filter(fn($i) => $i['daysOverdue'] >= 60 && $i['daysOverdue'] < 90)->count(),
            '90+' => $overdueInvoices->filter(fn($i) => $i['daysOverdue'] >= 90)->count(),
        ];

        $totalOverdue = $overdueInvoices->sum('amountDue');

        return response()->json([
            'success' => true,
            'data' => [
                'totalOverdue' => (float) $totalOverdue,
                'totalInvoices' => $overdueInvoices->count(),
                'buckets' => $buckets,
                'invoices' => $overdueInvoices,
            ],
        ]);
    }

    /**
     * GET /v1/saas/reports/churn
     * Get churn report (cancelled subscriptions) - Admin only
     */
    public function churn(Request $request): JsonResponse
    {
        if (!$this->isHcmAdmin($request)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_REQUIRED', 'message' => 'Admin access required.'],
            ], 403);
        }

        $period = $request->get('period', 'monthly'); // monthly, yearly
        $year = $request->get('year', now()->year);
        $company_id = $request->get('company_id');

        $query = Subscription::where('status', 'cancelled');

        if ($company_id) {
            $query->where('company_id', $company_id);
        }

        if ($period === 'yearly') {
            $data = $query
                ->selectRaw('MONTH(updated_at) as month, COUNT(*) as count, SUM(amount) as totalValue')
                ->whereYear('updated_at', $year)
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return [
                        'month' => \Carbon\Carbon::createFromFormat('m', $item->month)->format('F'),
                        'churnCount' => $item->count,
                        'churnValue' => (float) $item->totalValue,
                    ];
                });
        } else {
            $data = $query
                ->selectRaw('DATE(updated_at) as date, COUNT(*) as count, SUM(amount) as totalValue')
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(function ($item) {
                    return [
                        'date' => $item->date,
                        'churnCount' => $item->count,
                        'churnValue' => (float) $item->totalValue,
                    ];
                });
        }

        // Current subscription count
        $activeCount = Subscription::where('status', 'active')->count();
        $cancelledCount = Subscription::where('status', 'cancelled')->count();
        $churnRate = $activeCount > 0 ? ($cancelledCount / ($activeCount + $cancelledCount)) * 100 : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'year' => $year,
                'activeSubscriptions' => $activeCount,
                'cancelledSubscriptions' => $cancelledCount,
                'churnRate' => round($churnRate, 2),
                'breakdown' => $data,
            ],
        ]);
    }

    /**
     * Get aging bucket label
     */
    private function getAgingBucket(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue < 30 => 'current',
            $daysOverdue < 60 => '30-60',
            $daysOverdue < 90 => '60-90',
            default => '90+',
        };
    }

    /**
     * Check if user is HCM admin
     */
    private function isHcmAdmin(Request $request): bool
    {
        $user = $request->user();
        return $user && $user->isHcmAdmin();
    }
}
