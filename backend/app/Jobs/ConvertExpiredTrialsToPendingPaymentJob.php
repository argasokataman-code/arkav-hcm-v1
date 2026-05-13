<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\BillingTaxCalculationService;
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
                    $baseAmount = (float) ($subscription->amount ?? 0);
                    if ($baseAmount <= 0 && $subscription->package) {
                        $baseAmount = $subscription->billing_cycle === 'yearly'
                            ? (float) $subscription->package->yearly_price
                            : (float) $subscription->package->monthly_price;
                    }

                    $taxRateSnapshot = app(BillingTaxCalculationService::class)
                        ->resolvePolicyRateSnapshot($subscription->company_id, $now->format('Y-m'));

                    $subscriptionTaxAmount = $taxRateSnapshot > 0
                        ? round($baseAmount * ($taxRateSnapshot / 100), 2)
                        : 0.0;
                    $amountDue = round($baseAmount + $subscriptionTaxAmount, 2);

                    $invoice = Invoice::query()->create([
                        'company_id' => $subscription->company_id,
                        'subscription_id' => $subscription->id,
                        'purchase_transaction_id' => null,
                        'issue_date' => $now->toDateString(),
                        'due_date' => $now->copy()->addDay()->toDateString(),
                        'amount_due' => $amountDue,
                        'billing_tax_rate_snapshot' => $taxRateSnapshot > 0 ? $taxRateSnapshot : null,
                        'status' => 'draft',
                        'notes' => $this->buildInvoicePricingNotes('trial_expiry_conversion', [
                            'base_amount' => round($baseAmount, 2),
                            'subscription_tax_rate' => $taxRateSnapshot,
                            'subscription_tax_amount' => $subscriptionTaxAmount,
                            'total_amount' => $amountDue,
                            'billing_month' => $now->format('Y-m'),
                        ], 'Auto-generated after trial ended.'),
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

