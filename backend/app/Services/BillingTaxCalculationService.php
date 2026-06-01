<?php

namespace App\Services;

use App\Models\Company;
use App\Models\HcmBillingTaxPolicy;
use App\Models\Invoice;
use App\Models\PlatformRevenueTransaction;
use Illuminate\Support\Facades\DB;

class BillingTaxCalculationService
{
    public function calculateBillingTax(int $companyId, string $billingMonth): array
    {
        // AN-014: Defensive tenant isolation guard — reject calls with invalid companyId.
        if ($companyId <= 0) {
            throw new \InvalidArgumentException('BillingTaxCalculationService: companyId must be a positive integer.');
        }

        $policy = $this->resolvePolicy($companyId, $billingMonth);

        $periodStart = $billingMonth . '-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));

        $invoiceQuery = Invoice::query()
            ->where('company_id', $companyId)
            ->whereBetween('issue_date', [$periodStart, $periodEnd]);

        $invoiceCount = (clone $invoiceQuery)->count();
        $paidInvoiceCount = (clone $invoiceQuery)
            ->where(function ($query): void {
                $query->where('is_paid', true)
                    ->orWhere('status', 'paid');
            })
            ->count();

        $totalInvoiceAmount = (float) ((clone $invoiceQuery)->sum('amount_due') ?? 0);
        $paidInvoiceAmount = (float) ((clone $invoiceQuery)
            ->where(function ($query): void {
                $query->where('is_paid', true)
                    ->orWhere('status', 'paid');
            })
            ->sum('amount_due') ?? 0);

        $revenueQuery = PlatformRevenueTransaction::query()
            ->where('company_id', $companyId)
            ->forMonth($billingMonth)
            ->where('status', PlatformRevenueTransaction::STATUS_POSTED);

        $clearedRevenueAmount = (float) ((clone $revenueQuery)
            ->where('clearing_status', PlatformRevenueTransaction::CLEARING_CLEARED)
            ->sum('amount') ?? 0);
        $unclearedRevenueAmount = (float) ((clone $revenueQuery)
            ->where('clearing_status', PlatformRevenueTransaction::CLEARING_UNCLEARED)
            ->sum('amount') ?? 0);
        $disputedRevenueAmount = (float) ((clone $revenueQuery)
            ->where('clearing_status', PlatformRevenueTransaction::CLEARING_DISPUTED)
            ->sum('amount') ?? 0);
        $reversedRevenueAmount = (float) ((clone $revenueQuery)
            ->where('clearing_status', PlatformRevenueTransaction::CLEARING_REVERSED)
            ->sum('amount') ?? 0);

        $outstandingInvoiceAmount = max(0, round($totalInvoiceAmount - $paidInvoiceAmount, 2));
        $taxRatePercentage = (float) ($policy?->tax_rate_percentage ?? 0);
        // Runtime compatibility: use cleared revenue as primary taxable base; fallback to invoice total while legacy flow is still active.
        $taxableRevenueAmount = $clearedRevenueAmount > 0 ? $clearedRevenueAmount : $totalInvoiceAmount;

        // Level 2: When using invoice-based fallback, prefer per-invoice snapshot rates over current policy rate.
        // This ensures historical invoices reflect the rate that was active when they were issued.
        if ($clearedRevenueAmount <= 0) {
            $invoicesWithRates = (clone $invoiceQuery)->get(['amount_due', 'billing_tax_rate_snapshot']);
            $hasAnySnapshot = $invoicesWithRates->contains(fn ($inv): bool => $inv->billing_tax_rate_snapshot !== null);
            if ($hasAnySnapshot) {
                $snapshotTax = $invoicesWithRates->sum(function ($inv) use ($taxRatePercentage): float {
                    $rate = $inv->billing_tax_rate_snapshot !== null
                        ? (float) $inv->billing_tax_rate_snapshot
                        : $taxRatePercentage;
                    return (float) $inv->amount_due * ($rate / 100);
                });
                $taxAmount = round($snapshotTax, 2);
            } else {
                $taxAmount = round($taxableRevenueAmount * ($taxRatePercentage / 100), 2);
            }
        } else {
            $taxAmount = round($taxableRevenueAmount * ($taxRatePercentage / 100), 2);
        }

        return [
            'company_id' => $companyId,
            'billing_month' => $billingMonth,
            'policy_uuid' => $policy?->id,
            'billing_cycle_type' => $policy?->billing_cycle_type,
            'tax_rate_percentage' => $taxRatePercentage,
            'base_calculation_method' => $policy?->base_calculation_method ?? 'invoice_amount_due',
            'invoice_count' => (int) $invoiceCount,
            'paid_invoice_count' => (int) $paidInvoiceCount,
            'unpaid_invoice_count' => max(0, (int) $invoiceCount - (int) $paidInvoiceCount),
            'total_invoice_amount' => $totalInvoiceAmount,
            'paid_invoice_amount' => round($paidInvoiceAmount, 2),
            'outstanding_invoice_amount' => $outstandingInvoiceAmount,
            'taxable_revenue_amount' => round($taxableRevenueAmount, 2),
            'cleared_revenue_amount' => round($clearedRevenueAmount, 2),
            'uncleared_revenue_amount' => round($unclearedRevenueAmount, 2),
            'disputed_revenue_amount' => round($disputedRevenueAmount, 2),
            'reversed_revenue_amount' => round($reversedRevenueAmount, 2),
            'tax_amount' => $taxAmount,
        ];
    }

