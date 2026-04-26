<?php

namespace App\Services;

use App\Models\Company;
use App\Models\HcmBillingTaxPolicy;
use App\Models\Invoice;

class BillingTaxCalculationService
{
    public function calculateBillingTax(int $companyId, string $billingMonth): array
    {
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
        $taxRatePercentage = (float) ($policy?->tax_rate_percentage ?? 0);
        $taxAmount = round($totalInvoiceAmount * ($taxRatePercentage / 100), 2);

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

        foreach ($tenantIds as $tenantId) {
            $calc = $this->calculateBillingTax($tenantId, $billingMonth);
            $company = Company::query()->find($tenantId);

            if (!empty($calc['policy_uuid'])) {
                $tenantCountWithPolicy++;
            }

            $totalInvoiceAmount += (float) $calc['total_invoice_amount'];
            $totalTaxDue += (float) $calc['tax_amount'];
            $unpaidInvoiceCount += (int) $calc['unpaid_invoice_count'];

            $tenantRows[] = [
                'company_id' => $tenantId,
                'company_name' => $company?->name,
                'billing_month' => $billingMonth,
                'policy_uuid' => $calc['policy_uuid'],
                'tax_rate_percentage' => (float) $calc['tax_rate_percentage'],
                'invoice_count' => (int) $calc['invoice_count'],
                'paid_invoice_count' => (int) $calc['paid_invoice_count'],
                'unpaid_invoice_count' => (int) $calc['unpaid_invoice_count'],
                'total_invoice_amount' => (float) $calc['total_invoice_amount'],
                'tax_amount_due' => (float) $calc['tax_amount'],
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
                'total_tax_due' => round($totalTaxDue, 2),
                'unpaid_invoice_count' => $unpaidInvoiceCount,
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
