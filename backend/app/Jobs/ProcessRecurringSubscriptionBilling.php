<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Subscription;
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
        // Find subscriptions with unpaid renewal invoices
        $invoices = Invoice::where('status', 'pending')
            ->where('type', 'renewal')
            ->whereDate('due_date', '<=', $today->toDateString())
            ->with(['subscription.company', 'subscription.package'])
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

        // Create renewal invoice
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'type' => 'renewal',
            'status' => 'pending',
            'issue_date' => now(),
            'due_date' => now()->addDays(7),
            'amount' => $subscription->amount,
            'currency' => $subscription->currency ?? 'IDR',
            'description' => "Renewal: {$package->name} ({$billingCycle})",
            'metadata' => [
                'billing_period_start' => $currentEnd->toDateString(),
                'billing_period_end' => $nextEnd->toDateString(),
            ],
        ]);

        // Schedule payment attempt
        $paymentAttempt = DB::table('payment_attempts')->insert([
            'invoice_id' => $invoice->id,
            'payment_method' => $subscription->payment_method ?? 'card',
            'gateway' => $subscription->gateway ?? 'stripe',
            'status' => 'pending',
            'attempt_count' => 0,
            'next_attempt_at' => now()->addHours(1),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('Renewal invoice created', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $subscription->id,
            'amount' => $invoice->amount,
            'company_id' => $company->id,
        ]);

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
        $gateway = $subscription->gateway ?? 'stripe';
        $maxAttempts = 3;

        // Get or create payment attempt record
        $attempt = DB::table('payment_attempts')
            ->where('invoice_id', $invoice->id)
            ->first();

        if (!$attempt) {
            // Create initial attempt record
            $attemptCount = 0;
            DB::table('payment_attempts')->insert([
                'invoice_id' => $invoice->id,
                'payment_method' => $subscription->payment_method ?? 'card',
                'gateway' => $gateway,
                'status' => 'pending',
                'attempt_count' => 0,
                'next_attempt_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $attemptCount = $attempt->attempt_count;
        }

        // Check if max retries exceeded
        if ($attemptCount >= $maxAttempts) {
            DB::table('payment_attempts')
                ->where('invoice_id', $invoice->id)
                ->update(['status' => 'failed']);

            $invoice->update(['status' => 'payment_failed']);
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
            $success = match ($gateway) {
                'xendit' => $this->chargeViaXendit($invoice, $subscription),
                'stripe' => $this->chargeViaStripe($invoice, $subscription),
                default => throw new \RuntimeException("Unsupported gateway: $gateway"),
            };

            if ($success) {
                $invoice->update(['status' => 'paid', 'paid_at' => now()]);
                
                // Renew subscription
                $subscription->update(['ends_at' => $subscription->ends_at->addDays(30)]);

                DB::table('payment_attempts')
                    ->where('invoice_id', $invoice->id)
                    ->update(['status' => 'successful']);

                Log::info('Payment collected successfully', [
                    'invoice_id' => $invoice->id,
                    'gateway' => $gateway,
                ]);

                // Send payment receipt
                $notificationService = new NotificationService();
                $notificationService->notifyPaymentReceived($invoice->payment);
            } else {
                // Update attempt count and schedule retry
                $nextAttempt = now()->addHours(24);
                DB::table('payment_attempts')
                    ->where('invoice_id', $invoice->id)
                    ->update([
                        'attempt_count' => $attemptCount + 1,
                        'next_attempt_at' => $nextAttempt,
                        'updated_at' => now(),
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
    private function chargeViaStripe(Invoice $invoice, Subscription $subscription): bool
    {
        try {
            $stripeService = new StripeService();
            
            // Use stored payment method or create one-time payment intent
            if (!empty($subscription->gateway_reference)) {
                $result = $stripeService->createPaymentIntent([
                    'customer_id' => $subscription->gateway_reference,
                    'amount' => $invoice->amount,
                    'currency' => $invoice->currency,
                    'description' => $invoice->description,
                    'invoice_id' => $invoice->id,
                    'company_id' => $invoice->company_id,
                ]);

                // In production, this would need to confirm the payment
                // For now, we log it for manual processing
                Log::info('Stripe payment intent created for renewal', [
                    'invoice_id' => $invoice->id,
                    'intent_id' => $result['id'],
                ]);

                return true; // Payment initiated, webhook will confirm
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Stripe payment attempt failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Attempt charge via Xendit
     */
    private function chargeViaXendit(Invoice $invoice, Subscription $subscription): bool
    {
        try {
            $xenditService = new XenditService();

            $result = $xenditService->createInvoice([
                'external_id' => "renewal-inv-{$invoice->id}",
                'amount' => (int) $invoice->amount,
                'description' => $invoice->description,
                'customer_name' => $invoice->company->name,
                'customer_email' => $invoice->company->email,
                'currency' => $invoice->currency,
                'success_url' => config('app.url') . '/billing/success',
                'failure_url' => config('app.url') . '/billing/failed',
            ]);

            if ($result && !empty($result['id'])) {
                // Store Xendit invoice ID for webhook tracking
                $invoice->update([
                    'gateway_reference' => $result['id'],
                    'metadata' => array_merge($invoice->metadata ?? [], [
                        'xendit_invoice_id' => $result['id'],
                        'invoice_url' => $result['invoice_url'] ?? null,
                    ]),
                ]);

                Log::info('Xendit renewal invoice created', [
                    'invoice_id' => $invoice->id,
                    'xendit_invoice_id' => $result['id'],
                ]);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Xendit payment attempt failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
