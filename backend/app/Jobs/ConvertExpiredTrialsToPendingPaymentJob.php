<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\InvoiceService;
use App\Jobs\SendInvoiceEmailJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Converts trial subscriptions whose trial_ends_at has passed into pending_payment
 * and creates an invoice linked to the subscription.
 */
class ConvertExpiredTrialsToPendingPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $now = now();

        $trials = Subscription::query()
            ->with(['company.owner', 'package'])
            ->where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', $now)
            ->get();

        if ($trials->isEmpty()) {
            return;
        }

        foreach ($trials as $subscription) {
            try {
                // Idempotency: if an unpaid invoice already exists for this subscription, only flip status.
                $existingUnpaid = Invoice::query()
                    ->where('subscription_id', $subscription->id)
                    ->where('company_id', $subscription->company_id)
                    ->where('is_paid', false)
                    ->exists();

                if (! $existingUnpaid) {
                    $amountDue = (float) ($subscription->amount ?? 0);
                    if ($amountDue <= 0 && $subscription->package) {
                        $amountDue = $subscription->billing_cycle === 'yearly'
                            ? (float) $subscription->package->yearly_price
                            : (float) $subscription->package->monthly_price;
                    }

                    $invoice = Invoice::query()->create([
                        'company_id' => $subscription->company_id,
                        'subscription_id' => $subscription->id,
                        'purchase_transaction_id' => null,
                        'issue_date' => $now->toDateString(),
                        'due_date' => $now->copy()->addDay()->toDateString(),
                        'amount_due' => $amountDue,
                        'status' => 'draft',
                        'notes' => 'Auto-generated after trial ended.',
                    ]);

                    // Auto-send invoice email async (best effort). Uses owner email fallback.
                    dispatch(new SendInvoiceEmailJob($invoice->id));
                }

                // Flip to pending_payment. Set a provisioning window end (ends_at) if missing or already past.
                $provisionEndsAt = $subscription->ends_at && $subscription->ends_at->isFuture()
                    ? $subscription->ends_at
                    : $now->copy()->addHours(24);

                $subscription->update([
                    'status' => 'pending_payment',
                    'trial_ends_at' => null,
                    'ends_at' => $provisionEndsAt,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed converting trial to pending_payment', [
                    'subscription_id' => $subscription->id,
                    'company_id' => $subscription->company_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

