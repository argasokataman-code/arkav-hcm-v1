<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\PackageAddon;
use App\Models\PurchaseTransaction;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Services\BillingTaxCalculationService;
use App\Services\CompanyStatusSynchronizer;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Simplified renewal notifier — NO auto-charge, NO Stripe, NO retry.
 *
 * Only handles:
 * 1. H-7 reminder email
 * 2. Invoice creation on expiry day
 * 3. Grace period management
 * 4. Suspension warnings
 *
 * Tenant pays manually via invoice email → Midtrans Snap.
 */
class SubscriptionRenewalNotifier implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Log::info('Starting subscription renewal notification processing');

        $today = now()->startOfDay();

        try {
            $this->sendExpirationReminders($today->clone()->addDays(7));
            $this->createRenewalInvoices($today);
            $this->handleGracePeriods($today);
            $this->sendSuspensionWarnings($today);

            Log::info('Subscription renewal notification processing completed');
        } catch (\Exception $e) {
            Log::error('Subscription renewal notification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Send reminders for subscriptions expiring in 7 days.
     */
    private function sendExpirationReminders(Carbon $reminderDate): void
    {
        $subscriptions = Subscription::where('status', 'active')
            ->whereDate('ends_at', $reminderDate->toDateString())
            ->with(['company.owner', 'package'])
            ->get();

        foreach ($subscriptions as $subscription) {
            try {
                $this->emitEvent($subscription, 'expiring_soon',
                    'billing.subscription.expiring_soon',
                    'Subscription expires in 7 days. Invoice will be created on expiry.',
                    ['days_remaining' => 7]
                );
            } catch (\Exception $e) {
                Log::error('Failed to send expiration reminder', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Create renewal invoices for subscriptions expiring today.
     */
    private function createRenewalInvoices(Carbon $today): void
    {
        $subscriptions = Subscription::where('status', 'active')
            ->whereDate('ends_at', $today->toDateString())
            ->with(['company', 'package'])
            ->get();

        foreach ($subscriptions as $subscription) {
            try {
                DB::transaction(function () use ($subscription): void {
                    $locked = Subscription::query()
                        ->whereKey($subscription->getKey())
                        ->lockForUpdate()
                        ->with(['company', 'package'])
                        ->first();

                    if (! $locked || $locked->status !== 'active') {
                        return;
                    }

                    $company = $locked->company;
                    $package = $locked->package;
                    if (! $company) {
                        return;
                    }

                    $billingCycle = $locked->billing_cycle ?? 'monthly';
                    $periodAnchor = $locked->ends_at ?? $locked->starts_at ?? $locked->created_at ?? now();
                    $currentEnd = $locked->ends_at ?? $periodAnchor;
                    $nextEnd = match ($billingCycle) {
                        'yearly' => $currentEnd->clone()->addYear(),
                        'quarterly' => $currentEnd->clone()->addMonths(3),
                        default => $currentEnd->clone()->addMonth(),
                    };

                    $renewalPeriodKey = sprintf('sub_%d_%s', $locked->id, $periodAnchor->format('Y_m'));

                    $baseAmount = (float) ($locked->amount ?? 0);
                    if ($baseAmount <= 0 && $package) {
                        $baseAmount = $billingCycle === 'yearly'
                            ? (float) $package->yearly_price
                            : (float) $package->monthly_price;
                    }

                    $pricingBreakdown = $this->buildPricingBreakdown((int) $company->id, $baseAmount);
                    $amountDue = (float) ($pricingBreakdown['total_amount'] ?? $baseAmount);

                    // Check for inactive addons — exclude their amounts from renewal
                    $metadata = (array) ($locked->metadata ?? []);
                    $appliedIds = array_values(array_filter((array) ($metadata['addon_applied_transaction_ids'] ?? []), fn ($v) => is_numeric($v)));
                    $inactiveAddonAmount = 0.0;
                    $inactiveAddonCodes = [];

                    if ($appliedIds !== []) {
                        $txns = PurchaseTransaction::query()
                            ->whereIn('id', $appliedIds)
                            ->where('transaction_type', 'addon')
                            ->where('status', 'paid')
                            ->get();

                        foreach ($txns as $pt) {
                            $addon = PackageAddon::find($pt->package_addon_id);
                            if (! $addon || (string) $addon->status !== 'active') {
                                $inactiveAddonAmount += (float) ($pt->total_amount ?? 0);
                                if ($addon) {
                                    $inactiveAddonCodes[] = $addon->code;
                                }
                            }
                        }
                    }

                    if ($inactiveAddonAmount > 0) {
                        $amountDue = round(max(0, $amountDue - $inactiveAddonAmount), 2);
                    }

                    $taxRateSnapshot = app(BillingTaxCalculationService::class)
                        ->resolvePolicyRateSnapshot((int) $company->id, now()->format('Y-m'));

                    // Idempotency check
                    $existing = Invoice::query()
                        ->where('company_id', $company->id)
                        ->where('subscription_id', $locked->id)
                        ->where('renewal_period_key', $renewalPeriodKey)
                        ->exists();

                    if ($existing) {
                        return;
                    }

                    $invoice = Invoice::query()->create([
                        'company_id' => $company->id,
                        'subscription_id' => $locked->id,
                        'renewal_period_key' => $renewalPeriodKey,
                        'purchase_transaction_id' => null,
                        'issue_date' => now()->toDateString(),
                        'due_date' => now()->addDays(7)->toDateString(),
                        'amount_due' => $amountDue,
                        'billing_tax_rate_snapshot' => $taxRateSnapshot > 0 ? $taxRateSnapshot : null,
                        'status' => 'draft',
                        'renewal_reason_code' => 'RENEWAL_INVOICE_CREATED',
                        'renewal_reason_message' => 'Renewal invoice created.',
                        'notes' => $this->buildInvoiceNotes($locked, $pricingBreakdown, $billingCycle, $currentEnd, $nextEnd, $package, $inactiveAddonCodes, $inactiveAddonAmount),
                    ]);

                    $this->emitEvent($locked, 'renewal_invoice_created',
                        'RENEWAL_INVOICE_CREATED',
                        'Renewal invoice created.',
                        ['invoice_id' => $invoice->id, 'amount_due' => $amountDue]
                    );

                    if ((float) $invoice->amount_due <= 0) {
                        // Zero-amount: no invoice needed, just extend
                        $this->extendSubscription($locked, $nextEnd);
                        $this->emitEvent($locked, 'renewal_completed',
                            'ZERO_AMOUNT',
                            'Auto-renewed (zero amount).',
                            ['new_ends_at' => $nextEnd->toDateString()]
                        );
                        return;
                    }

                    // Notify company about the invoice
                    try {
                        app(NotificationService::class)->notifyInvoiceIssued($invoice);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to send invoice notification', [
                            'invoice_id' => $invoice->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                });
            } catch (\Exception $e) {
                Log::error('Failed to create renewal invoice', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle grace periods — escalate expired ones to inactive.
     */
    private function handleGracePeriods(Carbon $today): void
    {
        $expired = Subscription::where('status', 'grace_period')
            ->where('grace_ends_at', '<', $today)
            ->with('company')
            ->get();

        foreach ($expired as $subscription) {
            try {
                DB::transaction(function () use ($subscription): void {
                    $locked = Subscription::query()
                        ->whereKey($subscription->getKey())
                        ->lockForUpdate()
                        ->with('company')
                        ->first();

                    if (! $locked || $locked->status !== 'grace_period') {
                        return;
                    }

                    $oldStatus = $locked->status;
                    $locked->update([
                        'status' => 'inactive',
                        'suspended_at' => now(),
                    ]);

                    if ($locked->company) {
                        app(CompanyStatusSynchronizer::class)->syncFromSubscription($locked->fresh('company'));
                    }

                    $this->emitEvent($locked, 'inactive',
                        'RENEWAL_GRACE_EXPIRED',
                        'Grace period expired, subscription set to inactive.',
                        ['previous_status' => $oldStatus]
                    );
                });
            } catch (\Exception $e) {
                Log::error('Failed to expire grace period', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send suspension warnings for subscriptions about to leave grace period.
     */
    private function sendSuspensionWarnings(Carbon $today): void
    {
        $tomorrow = $today->clone()->addDay();
        $subscriptions = Subscription::where('status', 'grace_period')
            ->whereDate('grace_ends_at', $tomorrow->toDateString())
            ->get();

        foreach ($subscriptions as $subscription) {
            try {
                // Dedup: skip if warning already sent
                $alreadyWarned = SubscriptionEvent::query()
                    ->where('subscription_id', $subscription->id)
                    ->where('event_type', 'suspension_warning')
                    ->exists();

                if ($alreadyWarned) {
                    continue;
                }

                $this->emitEvent($subscription, 'suspension_warning',
                    'billing.subscription.suspension_warning',
                    'Subscription will be suspended tomorrow if payment is not received.',
                    ['grace_ends_at' => $subscription->grace_ends_at?->toDateString()]
                );
            } catch (\Exception $e) {
                Log::error('Failed to send suspension warning', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function emitEvent(Subscription $subscription, string $eventType, string $reasonCode, string $reasonMessage, array $payload = []): void
    {
        SubscriptionEvent::query()->create([
            'company_id' => $subscription->company_id,
            'company_uuid' => $subscription->getAttribute('company_uuid'),
            'subscription_id' => $subscription->id,
            'subscription_uuid' => $subscription->uuid ?? null,
            'event_type' => $eventType,
            'reason_code' => $reasonCode,
            'reason_message' => mb_substr($reasonMessage, 0, 255),
            'payload' => $payload === [] ? null : $payload,
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function buildPricingBreakdown(int $companyId, float $baseAmount): array
    {
        $taxSnapshot = app(BillingTaxCalculationService::class)
            ->resolvePolicyRateSnapshot($companyId, now()->format('Y-m'));

        $taxAmount = $taxSnapshot > 0 ? round($baseAmount * $taxSnapshot / 100, 2) : 0;
        $totalAmount = $baseAmount + $taxAmount;

        return [
            'base_amount' => $baseAmount,
            'tax_rate' => $taxSnapshot,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ];
    }

    private function buildInvoiceNotes(Subscription $subscription, array $pricing, string $billingCycle, Carbon $currentEnd, Carbon $nextEnd, $package, array $inactiveAddonCodes = [], float $inactiveAddonAmount = 0.0): string
    {
        $notes = [
            'source' => 'recurring_subscription_renewal',
            'pricing' => $pricing,
            'package_name' => $package?->name,
            'billing_cycle' => $billingCycle,
            'billing_period_start' => $currentEnd->toDateString(),
            'billing_period_end' => $nextEnd->toDateString(),
            'auto_generated' => true,
        ];

        if ($inactiveAddonAmount > 0) {
            $notes['inactive_addons_removed'] = [
                'addon_codes' => $inactiveAddonCodes,
                'removed_amount' => $inactiveAddonAmount,
            ];
        }

        return json_encode($notes, JSON_UNESCAPED_SLASHES);
    }

    private function extendSubscription(Subscription $subscription, Carbon $newEnd): void
    {
        $subscription->update([
            'ends_at' => $newEnd,
        ]);
    }
}
