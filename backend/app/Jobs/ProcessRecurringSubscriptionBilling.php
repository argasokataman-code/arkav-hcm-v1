<?php

namespace App\Jobs;

use App\Models\HcmBillingTaxPolicy;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Services\BillingTaxCalculationService;
use App\Services\CompanyStatusSynchronizer;
use App\Services\MidtransService;
use App\Services\NotificationService;
use App\Services\StripeService;
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

    private const RETRY_SCHEDULE_HOURS = [1, 24, 72];
    private const FAILURE_SPIKE_THRESHOLD = 3;

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
            $this->emitRenewalAlert('worker_crash', 'RENEWAL_WORKER_CRASHED', $e->getMessage());
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
        $this->sendSuspensionWarnings();
        $this->escalateExpiredGracePeriods();

        // Find due renewal invoices created by this recurring renewal job.
        $invoices = Invoice::query()
            ->where('is_paid', false)
            ->whereIn('status', ['draft', 'issued', 'sent'])
            ->whereDate('due_date', '<=', $today->toDateString())
            ->whereNotNull('subscription_id')
            ->where(function ($query): void {
                $query->whereNull('renewal_reason_code')
                    ->orWhereNotIn('renewal_reason_code', ['AWAITING_GATEWAY_SETTLEMENT']);
            })
            ->where(function ($query): void {
                $query->whereNotNull('renewal_period_key')
                    ->orWhere('notes', 'like', '%"source":"recurring_subscription_renewal"%');
            })
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
        DB::transaction(function () use ($subscription): void {
            $lockedSubscription = Subscription::query()
                ->whereKey($subscription->getKey())
                ->lockForUpdate()
                ->with(['company', 'package'])
                ->first();

            if (! $lockedSubscription || ! $lockedSubscription->auto_renew) {
                return;
            }

            $company = $lockedSubscription->company;
            $package = $lockedSubscription->package;
            if (! $company) {
                return;
            }

            $billingCycle = $lockedSubscription->billing_cycle ?? 'monthly';
            $periodAnchor = $lockedSubscription->ends_at
                ?? $lockedSubscription->starts_at
                ?? $lockedSubscription->created_at
                ?? now();
            $currentEnd = $lockedSubscription->ends_at ?? $periodAnchor;
            $nextEnd = match ($billingCycle) {
                'yearly' => $currentEnd->clone()->addYear(),
                'quarterly' => $currentEnd->clone()->addMonths(3),
                default => $currentEnd->clone()->addMonth(), // monthly
            };

            $renewalPeriodKey = $this->buildRenewalPeriodKey($lockedSubscription, $periodAnchor);

            $baseAmount = (float) ($lockedSubscription->amount ?? 0);
            if ($baseAmount <= 0 && $package) {
                $baseAmount = $billingCycle === 'yearly'
                    ? (float) $package->yearly_price
                    : (float) $package->monthly_price;
            }

            $pricingBreakdown = $this->buildSubscriptionPricingBreakdown((int) $company->id, $baseAmount);
            $amountDue = (float) ($pricingBreakdown['total_amount'] ?? $baseAmount);

            $taxRateSnapshot = app(BillingTaxCalculationService::class)
                ->resolvePolicyRateSnapshot((int) $company->id, now()->format('Y-m'));

            // Idempotency: avoid duplicate renewal invoices for the same subscription/renewal period.
            $existingRenewal = Invoice::query()
                ->where('company_id', $company->id)
                ->where('subscription_id', $lockedSubscription->id)
                ->where('renewal_period_key', $renewalPeriodKey)
                ->exists();

            if ($existingRenewal) {
                Log::info('Skipped duplicate renewal invoice creation', [
                    'subscription_id' => $lockedSubscription->id,
                    'company_id' => $company->id,
                    'renewal_period_key' => $renewalPeriodKey,
                ]);
                return;
            }

            $invoice = Invoice::query()->create([
                'company_id' => $company->id,
                'subscription_id' => $lockedSubscription->id,
                'renewal_period_key' => $renewalPeriodKey,
                'purchase_transaction_id' => null,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'amount_due' => $amountDue,
                'billing_tax_rate_snapshot' => $taxRateSnapshot > 0 ? $taxRateSnapshot : null,
                'status' => 'draft',
                'renewal_reason_code' => 'RENEWAL_INVOICE_CREATED',
                'renewal_reason_message' => 'Renewal invoice created and waiting for payment attempt.',
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
                'subscription_id' => $lockedSubscription->id,
                'amount_due' => $invoice->amount_due,
                'company_id' => $company->id,
            ]);

            $this->recordSubscriptionEvent($lockedSubscription, $invoice, null, 'renewal_invoice_created', 'RENEWAL_INVOICE_CREATED', 'Renewal invoice created and waiting payment attempt.');

            if ((float) $invoice->amount_due <= 0) {
                $invoice->markAsPaid();
                $this->updateRenewalReason($invoice, 'ZERO_AMOUNT_AUTO_SETTLED', 'Renewal auto-settled because invoice amount is zero.');
                $this->recordSubscriptionEvent($lockedSubscription, $invoice, null, 'renewal_paid', 'ZERO_AMOUNT_AUTO_SETTLED', 'Renewal auto-settled because invoice amount is zero.');
                $this->extendSubscriptionPeriod($lockedSubscription);
                return;
            }

            // Send invoice to company
            $notificationService = new NotificationService();
            $notificationService->notifyInvoiceIssued($invoice);
        });
    }

    /**
     * Attempt to collect payment for an invoice
     */
    private function attemptPaymentCollection(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedInvoice || (bool) $lockedInvoice->is_paid) {
                return;
            }

            $subscription = Subscription::query()
                ->whereKey($lockedInvoice->subscription_id)
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                Log::warning('Skipping payment collection because subscription is missing', [
                    'invoice_id' => $lockedInvoice->id,
                ]);
                return;
            }

            $gateway = $this->resolveGateway($subscription);
            $maxAttempts = 3;

            $payment = Payment::query()
                ->where('invoice_id', $lockedInvoice->id)
                ->whereIn('status', ['pending', 'failed'])
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                $payment = Payment::query()->create([
                    'company_id' => $lockedInvoice->company_id,
                    'subscription_id' => $subscription->id,
                    'purchase_transaction_id' => null,
                    'invoice_id' => $lockedInvoice->id,
                    'amount' => (float) $lockedInvoice->amount_due,
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
            $nextAttemptAt = isset($payment->metadata['next_attempt_at'])
                ? \Illuminate\Support\Carbon::parse((string) $payment->metadata['next_attempt_at'])
                : null;

            if ($nextAttemptAt && $nextAttemptAt->isFuture()) {
                Log::info('Skipping payment attempt because next retry window has not arrived', [
                    'invoice_id' => $lockedInvoice->id,
                    'next_attempt_at' => $nextAttemptAt->toIso8601String(),
                ]);
                return;
            }

            // Check if max retries exceeded
            if ($attemptCount >= $maxAttempts) {
                $payment->update([
                    'status' => 'failed',
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'attempt_count' => $attemptCount,
                        'failed_at' => now()->toIso8601String(),
                    ]),
                ]);

                $lockedInvoice->update(['status' => 'overdue']);
                $this->updateRenewalReason($lockedInvoice, 'RENEWAL_MAX_RETRY_EXCEEDED', 'Renewal payment attempts exceeded max retry policy.');
                $this->moveSubscriptionToGracePeriod($subscription, $lockedInvoice, $payment);
                $this->emitRenewalMetric('renewal_retry_exhausted', 'RENEWAL_MAX_RETRY_EXCEEDED', $lockedInvoice, [
                    'attempt_count' => $attemptCount,
                ]);
                $this->emitFailureSpikeAlert($lockedInvoice, 'RENEWAL_MAX_RETRY_EXCEEDED');
                Log::warning('Payment collection abandoned after max attempts', [
                    'invoice_id' => $lockedInvoice->id,
                    'attempts' => $attemptCount,
                ]);

                // Send payment failure notification
                $notificationService = new NotificationService();
                $notificationService->notifyPaymentFailed($lockedInvoice);
                return;
            }

            try {
                // Attempt payment based on gateway
                $result = match ($gateway) {
                    'stripe'   => $this->chargeViaStripe($lockedInvoice, $subscription, $payment),
                    'midtrans' => $this->chargeViaMidtrans($lockedInvoice, $subscription, $payment),
                    default    => throw new \RuntimeException("Unsupported gateway: $gateway"),
                };

                $resultState = $result['state'] ?? 'failed';

                if ($resultState === 'paid') {
                    $payment->update([
                        'status' => 'completed',
                        'paid_at' => now(),
                        'verified_at' => now(),
                        'amount' => (float) $lockedInvoice->amount_due,
                        'metadata' => array_merge($payment->metadata ?? [], [
                            'attempt_count' => $attemptCount + 1,
                            'completed_at' => now()->toIso8601String(),
                        ]),
                    ]);

                    $lockedInvoice->markAsPaid();
                    $this->updateRenewalReason($lockedInvoice, 'RENEWAL_PAYMENT_CONFIRMED', 'Renewal payment confirmed and invoice marked paid.');
                    $this->recordSubscriptionEvent($subscription, $lockedInvoice, $payment, 'renewal_paid', 'RENEWAL_PAYMENT_CONFIRMED', 'Renewal payment confirmed and invoice marked paid.');
                    $this->extendSubscriptionPeriod($subscription);

                    Log::info('Payment collected successfully', [
                        'invoice_id' => $lockedInvoice->id,
                        'gateway' => $gateway,
                    ]);

                    // Send payment receipt
                    $notificationService = new NotificationService();
                    $notificationService->notifyPaymentReceived($payment->fresh(), $lockedInvoice->fresh());
                } elseif ($resultState === 'pending') {
                    $nextAttempt = $this->resolveNextAttemptAt($attemptCount + 1)->toIso8601String();
                    $payment->update([
                        'status' => 'pending',
                        'metadata' => array_merge($payment->metadata ?? [], [
                            'attempt_count' => $attemptCount + 1,
                            'next_attempt_at' => $nextAttempt,
                            'awaiting_gateway_settlement' => true,
                            'last_gateway_poll_at' => now()->toIso8601String(),
                        ]),
                    ]);
                    $this->updateRenewalReason($lockedInvoice, 'AWAITING_GATEWAY_SETTLEMENT', 'Gateway charge is created and waiting settlement callback.');
                    $this->recordSubscriptionEvent($subscription, $lockedInvoice, $payment, 'renewal_retry_attempted', 'AWAITING_GATEWAY_SETTLEMENT', 'Gateway charge created and waiting settlement callback.');

                    Log::info('Gateway charge created and awaiting settlement', [
                        'invoice_id' => $lockedInvoice->id,
                        'gateway' => $gateway,
                        'next_attempt' => $nextAttempt,
                    ]);
                } else {
                    $nextAttempt = $this->resolveNextAttemptAt($attemptCount + 1)->toIso8601String();
                    $reasonCode = (string) ($result['reason_code'] ?? 'RENEWAL_RETRY_SCHEDULED');
                    $reasonMessage = (string) ($result['reason_message'] ?? 'Renewal charge failed, next retry has been scheduled.');
                    $payment->update([
                        'status' => 'pending',
                        'metadata' => array_merge($payment->metadata ?? [], [
                            'attempt_count' => $attemptCount + 1,
                            'next_attempt_at' => $nextAttempt,
                        ]),
                    ]);
                    $this->updateRenewalReason($lockedInvoice, $reasonCode, $reasonMessage);
                    $this->recordSubscriptionEvent(
                        $subscription,
                        $lockedInvoice,
                        $payment,
                        $reasonCode === 'RENEWAL_RETRY_SCHEDULED' ? 'renewal_retry_attempted' : 'renewal_anomaly',
                        $reasonCode,
                        $reasonMessage
                    );
                    $this->emitRenewalMetric('renewal_attempt_failed', $reasonCode, $lockedInvoice, [
                        'attempt_count' => $attemptCount + 1,
                        'next_attempt_at' => $nextAttempt,
                        'gateway' => $gateway,
                    ]);
                    if ($reasonCode !== 'RENEWAL_RETRY_SCHEDULED') {
                        $this->emitRenewalAlert('gateway_down', $reasonCode, $reasonMessage, $lockedInvoice, [
                            'attempt_count' => $attemptCount + 1,
                            'gateway' => $gateway,
                        ]);
                    }
                    $this->emitFailureSpikeAlert($lockedInvoice, $reasonCode);

                    Log::warning('Payment collection attempt failed, will retry', [
                        'invoice_id' => $lockedInvoice->id,
                        'attempt' => $attemptCount + 1,
                        'next_attempt' => $nextAttempt,
                    ]);
                }
            } catch (\Exception $e) {
                $nextAttempt = $this->resolveNextAttemptAt($attemptCount + 1)->toIso8601String();

                $payment->update([
                    'metadata' => array_merge($payment->metadata ?? [], [
                        'attempt_count' => $attemptCount + 1,
                        'next_attempt_at' => $nextAttempt,
                        'last_error' => $e->getMessage(),
                    ]),
                ]);

                Log::error('Payment collection error', [
                    'invoice_id' => $lockedInvoice->id,
                    'error' => $e->getMessage(),
                ]);
                $this->updateRenewalReason($lockedInvoice, 'RENEWAL_PROCESS_EXCEPTION', $e->getMessage());
                $this->recordSubscriptionEvent($subscription, $lockedInvoice, $payment, 'renewal_exception', 'RENEWAL_PROCESS_EXCEPTION', $e->getMessage());
                $this->emitRenewalMetric('renewal_process_exception', 'RENEWAL_PROCESS_EXCEPTION', $lockedInvoice, [
                    'attempt_count' => $attemptCount + 1,
                ]);
                $this->emitRenewalAlert('worker_crash', 'RENEWAL_PROCESS_EXCEPTION', $e->getMessage(), $lockedInvoice, [
                    'attempt_count' => $attemptCount + 1,
                ]);
                $this->emitFailureSpikeAlert($lockedInvoice, 'RENEWAL_PROCESS_EXCEPTION');
            }
        });
    }

    private function buildRenewalPeriodKey(Subscription $subscription, $periodAnchor): string
    {
        $period = $periodAnchor->format('Y_m');

        return sprintf('sub_%d_%s', (int) $subscription->id, $period);
    }

    private function updateRenewalReason(Invoice $invoice, string $reasonCode, string $reasonMessage): void
    {
        $invoice->forceFill([
            'renewal_reason_code' => $reasonCode,
            'renewal_reason_message' => mb_substr($reasonMessage, 0, 255),
        ])->save();
    }

    private function resolveNextAttemptAt(int $attemptNumber): \Illuminate\Support\Carbon
    {
        $index = max(1, min($attemptNumber, count(self::RETRY_SCHEDULE_HOURS))) - 1;
        $hours = self::RETRY_SCHEDULE_HOURS[$index];

        return now()->addHours($hours);
    }

    private function moveSubscriptionToGracePeriod(Subscription $subscription, Invoice $invoice, Payment $payment): void
    {
        if (in_array($subscription->status, ['suspended', 'cancelled', 'expired'], true)) {
            return;
        }

        $graceStartedAt = now();
        $graceDays = max(1, (int) config('hcm.saas.renewal_grace_period_days', 3));
        $graceEndsAt = $graceStartedAt->copy()->addDays($graceDays);

        $subscription->forceFill([
            'status' => 'grace_period',
            'grace_started_at' => $graceStartedAt,
            'grace_ends_at' => $graceEndsAt,
        ])->save();

        $this->recordSubscriptionEvent(
            $subscription,
            $invoice,
            $payment,
            'grace_started',
            'RENEWAL_GRACE_PERIOD_STARTED',
            'Subscription entered grace period after retry policy was exhausted.',
            ['grace_ends_at' => $graceEndsAt->toIso8601String()]
        );

        $notificationService = new NotificationService();
        $notificationService->notifyGracePeriodStarted($subscription->fresh(['company']), $invoice->fresh(['company']));
    }

    private function sendSuspensionWarnings(): void
    {
        $warningDate = now()->addDay()->toDateString();

        Subscription::query()
            ->where('status', 'grace_period')
            ->whereNotNull('grace_ends_at')
            ->whereDate('grace_ends_at', $warningDate)
            ->with(['company'])
            ->chunkById(100, function ($subscriptions) use ($warningDate): void {
                foreach ($subscriptions as $subscription) {
                    $metadata = is_array($subscription->metadata) ? $subscription->metadata : [];
                    $lastWarningDate = (string) ($metadata['suspension_warning_sent_for'] ?? '');

                    if ($lastWarningDate === $warningDate) {
                        continue;
                    }

                    $notificationService = new NotificationService();
                    $notificationService->notifySuspensionWarning($subscription);

                    $subscription->forceFill([
                        'metadata' => array_merge($metadata, [
                            'suspension_warning_sent_for' => $warningDate,
                            'suspension_warning_sent_at' => now()->toIso8601String(),
                        ]),
                    ])->save();
                }
            });
    }

    private function escalateExpiredGracePeriods(): void
    {
        Subscription::query()
            ->where('status', 'grace_period')
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', now())
            ->chunkById(100, function ($subscriptions): void {
                $companyStatusSynchronizer = app(CompanyStatusSynchronizer::class);

                foreach ($subscriptions as $subscription) {
                    $subscription->forceFill([
                        'status' => 'inactive',
                        'suspended_at' => now(),
                        'suspension_reason' => 'Grace period expired without successful renewal payment.',
                    ])->save();

                    $companyStatusSynchronizer->syncFromSubscription($subscription->fresh('company'));

                    $this->recordSubscriptionEvent(
                        $subscription,
                        null,
                        null,
                        'inactive',
                        'RENEWAL_GRACE_EXPIRED',
                        'Subscription inactivated because grace period expired without successful renewal.'
                    );

                    $notificationService = new NotificationService();
                    $notificationService->notifySubscriptionSuspended($subscription->fresh(['company']));
                }
            });
    }

    private function recordSubscriptionEvent(
        Subscription $subscription,
        ?Invoice $invoice,
        ?Payment $payment,
        string $eventType,
        ?string $reasonCode = null,
        ?string $reasonMessage = null,
        array $payload = []
    ): void {
        SubscriptionEvent::query()->create([
            'company_id' => $subscription->company_id,
            'company_uuid' => $subscription->getAttribute('company_uuid'),
            'subscription_id' => $subscription->id,
            'subscription_uuid' => $subscription->uuid ?? null,
            'invoice_id' => $invoice?->id,
            'invoice_uuid' => $invoice?->uuid,
            'payment_id' => $payment?->id,
            'payment_uuid' => $payment?->uuid,
            'renewal_period_key' => $invoice?->renewal_period_key,
            'event_type' => $eventType,
            'reason_code' => $reasonCode,
            'reason_message' => $reasonMessage !== null ? mb_substr($reasonMessage, 0, 255) : null,
            'payload' => $payload === [] ? null : $payload,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }

    /**
     * Attempt charge via Stripe
     */
    private function chargeViaStripe(Invoice $invoice, Subscription $subscription, Payment $payment): array
    {
        try {
            $stripeService = app(StripeService::class);
            $customerId = $this->resolveStripeCustomerId($subscription);
            if ($customerId === null) {
                Log::warning('Stripe payment skipped because no customer reference was found', [
                    'invoice_id' => $invoice->id,
                    'subscription_id' => $subscription->id,
                ]);
                return ['state' => 'failed'];
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

            return ['state' => $accepted ? 'paid' : 'failed'];
        } catch (\Throwable $e) {
            Log::error('Stripe payment attempt failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return ['state' => 'failed'];
        }
    }

    private function buildRecurringExternalId(Invoice $invoice): string
    {
        return "renewal-inv-{$invoice->id}";
    }

    /**
     * Attempt charge via Midtrans (Snap hosted invoice).
     * Midtrans does not support server-side auto-charge; this creates a hosted
     * payment link that the customer must open manually. State is always 'pending'
     * until the webhook or reconciliation confirms settlement.
     */
    private function chargeViaMidtrans(Invoice $invoice, Subscription $subscription, Payment $payment): array
    {
        try {
            $midtransService = app(MidtransService::class);
            $metadata        = is_array($payment->metadata) ? $payment->metadata : [];

            $company = $invoice->company;
            if (! $company) {
                Log::warning('Midtrans payment skipped: company missing', ['invoice_id' => $invoice->id]);
                return ['state' => 'failed'];
            }

            // Re-use existing Midtrans order if pending (avoid duplicate Snap tokens)
            $existingOrderId = isset($metadata['midtrans_order_id']) ? (string) $metadata['midtrans_order_id'] : '';
            if ($existingOrderId !== '') {
                $existing       = $midtransService->getTransaction($existingOrderId);
                $existingStatus = strtolower((string) ($existing['transaction_status'] ?? ''));
                $existingFraud  = strtolower((string) ($existing['fraud_status'] ?? ''));
                $existingState  = $midtransService->resolvePaymentState($existingStatus, $existingFraud);

                if ($existingState === 'paid') {
                    return ['state' => 'paid'];
                }

                if ($existingState === 'pending') {
                    Log::info('Midtrans renewal order still pending', [
                        'invoice_id' => $invoice->id,
                        'order_id'   => $existingOrderId,
                        'status'     => $existingStatus,
                    ]);
                    return ['state' => 'pending'];
                }
            }

            $orderId = sprintf('renewal-inv-%d-%s', $invoice->id, \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8)));
            $result  = $midtransService->createTransaction([
                'order_id'    => $orderId,
                'amount'      => (int) round((float) $invoice->amount_due),
                'description' => 'Recurring renewal invoice ' . $invoice->invoice_number,
                'customer'    => [
                    'name'  => (string) ($company->name ?? 'Customer'),
                    'email' => (string) ($company->email ?? ''),
                ],
            ]);

            $payment->update([
                'gateway_reference' => $orderId,
                'metadata'          => array_merge($metadata, [
                    'midtrans_order_id'     => $orderId,
                    'midtrans_redirect_url' => $result['redirect_url'],
                    'midtrans_snap_token'   => $result['token'],
                ]),
            ]);

            Log::info('Midtrans renewal Snap transaction created', [
                'invoice_id'   => $invoice->id,
                'order_id'     => $orderId,
                'redirect_url' => $result['redirect_url'],
            ]);

            return ['state' => 'pending'];
        } catch (\Exception $e) {
            Log::error('Midtrans payment attempt failed', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
            return [
                'state'          => 'failed',
                'reason_code'    => 'MIDTRANS_DOWN',
                'reason_message' => 'Midtrans gateway unavailable, next retry has been scheduled.',
            ];
        }
    }

    private function emitRenewalMetric(string $metricKey, string $reasonCode, ?Invoice $invoice = null, array $context = []): void
    {
        Log::info('renewal_monitoring.metric', array_merge([
            'metric_key' => $metricKey,
            'reason_code' => $reasonCode,
            'invoice_id' => $invoice?->id,
            'company_id' => $invoice?->company_id,
            'renewal_period_key' => $invoice?->renewal_period_key,
            'recorded_at' => now()->toIso8601String(),
        ], $context));
    }

    private function emitRenewalAlert(string $alertType, string $reasonCode, string $message, ?Invoice $invoice = null, array $context = []): void
    {
        Log::warning('renewal_monitoring.alert', array_merge([
            'alert_type' => $alertType,
            'reason_code' => $reasonCode,
            'message' => $message,
            'invoice_id' => $invoice?->id,
            'company_id' => $invoice?->company_id,
            'renewal_period_key' => $invoice?->renewal_period_key,
            'recorded_at' => now()->toIso8601String(),
        ], $context));

        if (in_array($alertType, ['gateway_down', 'worker_crash'], true)) {
            (new NotificationService())->notifyAdminOperationalAlert(
                $alertType,
                $reasonCode,
                $message,
                array_merge(['invoice_id' => $invoice?->id, 'company_id' => $invoice?->company_id], $context)
            );
        }
    }

    private function emitFailureSpikeAlert(?Invoice $invoice, string $reasonCode): void
    {
        $failureCodes = [
            'MIDTRANS_DOWN',
            'RENEWAL_PROCESS_EXCEPTION',
            'RENEWAL_MAX_RETRY_EXCEEDED',
            'MIDTRANS_PAYMENT_FAILED',
            'MIDTRANS_INVOICE_EXPIRED',
        ];

        if (! in_array($reasonCode, $failureCodes, true)) {
            return;
        }

        $count = Invoice::query()
            ->whereNotNull('renewal_period_key')
            ->whereIn('renewal_reason_code', $failureCodes)
            ->whereDate('issue_date', '>=', now()->subDay()->toDateString())
            ->count();

        $this->emitRenewalMetric('renewal_failure_count_24h', $reasonCode, $invoice, [
            'window' => '24h',
            'count' => $count,
        ]);

        if ($count < self::FAILURE_SPIKE_THRESHOLD) {
            return;
        }

        $this->emitRenewalAlert('failure_spike', 'RENEWAL_FAILURE_SPIKE', 'Renewal failure spike threshold reached in the last 24 hours.', $invoice, [
            'window' => '24h',
            'count' => $count,
            'threshold' => self::FAILURE_SPIKE_THRESHOLD,
            'trigger_reason_code' => $reasonCode,
        ]);

        (new NotificationService())->notifyAdminOperationalAlert(
            'failure_spike',
            'RENEWAL_FAILURE_SPIKE',
            "Renewal failure spike: {$count} failures in the last 24 hours (threshold: " . self::FAILURE_SPIKE_THRESHOLD . ").",
            [
                'window' => '24h',
                'count' => $count,
                'threshold' => self::FAILURE_SPIKE_THRESHOLD,
                'trigger_reason_code' => $reasonCode,
                'invoice_id' => $invoice?->id,
            ]
        );
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
        $previousStatus = (string) $subscription->status;
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
            'grace_started_at' => null,
            'grace_ends_at' => null,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        $latestInvoice = $subscription->invoices()->latest('id')->first();
        $this->recordSubscriptionEvent(
            $subscription,
            $latestInvoice,
            null,
            'renewed',
            'RENEWAL_SUBSCRIPTION_EXTENDED',
            'Subscription period extended after renewal payment.',
            ['next_ends_at' => $nextEnd->toIso8601String()]
        );

        if (in_array($previousStatus, ['grace_period', 'suspended'], true)) {
            $this->recordSubscriptionEvent(
                $subscription,
                $latestInvoice,
                null,
                'resumed',
                'RENEWAL_SUBSCRIPTION_RESUMED',
                'Subscription resumed from grace/suspended state after renewal payment.'
            );
        }
    }
}
