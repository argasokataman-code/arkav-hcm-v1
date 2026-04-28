<?php

namespace App\Services;

use App\Models\Company;
use App\Models\HcmBillingTaxPolicy;
use App\Models\Invoice;
use App\Models\PlatformRevenueTransaction;

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
        $taxAmount = round($taxableRevenueAmount * ($taxRatePercentage / 100), 2);

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

    public function generateCrossTenantMonthlyReport(string $billingMonth): array
    {
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
        $totalPayrollServiceFee = 0.0;
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
            $payrollServiceFee = (float) ((clone $transactionBase)
                ->where('transaction_type', PlatformRevenueTransaction::TYPE_PAYROLL_SERVICE)
                ->sum('amount') ?? 0);
            $addonRevenue = (float) ((clone $transactionBase)
                ->where('transaction_type', PlatformRevenueTransaction::TYPE_ADDON_FEATURE)
                ->sum('amount') ?? 0);

            $grossRevenue = round($subscriptionRevenue + $payrollServiceFee + $addonRevenue, 2);
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
            $totalPayrollServiceFee += $payrollServiceFee;
            $totalAddonRevenue += $addonRevenue;
            $totalGrossRevenue += $effectiveGrossRevenue;
            $totalNetRevenue += $netRevenue;

            $tenantRows[] = [
                'company_id' => $tenantId,
                'company_name' => $company?->name,
                'billing_month' => $billingMonth,
                'policy_uuid' => $calc['policy_uuid'],
                'plan_name' => '-',
                'tax_rate_percentage' => (float) $calc['tax_rate_percentage'],
                'invoice_count' => (int) $calc['invoice_count'],
                'paid_invoice_count' => (int) $calc['paid_invoice_count'],
                'unpaid_invoice_count' => (int) $calc['unpaid_invoice_count'],
                'total_invoice_amount' => (float) $calc['total_invoice_amount'],
                'subscription_revenue' => round($subscriptionRevenue, 2),
                'payroll_service_fee' => round($payrollServiceFee, 2),
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
                'total_payroll_service_fee' => round($totalPayrollServiceFee, 2),
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

    private function resolvePolicy(int $companyId, string $billingMonth): ?HcmBillingTaxPolicy
    {
        return HcmBillingTaxPolicy::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('billing_month', $billingMonth)
            ->orderByDesc('effective_from')
            ->orderByDesc('created_at')
            ->first();
    }
}
