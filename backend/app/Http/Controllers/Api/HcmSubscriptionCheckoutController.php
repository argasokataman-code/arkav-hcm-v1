<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ChecksPermissions;
use App\Http\Controllers\Api\Concerns\EnsuresHcmAdmin;
use App\Jobs\SendInvoiceEmailJob;
use App\Models\Company;
use App\Models\HcmBillingTaxPolicy;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HcmSubscriptionCheckoutController
{
    use ChecksPermissions;
    use EnsuresHcmAdmin;

    /**
     * POST /v1/hcm/billing/checkout
     *
     * Tenant checkout: create (or reuse) a pending_payment subscription + invoice for the active company.
     */
    public function checkout(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureHcmAdmin($request)) {
            return $forbidden;
        }

        $activeCompanyId = (int) ($request->attributes->get('activeCompanyId') ?? 0);
        if ($activeCompanyId <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TENANT_CONTEXT_REQUIRED',
                    'message' => 'Active company context is required.',
                ],
            ], 422);
        }

        $validated = $request->validate([
            'package_uuid' => [
                'required',
                'uuid',
                Rule::exists('packages', 'uuid')->where(fn ($q) => $q->where('status', 'active')),
            ],
            'billing_cycle' => ['required', 'string', Rule::in(['monthly', 'yearly'])],
            'billingEmail' => ['nullable', 'string', 'email:rfc', 'max:255'],
        ]);

        /** @var Package $package */
        $package = Package::query()->where('uuid', $validated['package_uuid'])->firstOrFail();

        if ((string) $package->code === 'trial') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Trial package cannot be used for checkout.',
                ],
            ], 422);
        }

        /** @var Company $company */
        $company = Company::query()->findOrFail($activeCompanyId);
        $billingCycle = (string) $validated['billing_cycle'];
        $baseAmount = $billingCycle === 'yearly' ? (float) $package->yearly_price : (float) $package->monthly_price;
        $pricingBreakdown = $this->buildSubscriptionPricingBreakdown($company->id, $baseAmount);
        $amountDue = (float) $pricingBreakdown['total_amount'];

        // 24-hour payment window by default.
        $dueDate = now()->addDay()->toDateString();

        return DB::transaction(function () use ($company, $package, $billingCycle, $baseAmount, $amountDue, $pricingBreakdown, $dueDate, $validated): JsonResponse {
            // Global guard: if there is ANY unpaid invoice for this company, reuse it and
            // never create a duplicate — regardless of subscription status.
            $anyUnpaid = Invoice::query()
                ->where('company_id', $company->id)
                ->where('is_paid', false)
                ->whereIn('status', ['draft', 'sent'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($anyUnpaid) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'invoice' => [
                            'id' => $anyUnpaid->id,
                            'invoiceNumber' => $anyUnpaid->invoice_number,
                            'issueDate' => $anyUnpaid->issue_date?->toDateString(),
                            'dueDate' => $anyUnpaid->due_date?->toDateString(),
                            'amountDue' => (float) $anyUnpaid->amount_due,
                            'isPaid' => (bool) $anyUnpaid->is_paid,
                            'status' => (string) $anyUnpaid->status,
                        ],
                        'reused' => true,
                    ],
                ]);
            }

            // Reuse an existing unpaid invoice for an existing pending_payment subscription (if still valid).
            $existingPending = Subscription::query()
                ->where('company_id', $company->id)
                ->where('status', 'pending_payment')
                ->latest('id')
                ->first();

            if ($existingPending) {
                $existingInvoice = Invoice::query()
                    ->where('company_id', $company->id)
                    ->where('subscription_id', $existingPending->id)
                    ->where('is_paid', false)
                    ->whereIn('status', ['draft', 'sent'])
                    ->latest('id')
                    ->first();

                if ($existingInvoice && $existingInvoice->due_date && $existingInvoice->due_date->toDateString() >= now()->toDateString()) {
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'subscription' => [
                                'id' => $existingPending->id,
                                'status' => $existingPending->status,
                                'billingCycle' => $existingPending->billing_cycle,
                                'amount' => (float) $existingPending->amount,
                                'packageId' => $existingPending->package_uuid,
                                'packageCode' => $existingPending->plan_code,
                            ],
                            'invoice' => [
                                'id' => $existingInvoice->id,
                                'invoiceNumber' => $existingInvoice->invoice_number,
                                'issueDate' => $existingInvoice->issue_date?->toDateString(),
                                'dueDate' => $existingInvoice->due_date?->toDateString(),
                                'amountDue' => (float) $existingInvoice->amount_due,
                                'isPaid' => (bool) $existingInvoice->is_paid,
                                'status' => (string) $existingInvoice->status,
                            ],
                            'reused' => true,
                        ],
                    ]);
                }
            }

            // If there is an active trial subscription, convert it into pending_payment (upgrade path).
            $trialSub = Subscription::query()
                ->where('company_id', $company->id)
                ->where('status', 'trial')
                ->latest('id')
                ->first();

            $subscription = $trialSub ?: new Subscription();
            if (! $subscription->exists) {
                $subscription->company_id = $company->id;
                $subscription->starts_at = now();
                $subscription->auto_renew = false;
            }

            $subscription->package_uuid = $package->uuid;
            $subscription->plan_code = $package->code;
            $subscription->status = 'pending_payment';
            $subscription->billing_cycle = $billingCycle;
            $subscription->amount = $baseAmount;
            $subscription->trial_ends_at = null;
            $subscription->ends_at = now()->addHours(24);
            $subscription->save();

            $invoice = Invoice::query()->create([
                'company_id' => $company->id,
                'subscription_id' => $subscription->id,
                'purchase_transaction_id' => null,
                'issue_date' => now()->toDateString(),
                'due_date' => $dueDate,
                'amount_due' => $amountDue,
                'status' => 'draft',
                'notes' => $this->buildInvoicePricingNotes(
                    'tenant_subscription_checkout',
                    $pricingBreakdown,
                    'Created from tenant subscription checkout.'
                ),
            ]);

            $billingEmail = $validated['billingEmail'] ?? null;
            SendInvoiceEmailJob::dispatch($invoice->id, $billingEmail)->afterCommit();

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription' => [
                        'id' => $subscription->id,
                        'status' => $subscription->status,
                        'billingCycle' => $subscription->billing_cycle,
                        'amount' => (float) $subscription->amount,
                        'packageId' => $package->uuid,
                        'packageCode' => $package->code,
                        'packageName' => $package->name,
                    ],
                    'invoice' => [
                        'id' => $invoice->id,
                        'invoiceNumber' => $invoice->invoice_number,
                        'issueDate' => $invoice->issue_date?->toDateString(),
                        'dueDate' => $invoice->due_date?->toDateString(),
                        'amountDue' => (float) $invoice->amount_due,
                        'isPaid' => (bool) $invoice->is_paid,
                        'status' => (string) $invoice->status,
                    ],
                    'reused' => false,
                ],
            ], 201);
        });
    }

    private function buildSubscriptionPricingBreakdown(int $companyId, float $baseAmount): array
    {
        $billingMonth = now()->format('Y-m');

        $policy = HcmBillingTaxPolicy::query()
            ->where('company_id', $companyId)
            ->where('billing_month', $billingMonth)
            ->where('status', 'active')
            ->orderByDesc('effective_from')
            ->orderByDesc('created_at')
            ->first();

        if (! $policy) {
            $globalPolicyCandidates = HcmBillingTaxPolicy::query()
                ->where('billing_month', $billingMonth)
                ->where('status', 'active')
                ->orderByDesc('effective_from')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            foreach ($globalPolicyCandidates as $candidate) {
                $decoded = json_decode((string) ($candidate->notes ?? ''), true);
                if (is_array($decoded) && isset($decoded['global_rates']) && is_array($decoded['global_rates'])) {
                    $policy = $candidate;
                    break;
                }
            }
        }

        $defaultSubscriptionTaxRate = (float) ($policy?->tax_rate_percentage ?? 0);
        [$components, $serviceFeeRate, $serviceFeeAmount, $subscriptionTaxRate, $subscriptionTaxAmount] =
            $this->resolvePricingComponents($policy, $baseAmount, $defaultSubscriptionTaxRate);

        $totalAdjustments = round((float) collect($components)->sum(fn (array $component): float => (float) ($component['amount'] ?? 0)), 2);
        $totalAmount = round($baseAmount + $totalAdjustments, 2);

        return [
            'billing_month' => $billingMonth,
            'policy_id' => $policy?->id,
            'base_amount' => round($baseAmount, 2),
            'components' => $components,
            'total_adjustments' => $totalAdjustments,
            'service_fee_rate' => $serviceFeeRate,
            'service_fee_amount' => $serviceFeeAmount,
            'subscription_tax_rate' => $subscriptionTaxRate,
            'subscription_tax_amount' => $subscriptionTaxAmount,
            'total_amount' => $totalAmount,
        ];
    }

    private function resolvePricingComponents(?HcmBillingTaxPolicy $policy, float $baseAmount, float $defaultSubscriptionTaxRate): array
    {
        $notes = json_decode((string) ($policy?->notes ?? ''), true);
        $globalRates = is_array($notes) && isset($notes['global_rates']) && is_array($notes['global_rates'])
            ? $notes['global_rates']
            : [];
        $customLabels = is_array($notes) && isset($notes['global_rate_labels']) && is_array($notes['global_rate_labels'])
            ? $notes['global_rate_labels']
            : [];

        $resolvedRates = [];
        foreach ($globalRates as $key => $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $componentKey = Str::snake((string) $key);
            if ($componentKey === '') {
                continue;
            }

            $resolvedRates[$componentKey] = (float) $value;
        }

        if (! array_key_exists('subscription_tax_rate', $resolvedRates)) {
            $resolvedRates['subscription_tax_rate'] = $defaultSubscriptionTaxRate;
        }

        $defaultLabels = [
            'subscription_tax_rate' => 'Pajak langganan',
            'payroll_service_fee' => 'Biaya layanan',
            'addon_markup_rate' => 'Corporate tax',
        ];

        $components = [];
        foreach ($resolvedRates as $componentKey => $rate) {
            $amount = round($baseAmount * ($rate / 100), 2);
            $label = $customLabels[$componentKey] ?? $defaultLabels[$componentKey] ?? Str::title(str_replace('_', ' ', $componentKey));

            $components[] = [
                'key' => $componentKey,
                'label' => (string) $label,
                'rate' => $rate,
                'amount' => $amount,
            ];
        }

        $serviceFeeRate = 0.0;
        $serviceFeeAmount = 0.0;
        $subscriptionTaxRate = 0.0;
        $subscriptionTaxAmount = 0.0;
        foreach ($components as $component) {
            if (($component['key'] ?? null) === 'payroll_service_fee') {
                $serviceFeeRate = (float) ($component['rate'] ?? 0);
                $serviceFeeAmount = (float) ($component['amount'] ?? 0);
            }
            if (($component['key'] ?? null) === 'subscription_tax_rate') {
                $subscriptionTaxRate = (float) ($component['rate'] ?? 0);
                $subscriptionTaxAmount = (float) ($component['amount'] ?? 0);
            }
        }

        return [$components, $serviceFeeRate, $serviceFeeAmount, $subscriptionTaxRate, $subscriptionTaxAmount];
    }

    private function buildInvoicePricingNotes(string $source, array $pricingBreakdown, string $fallbackMessage): string
    {
        $payload = [
            'source' => $source,
            'message' => $fallbackMessage,
            'pricing_breakdown' => $pricingBreakdown,
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? $encoded : $fallbackMessage;
    }
}
