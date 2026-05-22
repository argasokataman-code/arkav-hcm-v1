<?php

namespace Tests\Feature;

use App\Jobs\ProcessRecurringSubscriptionBilling;
use App\Models\Company;
use App\Models\HcmBillingTaxPolicy;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcessRecurringSubscriptionBillingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_tax_inclusive_renewal_invoice_with_valid_invoice_schema(): void
    {
        $package = Package::query()->create([
            'code' => 'starter-recurring',
            'name' => 'Starter Recurring',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'RENEW01',
            'name' => 'Renewal Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        HcmBillingTaxPolicy::query()->create([
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'billing_month' => now()->format('Y-m'),
            'billing_cycle_type' => 'monthly',
            'tax_rate_percentage' => 11,
            'base_calculation_method' => 'invoice_amount_due',
            'effective_from' => now()->startOfMonth()->toDateString(),
            'effective_to' => now()->endOfMonth()->toDateString(),
            'status' => 'active',
            'notes' => json_encode([
                'global_rates' => [
                    'subscription_tax_rate' => 11,
                ],
            ]),
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now(),
            'trial_ends_at' => null,
            'auto_renew' => true,
            'billing_cycle' => 'monthly',
            'amount' => 100000,
            'metadata' => [
                'gateway' => 'stripe',
                'payment_method' => 'credit_card',
            ],
        ]);

        dispatch_sync(new ProcessRecurringSubscriptionBilling());

        $invoice = Invoice::query()
            ->where('company_id', $company->id)
            ->where('subscription_id', $subscription->id)
            ->where('is_paid', false)
            ->latest('id')
            ->firstOrFail();

        $this->assertEqualsWithDelta(111000, (float) $invoice->amount_due, 0.01);
        $this->assertEqualsWithDelta(11, (float) ($invoice->billing_tax_rate_snapshot ?? 0), 0.01);
        $this->assertSame('draft', $invoice->status);
        $this->assertStringContainsString('"source":"recurring_subscription_renewal"', (string) $invoice->notes);
        $this->assertNotNull($invoice->renewal_period_key);
        $this->assertSame('RENEWAL_INVOICE_CREATED', $invoice->renewal_reason_code);
    }

    public function test_job_is_idempotent_per_subscription_and_period(): void
    {
        $package = Package::query()->create([
            'code' => 'starter-idempotent',
            'name' => 'Starter Idempotent',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'RENEW02',
            'name' => 'Renewal Co 2',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now(),
            'trial_ends_at' => null,
            'auto_renew' => true,
            'billing_cycle' => 'monthly',
            'amount' => 100000,
            'metadata' => [
                'gateway' => 'stripe',
                'payment_method' => 'credit_card',
            ],
        ]);

        dispatch_sync(new ProcessRecurringSubscriptionBilling());
        dispatch_sync(new ProcessRecurringSubscriptionBilling());

        $invoices = Invoice::query()
            ->where('company_id', $company->id)
            ->whereNotNull('renewal_period_key')
            ->where('notes', 'like', '%"source":"recurring_subscription_renewal"%')
            ->get();

        $this->assertCount(1, $invoices);
    }

    public function test_failed_renewal_attempt_schedules_retry_in_one_hour_and_writes_event(): void
    {
        [$company, $subscription] = $this->createRecurringFixture();

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'renewal_period_key' => sprintf('sub_%d_%s', $subscription->id, now()->format('Y_m')),
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'amount_due' => 100000,
            'status' => 'draft',
            'notes' => json_encode(['source' => 'recurring_subscription_renewal'], JSON_UNESCAPED_SLASHES),
        ]);

        dispatch_sync(new ProcessRecurringSubscriptionBilling());

        $payment = Payment::query()->where('invoice_id', $invoice->id)->latest('id')->firstOrFail();
        $this->assertSame(1, (int) ($payment->metadata['attempt_count'] ?? 0));
        $this->assertNotNull($payment->metadata['next_attempt_at'] ?? null);

        $nextAttempt = \Illuminate\Support\Carbon::parse((string) $payment->metadata['next_attempt_at']);
        $this->assertTrue($nextAttempt->greaterThan(now()->addMinutes(55)));

        $invoice->refresh();
        $this->assertSame('RENEWAL_RETRY_SCHEDULED', $invoice->renewal_reason_code);

        $event = SubscriptionEvent::query()
            ->where('subscription_id', $subscription->id)
            ->where('event_type', 'renewal_retry_attempted')
            ->latest('id')
            ->first();
        $this->assertNotNull($event);
    }

    public function test_parallel_worker_runs_do_not_double_increment_attempt_or_events(): void
    {
        [$company, $subscription] = $this->createRecurringFixture();

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'renewal_period_key' => sprintf('sub_%d_%s', $subscription->id, now()->format('Y_m')),
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'amount_due' => 100000,
            'status' => 'draft',
            'notes' => json_encode(['source' => 'recurring_subscription_renewal'], JSON_UNESCAPED_SLASHES),
        ]);

        // Simulate two workers picking the same due invoice in a tight window.
        dispatch_sync(new ProcessRecurringSubscriptionBilling());
        dispatch_sync(new ProcessRecurringSubscriptionBilling());

        $payment = Payment::query()->where('invoice_id', $invoice->id)->latest('id')->firstOrFail();
        $this->assertSame(1, (int) ($payment->metadata['attempt_count'] ?? 0));

        $this->assertSame(1, SubscriptionEvent::query()
            ->where('subscription_id', $subscription->id)
            ->where('event_type', 'renewal_retry_attempted')
            ->count());
    }

    public function test_retry_exhausted_moves_subscription_to_grace_period(): void
    {
        [$company, $subscription] = $this->createRecurringFixture();

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'renewal_period_key' => sprintf('sub_%d_%s', $subscription->id, now()->format('Y_m')),
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'amount_due' => 100000,
            'status' => 'draft',
            'notes' => json_encode(['source' => 'recurring_subscription_renewal'], JSON_UNESCAPED_SLASHES),
        ]);

        Payment::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'currency' => 'IDR',
            'status' => 'failed',
            'payment_method' => 'credit_card',
            'gateway' => 'stripe',
            'metadata' => [
                'attempt_count' => 3,
            ],
        ]);

        dispatch_sync(new ProcessRecurringSubscriptionBilling());

        $subscription->refresh();
        $this->assertSame('grace_period', $subscription->status);
        $this->assertNotNull($subscription->grace_started_at);
        $this->assertNotNull($subscription->grace_ends_at);

        $invoice->refresh();
        $this->assertSame('RENEWAL_MAX_RETRY_EXCEEDED', $invoice->renewal_reason_code);

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->id,
            'event_type' => 'grace_started',
            'reason_code' => 'RENEWAL_GRACE_PERIOD_STARTED',
        ]);
    }

    public function test_retry_exhausted_emits_failure_spike_alert_when_threshold_is_reached(): void
    {
        Log::spy();

        [$company, $subscription] = $this->createRecurringFixture();

        Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'renewal_period_key' => sprintf('sub_%d_%s_prev_a', $subscription->id, now()->format('Y_m')),
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'amount_due' => 100000,
            'status' => 'overdue',
            'renewal_reason_code' => 'RENEWAL_MAX_RETRY_EXCEEDED',
            'notes' => json_encode(['source' => 'recurring_subscription_renewal'], JSON_UNESCAPED_SLASHES),
        ]);

        Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'renewal_period_key' => sprintf('sub_%d_%s_prev_b', $subscription->id, now()->format('Y_m')),
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'amount_due' => 100000,
            'status' => 'overdue',
            'renewal_reason_code' => 'RENEWAL_PROCESS_EXCEPTION',
            'notes' => json_encode(['source' => 'recurring_subscription_renewal'], JSON_UNESCAPED_SLASHES),
        ]);

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'renewal_period_key' => sprintf('sub_%d_%s_spike', $subscription->id, now()->format('Y_m')),
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'amount_due' => 100000,
            'status' => 'draft',
            'notes' => json_encode(['source' => 'recurring_subscription_renewal'], JSON_UNESCAPED_SLASHES),
        ]);

        Payment::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'invoice_id' => $invoice->id,
            'amount' => 100000,
            'currency' => 'IDR',
            'status' => 'failed',
            'payment_method' => 'credit_card',
            'gateway' => 'stripe',
            'metadata' => [
                'attempt_count' => 3,
            ],
        ]);

        dispatch_sync(new ProcessRecurringSubscriptionBilling());

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            return $message === 'renewal_monitoring.alert'
                && ($context['alert_type'] ?? null) === 'failure_spike'
                && ($context['reason_code'] ?? null) === 'RENEWAL_FAILURE_SPIKE'
                && (int) ($context['count'] ?? 0) === 3;
        })->once();
    }

    public function test_expired_grace_period_escalates_to_inactive(): void
    {
        [$company, $subscription] = $this->createRecurringFixture();

        $subscription->forceFill([
            'status' => 'grace_period',
            'grace_started_at' => now()->subDays(4),
            'grace_ends_at' => now()->subDay(),
        ])->save();

        dispatch_sync(new ProcessRecurringSubscriptionBilling());

        $subscription->refresh();
        $company->refresh();
        $this->assertSame('inactive', $subscription->status);
        $this->assertSame('inactive', $company->status);
        $this->assertNotNull($subscription->suspended_at);

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->id,
            'event_type' => 'inactive',
            'reason_code' => 'RENEWAL_GRACE_EXPIRED',
        ]);
    }

    public function test_grace_period_sends_notif_to_billing_contact_on_retry_exhausted(): void
    {
        Log::spy();

        [$company, $subscription] = $this->createRecurringFixture();

        $user = \App\Models\User::query()->create([
            'name' => 'Billing Contact',
            'email' => 'billing@testco.example',
            'password' => bcrypt('secret'),
        ]);

        $company->forceFill(['owner_user_id' => $user->id])->save();

        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'renewal_period_key' => sprintf('sub_%d_%s_gracen', $subscription->id, now()->format('Y_m')),
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'amount_due' => 150000,
            'status' => 'draft',
            'notes' => json_encode(['source' => 'recurring_subscription_renewal'], JSON_UNESCAPED_SLASHES),
        ]);

        Payment::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'invoice_id' => $invoice->id,
            'amount' => 150000,
            'currency' => 'IDR',
            'status' => 'failed',
            'payment_method' => 'credit_card',
            'gateway' => 'midtrans',
            'metadata' => ['attempt_count' => 3],
        ]);

        dispatch_sync(new ProcessRecurringSubscriptionBilling());

        $subscription->refresh();
        $this->assertSame('grace_period', $subscription->status);

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context = []): bool {
            return ($context['event_key'] ?? null) === 'billing.subscription.grace_started';
        })->once();
    }

    public function test_suspension_warning_is_sent_one_day_before_grace_expires(): void
    {
        Log::spy();

        [$company, $subscription] = $this->createRecurringFixture();

        $user = \App\Models\User::query()->create([
            'name' => 'Billing Contact Warn',
            'email' => 'billing.warn@testco.example',
            'password' => bcrypt('secret'),
        ]);

        $company->forceFill(['owner_user_id' => $user->id])->save();

        $subscription->forceFill([
            'status' => 'grace_period',
            'grace_started_at' => now()->subDays(2),
            'grace_ends_at' => now()->addDay(),
        ])->save();

        dispatch_sync(new ProcessRecurringSubscriptionBilling());

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context = []): bool {
            return ($context['event_key'] ?? null) === 'billing.subscription.suspension_warning';
        })->once();

        // Guard: re-dispatch must not send duplicate warning.
        dispatch_sync(new ProcessRecurringSubscriptionBilling());

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context = []): bool {
            return ($context['event_key'] ?? null) === 'billing.subscription.suspension_warning';
        })->once();
    }

    public function test_suspended_notification_sent_after_grace_expires(): void
    {
        Log::spy();

        [$company, $subscription] = $this->createRecurringFixture();

        $user = \App\Models\User::query()->create([
            'name' => 'Billing Contact Susp',
            'email' => 'billing.susp@testco.example',
            'password' => bcrypt('secret'),
        ]);

        $company->forceFill(['owner_user_id' => $user->id])->save();

        $subscription->forceFill([
            'status' => 'grace_period',
            'grace_started_at' => now()->subDays(4),
            'grace_ends_at' => now()->subDay(),
        ])->save();

        dispatch_sync(new ProcessRecurringSubscriptionBilling());

        $subscription->refresh();
        $company->refresh();
        $this->assertSame('inactive', $subscription->status);
        $this->assertSame('inactive', $company->status);

        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context = []): bool {
            return ($context['event_key'] ?? null) === 'billing.subscription.inactive';
        })->once();
    }

    /**
     * @return array{0: \App\Models\Company, 1: \App\Models\Subscription}
     */
    private function createRecurringFixture(): array
    {
        $package = Package::query()->create([
            'code' => 'starter-phase2',
            'name' => 'Starter Phase 2',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'RENEWP2'.strtoupper((string) str()->random(2)),
            'name' => 'Renewal Phase 2 Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addDays(1),
            'trial_ends_at' => null,
            'auto_renew' => true,
            'billing_cycle' => 'monthly',
            'amount' => 100000,
            'metadata' => [
                'gateway' => 'stripe',
                'payment_method' => 'credit_card',
            ],
        ]);

        return [$company, $subscription];
    }
}