    /**
     * Level 2: Resolve the active billing tax rate for a company at the current moment.
     * Use this when creating a new invoice so the rate is snapshotted into the invoice record.
     */
    public function resolvePolicyRateSnapshot(int $companyId, string $billingMonth): float
    {
        $policy = $this->resolvePolicy($companyId, $billingMonth);
        return (float) ($policy?->tax_rate_percentage ?? 0);
    }

    public function generateCrossTenantMonthlyReport(string $billingMonth): array
    {
        // Level 3: If this billing month has been locked with a per-tenant snapshot, return it directly.
        // This prevents retroactive config changes from altering historical report data.
        $lockedSnapshot = $this->getLockedMonthlySnapshot($billingMonth);
        if ($lockedSnapshot !== null) {
            return $lockedSnapshot;
        }

        $periodStart = $billingMonth . '-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));

        $tenantIds = Invoice::query()
            ->whereBetween('issue_date', [$periodStart, $periodEnd])
            ->distinct()
            ->pluck('company_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values();

        $tenantRows = [];
        $invoiceSnapshots = [];
        $totalInvoiceAmount = 0.0;
        $totalTaxDue = 0.0;
        $unpaidInvoiceCount = 0;
        $tenantCountWithPolicy = 0;
        $totalTaxableRevenueAmount = 0.0;
        $totalClearedRevenueAmount = 0.0;
        $totalUnclearedRevenueAmount = 0.0;
        $totalDisputedRevenueAmount = 0.0;
        $totalReversedRevenueAmount = 0.0;
        $totalSubscriptionRevenue = 0.0;
        $totalAddonRevenue = 0.0;
        $totalGrossRevenue = 0.0;
        $totalNetRevenue = 0.0;

        foreach ($tenantIds as $tenantId) {
            $calc = $this->calculateBillingTax($tenantId, $billingMonth);
            $company = Company::query()->find($tenantId);

            $transactionBase = PlatformRevenueTransaction::query()
                ->where('company_id', $tenantId)
                ->forMonth($billingMonth)
                ->where('status', PlatformRevenueTransaction::STATUS_POSTED);

            $subscriptionRevenue = (float) ((clone $transactionBase)
                ->where('transaction_type', PlatformRevenueTransaction::TYPE_SUBSCRIPTION)
                ->sum('amount') ?? 0);
            $payrollServiceFee = 0.0;
            $addonRevenue = (float) ((clone $transactionBase)
                ->where('transaction_type', PlatformRevenueTransaction::TYPE_ADDON_FEATURE)
                ->sum('amount') ?? 0);

            $grossRevenue = round($subscriptionRevenue + $addonRevenue, 2);
            $taxableRevenueAmount = (float) ($calc['taxable_revenue_amount'] ?? 0);
            // If runtime stream capture is not yet available for the month, align gross/net
            // with taxable fallback (invoice-based) to avoid misleading zero-gross summaries.
            $effectiveGrossRevenue = $grossRevenue > 0 ? $grossRevenue : $taxableRevenueAmount;
            $netRevenue = round(max(0, $effectiveGrossRevenue - (float) $calc['tax_amount']), 2);

            if (!empty($calc['policy_uuid'])) {
                $tenantCountWithPolicy++;
            }

            $totalInvoiceAmount += (float) $calc['total_invoice_amount'];
            $totalTaxDue += (float) $calc['tax_amount'];
            $unpaidInvoiceCount += (int) $calc['unpaid_invoice_count'];
            $totalTaxableRevenueAmount += (float) ($calc['taxable_revenue_amount'] ?? 0);
            $totalClearedRevenueAmount += (float) ($calc['cleared_revenue_amount'] ?? 0);
            $totalUnclearedRevenueAmount += (float) ($calc['uncleared_revenue_amount'] ?? 0);
            $totalDisputedRevenueAmount += (float) ($calc['disputed_revenue_amount'] ?? 0);
            $totalReversedRevenueAmount += (float) ($calc['reversed_revenue_amount'] ?? 0);
            $totalSubscriptionRevenue += $subscriptionRevenue;
            $totalAddonRevenue += $addonRevenue;
            $totalGrossRevenue += $effectiveGrossRevenue;
            $totalNetRevenue += $netRevenue;

            $tenantRows[] = [
                'company_id' => $tenantId,
                'company_name' => $company?->name,
                'billing_month' => $billingMonth,
                'billing_cycle_type' => $calc['billing_cycle_type'] ?: 'monthly',
                'next_renewal_month' => $this->resolveNextRenewalMonth($billingMonth, $calc['billing_cycle_type'] ?? null),
                'policy_uuid' => $calc['policy_uuid'],
                'plan_name' => '-',
                'tax_rate_percentage' => (float) $calc['tax_rate_percentage'],
                'invoice_count' => (int) $calc['invoice_count'],
                'paid_invoice_count' => (int) $calc['paid_invoice_count'],
                'unpaid_invoice_count' => (int) $calc['unpaid_invoice_count'],
                'total_invoice_amount' => (float) $calc['total_invoice_amount'],
                'subscription_revenue' => round($subscriptionRevenue, 2),
                'addon_revenue' => round($addonRevenue, 2),
                'gross_revenue' => round($effectiveGrossRevenue, 2),
                'taxable_revenue_amount' => $taxableRevenueAmount,
                'cleared_revenue_amount' => (float) ($calc['cleared_revenue_amount'] ?? 0),
                'uncleared_revenue_amount' => (float) ($calc['uncleared_revenue_amount'] ?? 0),
                'disputed_revenue_amount' => (float) ($calc['disputed_revenue_amount'] ?? 0),
                'reversed_revenue_amount' => (float) ($calc['reversed_revenue_amount'] ?? 0),
                'tax_amount_due' => (float) $calc['tax_amount'],
                'net_revenue' => $netRevenue,
            ];

            $monthlyInvoices = Invoice::query()
                ->where('company_id', $tenantId)
                ->whereBetween('issue_date', [$periodStart, $periodEnd])
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->get(['id', 'uuid', 'invoice_number', 'amount_due', 'issue_date', 'due_date', 'is_paid', 'status']);

            foreach ($monthlyInvoices as $invoice) {
                $invoiceSnapshots[] = [
                    'company_id' => $tenantId,
                    'company_name' => $company?->name,
                    'invoice_uuid' => $invoice->uuid,
                    'invoice_number' => $invoice->invoice_number,
                    'amount_due' => (float) ($invoice->amount_due ?? 0),
                    'status' => ((bool) $invoice->is_paid || strtolower((string) $invoice->status) === 'paid') ? 'paid' : 'unpaid',
                    'issue_date' => optional($invoice->issue_date)?->toDateString(),
                    'due_date' => optional($invoice->due_date)?->toDateString(),
                ];
            }
        }

        return [
            'month' => $billingMonth,
            'summary' => [
                'tenant_count' => count($tenantRows),
                'tenant_count_with_policy' => $tenantCountWithPolicy,
                'total_invoice_amount' => round($totalInvoiceAmount, 2),
                'total_taxable_revenue_amount' => round($totalTaxableRevenueAmount, 2),
                'total_cleared_revenue_amount' => round($totalClearedRevenueAmount, 2),
                'total_uncleared_revenue_amount' => round($totalUnclearedRevenueAmount, 2),
                'total_disputed_revenue_amount' => round($totalDisputedRevenueAmount, 2),
                'total_reversed_revenue_amount' => round($totalReversedRevenueAmount, 2),
                'total_tax_due' => round($totalTaxDue, 2),
                'unpaid_invoice_count' => $unpaidInvoiceCount,
                'total_subscription_revenue' => round($totalSubscriptionRevenue, 2),
                'total_addon_revenue' => round($totalAddonRevenue, 2),
                'total_gross_revenue' => round($totalGrossRevenue, 2),
                'total_net_revenue' => round($totalNetRevenue, 2),
                'effective_tax_rate' => $totalTaxableRevenueAmount > 0 ? round(($totalTaxDue / $totalTaxableRevenueAmount) * 100, 2) : 0,
            ],
            'tenants' => $tenantRows,
            'invoice_snapshots' => $invoiceSnapshots,
        ];
    }

    public function validateBillingTaxPolicy(array $payload): bool
    {
        if (!isset($payload['tax_rate_percentage']) || (float) $payload['tax_rate_percentage'] < 0 || (float) $payload['tax_rate_percentage'] > 100) {
            return false;
        }

        if (!isset($payload['billing_cycle_type']) || !in_array($payload['billing_cycle_type'], ['monthly', 'yearly', 'custom'], true)) {
            return false;
        }

        if (!isset($payload['base_calculation_method']) || $payload['base_calculation_method'] !== 'invoice_amount_due') {
            return false;
        }

        return true;
    }

    /**
     * Level 3: Return the frozen per-tenant snapshot for a locked billing month, or null if not locked.
     */
    private function getLockedMonthlySnapshot(string $billingMonth): ?array
    {
        if (! DB::getSchemaBuilder()->hasTable('platform_monthly_financial_summaries')) {
            return null;
        }

        $year  = (int) substr($billingMonth, 0, 4);
        $month = (int) substr($billingMonth, 5, 2);

        $row = DB::table('platform_monthly_financial_summaries')
            ->where('report_year', $year)
            ->where('report_month', $month)
            ->where('report_status', 'locked')
            ->whereNotNull('tenant_billing_snapshots')
            ->first();

        if (! $row) {
            return null;
        }

        $snapshot = json_decode((string) $row->tenant_billing_snapshots, true);
        return is_array($snapshot) ? $snapshot : null;
    }

    /**
     * Level 1: Date-aware policy resolution.
     * Only considers policies that were created on or before the end of the billing period.
     * This prevents retroactively-created policies (e.g., created in March for January) from
     * altering historical invoice tax calculations.
     */
    private function resolvePolicy(int $companyId, string $billingMonth): ?HcmBillingTaxPolicy
    {
        $periodEnd = date('Y-m-t', strtotime($billingMonth . '-01'));

        return HcmBillingTaxPolicy::query()
            ->where('company_id', $companyId)
            ->where('billing_month', $billingMonth)
            ->where('status', 'active')
            ->where(function ($q) use ($periodEnd): void {
                $q->whereNull('effective_from')
                  ->orWhere('effective_from', '<=', $periodEnd);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('created_at')
            ->first();
    }

    private function resolveNextRenewalMonth(string $billingMonth, ?string $billingCycleType): ?string
    {
        $baseDate = strtotime($billingMonth . '-01');
        if ($baseDate === false) {
            return null;
        }

        return match ($billingCycleType) {
            'yearly' => date('Y-m', strtotime('+1 year', $baseDate)),
            'custom' => null,
            default => date('Y-m', strtotime('+1 month', $baseDate)),
        };
    }
}
