<?php

namespace App\Jobs;

use App\Models\HcmBillingTaxPolicy;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\BillingTaxCalculationService;
use App\Services\NotificationService;
use App\Services\StripeService;
use App\Services\XenditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessRecurringSubscriptionBilling implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     * 
     * This job runs daily to:
     * 1. Check subscriptions expiring in 7 days and send reminders
     * 2. Create renewal invoices for subscriptions ending today
     * 3. Attempt payment collection using configured gateway
     */
    public function handle(): void
    {
        Log::info('Starting recurring subscription billing processing');

        $today = now()->startOfDay();
        $expiringIn7Days = $today->clone()->addDays(7);

        try {
            // 1. Send expiration reminders (7 days before)
            $this->sendExpirationReminders($expiringIn7Days);

            // 2. Process subscriptions expiring today
            $this->processExpiringSubscriptions($today);

            // 3. Process subscriptions that need renewal
            $this->processSubscriptionRenewals($today);

            Log::info('Recurring subscription billing processing completed successfully');
        } catch (\Exception $e) {
            Log::error('Recurring subscription billing processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Send renewal reminders for subscriptions expiring in 7 days
     */
    private function sendExpirationReminders($expiringDate): void
    {
        $subscriptions = Subscription::where('status', 'active')
            ->whereDate('ends_at', $expiringDate->toDateString())
            ->with(['company'])
            ->get();

        Log::info('Found subscriptions expiring in 7 days', ['count' => $subscriptions->count()]);

        foreach ($subscriptions as $subscription) {
            try {
                // Send notification to company billing contact
                $notificationService = new NotificationService();
                $notificationService->notifySubscriptionExpiringIn7Days($subscription);

                Log::info('Sent expiration reminder', [
                    'subscription_id' => $subscription->id,
                    'company_id' => $subscription->company_id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send expiration reminder', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process subscriptions expiring today
     */
    private function processExpiringSubscriptions($today): void
    {
        $subscriptions = Subscription::where('status', 'active')
            ->whereDate('ends_at', $today->toDateString())
            ->with(['company', 'package'])
            ->get();

        Log::info('Found subscriptions expiring today', ['count' => $subscriptions->count()]);

        foreach ($subscriptions as $subscription) {
            try {
                // Check if auto-renewal is enabled
                if ($subscription->auto_renew) {
                    $this->createRenewalInvoice($subscription);
                } else {
                    // Mark as expired
                    $subscription->update(['status' => 'expired']);
                    Log::info('Subscription expired (auto-renewal disabled)', [
                        'subscription_id' => $subscription->id,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to process expiring subscription', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process subscription renewals
     */
    private function processSubscriptionRenewals($today): void
    {
        // Find due renewal invoices created by this recurring renewal job.
        $invoices = Invoice::query()
            ->where('is_paid', false)
            ->whereIn('status', ['draft', 'issued', 'sent'])
            ->whereDate('due_date', '<=', $today->toDateString())
            ->whereNotNull('subscription_id')
            ->where('notes', 'like', '%"source":"recurring_subscription_renewal"%')
            ->with(['subscription.company', 'subscription.package', 'company'])
            ->get();

        Log::info('Found renewal invoices to process', ['count' => $invoices->count()]);

        foreach ($invoices as $invoice) {
            try {
                $this->attemptPaymentCollection($invoice);
            } catch (\Exception $e) {
                Log::error('Failed to process renewal invoice', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Create renewal invoice for a subscription
     */
    private function createRenewalInvoice(Subscription $subscription): void
    {
        $company = $subscription->company;
        $package = $subscription->package;
        $billingCycle = $subscription->billing_cycle ?? 'monthly';

        // Calculate next billing period
        $currentEnd = $subscription->ends_at;
        $nextEnd = match ($billingCycle) {
            'yearly' => $currentEnd->clone()->addYear(),
            'quarterly' => $currentEnd->clone()->addMonths(3),
            default => $currentEnd->clone()->addMonth(), // monthly
        };

        $baseAmount = (float) ($subscription->amount ?? 0);
        if ($baseAmount <= 0 && $package) {
            $baseAmount = $billingCycle === 'yearly'
                ? (float) $package->yearly_price
                : (float) $package->monthly_price;
        }

        $pricingBreakdown = $this->buildSubscriptionPricingBreakdown((int) $company->id, $baseAmount);
        $amountDue = (float) ($pricingBreakdown['total_amount'] ?? $baseAmount);

        $taxRateSnapshot = app(BillingTaxCalculationService::class)
            ->resolvePolicyRateSnapshot((int) $company->id, now()->format('Y-m'));

        // Idempotency: avoid duplicate renewal invoices for the same subscription/day.
        $existingUnpaidRenewal = Invoice::query()
            ->where('company_id', $company->id)
            ->where('subscription_id', $subscription->id)
            ->where('is_paid', false)
            ->where('notes', 'like', '%"source":"recurring_subscription_renewal"%')
            ->whereDate('issue_date', now()->toDateString())
            ->exists();

        if ($existingUnpaidRenewal) {
            Log::info('Skipped duplicate renewal invoice creation', [
                'subscription_id' => $subscription->id,
                'company_id' => $company->id,
            ]);
            return;
        }

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => $amountDue,
            'billing_tax_rate_snapshot' => $taxRateSnapshot > 0 ? $taxRateSnapshot : null,
            'status' => 'draft',
            'notes' => $this->buildInvoicePricingNotes('recurring_subscription_renewal', array_merge(
                $pricingBreakdown,
                [
                    'package_name' => $package?->name,
                    'billing_cycle' => $billingCycle,
                    'billing_period_start' => $currentEnd->toDateString(),
                    'billing_period_end' => $nextEnd->toDateString(),
                ]
            ), 'Auto-generated recurring renewal invoice.'),
        ]);

        Log::info('Renewal invoice created', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $subscription->id,
            'amount_due' => $invoice->amount_due,
            'company_id' => $company->id,
        ]);

        if ((float) $invoice->amount_due <= 0) {
            $invoice->markAsPaid();
            $this->extendSubscriptionPeriod($subscription);
            return;
        }

        // Send invoice to company
        $notificationService = new NotificationService();
        $notificationService->notifyInvoiceIssued($invoice);
    }

    /**
     * Attempt to collect payment for an invoice
     */
    private function attemptPaymentCollection(Invoice $invoice): void
    {
        $subscription = $invoice->subscription;
        if (! $subscription) {
            Log::warning('Skipping payment collection because subscription is missing', [
                'invoice_id' => $invoice->id,
            ]);
            return;
        }

        $gateway = $this->resolveGateway($subscription);
        $maxAttempts = 3;

        $payment = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->whereIn('status', ['pending', 'failed'])
            ->latest('id')
            ->first();

        if (! $payment) {
            $payment = Payment::query()->create([
                'company_id' => $invoice->company_id,
                'subscription_id' => $subscription->id,
                'purchase_transaction_id' => null,
                'invoice_id' => $invoice->id,
                'amount' => (float) $invoice->amount_due,
                'currency' => 'IDR',
                'status' => 'pending',
                'payment_method' => $this->resolvePaymentMethod($subscription),
                'gateway' => $gateway,
                'metadata' => [
                    'attempt_count' => 0,
                    'source' => 'recurring_subscription_renewal',
                ],
            ]);
        }

        $attemptCount = (int) (($payment->metadata['attempt_count'] ?? 0));

        // Check if max retries exceeded
        if ($attemptCount >= $maxAttempts) {
            $payment->update([
                'status' => 'failed',
                'metadata' => array_merge($payment->metadata ?? [], [
                    'attempt_count' => $attemptCount,
                    'failed_at' => now()->toIso8601String(),
                ]),
            ]);

            $invoice->update(['status' => 'overdue']);
            Log::warning('Payment collection abandoned after max attempts', [
                'invoice_id' => $invoice->id,
                'attempts' => $attemptCount,
            ]);

            // Send payment failure notification
            $notificationService = new NotificationService();
            $notificationService->notifyPaymentFailed($invoice);
            return;
        }

        try {
            // Attempt payment based on gateway
            $resultState = match ($gateway) {
                'xendit' => $this->chargeViaXendit($invoice, $subscription, $payment),
                'stripe' => $this->chargeViaStripe($invoice, $subscription, $payment),
                default => throw new \RuntimeException("Unsupported gateway: $gateway"),
            };

            if ($resultState === 'paid') {
                $payment->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                    'verified_at' => now(),
                    'amount' => (float) $invoice->amount_due,
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'attempt_count' => $attemptCount + 1,
                        'completed_at' => now()->toIso8601String(),
                    ]),
                ]);

                $invoice->markAsPaid();
                $this->extendSubscriptionPeriod($subscription);

                Log::info('Payment collected successfully', [
                    'invoice_id' => $invoice->id,
                    'gateway' => $gateway,
                ]);

                // Send payment receipt
                $notificationService = new NotificationService();
                $notificationService->notifyPaymentReceived($payment->fresh(), $invoice->fresh());
            } elseif ($resultState === 'pending') {
                $nextAttempt = now()->addHours(24)->toIso8601String();
                $payment->update([
                    'status' => 'pending',
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'next_attempt_at' => $nextAttempt,
                        'awaiting_gateway_settlement' => true,
                        'last_gateway_poll_at' => now()->toIso8601String(),
                    ]),
                ]);

                Log::info('Gateway charge created and awaiting settlement', [
                    'invoice_id' => $invoice->id,
                    'gateway' => $gateway,
                    'next_attempt' => $nextAttempt,
                ]);
            } else {
                $nextAttempt = now()->addHours(24)->toIso8601String();
                $payment->update([
                    'status' => 'pending',
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'attempt_count' => $attemptCount + 1,
                        'next_attempt_at' => $nextAttempt,
                    ]),
                ]);

                Log::warning('Payment collection attempt failed, will retry', [
                    'invoice_id' => $invoice->id,
                    'attempt' => $attemptCount + 1,
                    'next_attempt' => $nextAttempt,
                ]);
            }
        } catch (\Exception $e) {
            DB::table('payment_attempts')
                ->where('invoice_id', $invoice->id)
                ->update([
                    'attempt_count' => $attemptCount + 1,
                    'error_message' => $e->getMessage(),
                    'updated_at' => now(),
                ]);

            Log::error('Payment collection error', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Attempt charge via Stripe
     */
    private function chargeViaStripe(Invoice $invoice, Subscription $subscription, Payment $payment): string
    {
        try {
            $stripeService = app(StripeService::class);
            $customerId = $this->resolveStripeCustomerId($subscription);
            if ($customerId === null) {
                Log::warning('Stripe payment skipped because no customer reference was found', [
                    'invoice_id' => $invoice->id,
                    'subscription_id' => $subscription->id,
                ]);
                return 'failed';
            }
            
            // Use stored payment method or create one-time payment intent
            $result = $stripeService->createPaymentIntent([
                'customer_id' => $customerId,
                'amount' => (float) $invoice->amount_due,
                'currency' => 'IDR',
                'description' => 'Recurring renewal invoice '.$invoice->invoice_number,
                'invoice_id' => $invoice->id,
                'company_id' => $invoice->company_id,
            ]);

            $payment->update([
                'gateway_reference' => (string) ($result['id'] ?? ''),
                'metadata' => array_merge($payment->metadata ?? [], [
                    'stripe_intent_id' => $result['id'] ?? null,
                    'stripe_status' => $result['status'] ?? null,
                ]),
            ]);

            // Consider succeeded or requires_capture/processing as accepted charge creation.
            $accepted = in_array((string) ($result['status'] ?? ''), ['succeeded', 'processing', 'requires_capture'], true);

            if (! $accepted) {
                Log::warning('Stripe payment intent did not reach accepted status', [
                    'invoice_id' => $invoice->id,
                    'status' => $result['status'] ?? null,
                ]);
            }

            return $accepted ? 'paid' : 'failed';
        } catch (\Exception $e) {
            Log::error('Stripe payment attempt failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return 'failed';
        }
    }

    /**
     * Attempt charge via Xendit
     */
    private function chargeViaXendit(Invoice $invoice, Subscription $subscription, Payment $payment): string
    {
        try {
            $xenditService = app(XenditService::class);
            $metadata = is_array($payment->metadata) ? $payment->metadata : [];

            $company = $invoice->company;
            if (! $company || empty($company->email)) {
                Log::warning('Xendit payment skipped because company billing email is missing', [
                    'invoice_id' => $invoice->id,
                    'company_id' => $invoice->company_id,
                ]);
                return 'failed';
            }

            $existingInvoiceId = isset($metadata['xendit_invoice_id']) ? (string) $metadata['xendit_invoice_id'] : '';
            if ($existingInvoiceId !== '') {
                $existing = $xenditService->getInvoice($existingInvoiceId);
                $existingStatus = strtoupper((string) ($existing['status'] ?? ''));

                if (in_array($existingStatus, ['SETTLED', 'PAID'], true)) {
                    return 'paid';
                }

                if ($existingStatus !== '' && ! in_array($existingStatus, ['EXPIRED', 'FAILED'], true)) {
                    Log::info('Xendit renewal invoice still pending settlement', [
                        'invoice_id' => $invoice->id,
                        'xendit_invoice_id' => $existingInvoiceId,
                        'status' => $existingStatus,
                    ]);

                    return 'pending';
                }
            }

            $externalId = $this->buildRecurringExternalId($invoice);
            $result = $xenditService->createInvoice([
                'external_id' => $externalId,
                'amount' => (int) round((float) $invoice->amount_due),
                'description' => 'Recurring renewal invoice '.$invoice->invoice_number,
                'customer_name' => $company->name,
                'customer_email' => $company->email,
                'currency' => 'IDR',
                'success_url' => config('app.url') . '/billing/success',
                'failure_url' => config('app.url') . '/billing/failed',
            ]);

            if ($result && !empty($result['id'])) {
                $payment->update([
                    'gateway_reference' => (string) $result['id'],
                    'metadata' => array_merge($metadata, [
                        'xendit_invoice_id' => $result['id'],
                        'xendit_external_id' => $externalId,
                        'invoice_url' => $result['invoice_url'] ?? null,
                    ]),
                ]);

                Log::info('Xendit renewal invoice created', [
                    'invoice_id' => $invoice->id,
                    'xendit_invoice_id' => $result['id'],
                ]);

                return 'pending';
            }

            return 'failed';
        } catch (\Exception $e) {
            Log::error('Xendit payment attempt failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return 'failed';
        }
    }

    private function buildRecurringExternalId(Invoice $invoice): string
    {
        return "renewal-inv-{$invoice->id}";
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

        $defaultSubscriptionTaxRate = $this->resolveDefaultSubscriptionTaxRate($policy);
        [$components, $subscriptionTaxRate, $subscriptionTaxAmount] =
            $this->resolvePricingComponents($policy, $baseAmount, $defaultSubscriptionTaxRate);

        $totalAdjustments = round((float) collect($components)->sum(fn (array $component): float => (float) ($component['amount'] ?? 0)), 2);
        $totalAmount = round($baseAmount + $totalAdjustments, 2);

        return [
            'billing_month' => $billingMonth,
            'policy_id' => $policy?->id,
            'base_amount' => round($baseAmount, 2),
            'components' => $components,
            'total_adjustments' => $totalAdjustments,
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

            $componentKey = \Illuminate\Support\Str::snake((string) $key);
            if ($componentKey === '') {
                continue;
            }

            $resolvedRates[$componentKey] = (float) $value;
        }

        // Government compliance policy stores customer transaction tax in nested notes,
        // while global subscription_tax_rate is used for corporate tax reporting.
        if (is_array($notes) && (string) ($notes['source'] ?? '') === 'government_tax_compliance_policy') {
            $transactionTaxRate = $this->extractGovernmentTransactionTaxRate($notes);
            if ($transactionTaxRate !== null) {
                $resolvedRates['subscription_tax_rate'] = $transactionTaxRate;
            }
        }

        if (! array_key_exists('subscription_tax_rate', $resolvedRates)) {
            $resolvedRates['subscription_tax_rate'] = $defaultSubscriptionTaxRate;
        }

        $defaultLabels = [
            'subscription_tax_rate' => 'Pajak langganan',
            'addon_markup_rate' => 'Corporate tax',
        ];

        $components = [];
        foreach ($resolvedRates as $componentKey => $rate) {
            $amount = round($baseAmount * ($rate / 100), 2);
            $label = $customLabels[$componentKey] ?? $defaultLabels[$componentKey] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $componentKey));

            $components[] = [
                'key' => $componentKey,
                'label' => (string) $label,
                'rate' => $rate,
                'amount' => $amount,
            ];
        }

        $subscriptionTaxRate = 0.0;
        $subscriptionTaxAmount = 0.0;
        foreach ($components as $component) {
            if (($component['key'] ?? null) === 'subscription_tax_rate') {
                $subscriptionTaxRate = (float) ($component['rate'] ?? 0);
                $subscriptionTaxAmount = (float) ($component['amount'] ?? 0);
            }
        }

        return [$components, $subscriptionTaxRate, $subscriptionTaxAmount];
    }

    private function resolveDefaultSubscriptionTaxRate(?HcmBillingTaxPolicy $policy): float
    {
        $defaultRate = (float) ($policy?->tax_rate_percentage ?? 0);
        $notes = json_decode((string) ($policy?->notes ?? ''), true);

        if (! is_array($notes) || (string) ($notes['source'] ?? '') !== 'government_tax_compliance_policy') {
            return max(0.0, min(100.0, $defaultRate));
        }

        $transactionTaxRate = $this->extractGovernmentTransactionTaxRate($notes);
        if ($transactionTaxRate === null) {
            return max(0.0, min(100.0, $defaultRate));
        }

        return $transactionTaxRate;
    }

    private function extractGovernmentTransactionTaxRate(array $policyNotes): ?float
    {
        $rawNotes = $policyNotes['notes'] ?? null;
        $nestedNotes = is_array($rawNotes)
            ? $rawNotes
            : (is_string($rawNotes) ? json_decode($rawNotes, true) : null);

        if (! is_array($nestedNotes)) {
            return null;
        }

        $rate = $nestedNotes['transaction_tax']['tax_rate'] ?? null;
        if (! is_numeric($rate)) {
            return null;
        }

        return max(0.0, min(100.0, (float) $rate));
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

    private function resolveGateway(Subscription $subscription): string
    {
        $metadataGateway = is_array($subscription->metadata)
            ? (string) ($subscription->metadata['gateway'] ?? '')
            : '';

        return $metadataGateway !== '' ? $metadataGateway : 'stripe';
    }

    private function resolvePaymentMethod(Subscription $subscription): ?string
    {
        $metadataMethod = is_array($subscription->metadata)
            ? (string) ($subscription->metadata['payment_method'] ?? '')
            : '';

        return $metadataMethod !== '' ? $metadataMethod : 'credit_card';
    }

    private function resolveStripeCustomerId(Subscription $subscription): ?string
    {
        if (! is_array($subscription->metadata)) {
            return null;
        }

        $value = (string) ($subscription->metadata['stripe_customer_id'] ?? '');
        return $value !== '' ? $value : null;
    }

    private function extendSubscriptionPeriod(Subscription $subscription): void
    {
        $currentEnd = $subscription->ends_at ?? now();
        $billingCycle = $subscription->billing_cycle ?? 'monthly';

        $nextEnd = match ($billingCycle) {
            'yearly' => $currentEnd->clone()->addYear(),
            'quarterly' => $currentEnd->clone()->addMonths(3),
            default => $currentEnd->clone()->addMonth(),
        };

        $subscription->update([
            'status' => 'active',
            'ends_at' => $nextEnd,
        ]);
    }
}
