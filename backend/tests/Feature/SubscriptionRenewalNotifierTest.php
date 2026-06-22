<?php

namespace Tests\Feature;

use App\Jobs\SubscriptionRenewalNotifier;
use App\Models\Company;
use App\Models\HcmBillingTaxPolicy;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * UU PDP: Auto-renewal / auto-charge dihapus.
 * Tenant fully in control — bayar manual via invoice email.
 * Job ini hanya: notif + bikin invoice + grace period management.
 * NO Stripe, NO payment collection, NO retry, NO auto-charge.
 */
class SubscriptionRenewalNotifierTest extends TestCase
{
    use RefreshDatabase;

    private int $queryCount = 0;

    private function startQueryTracking(): void
    {
        $this->queryCount = 0;
        DB::listen(function ($query): void {
            ++$this->queryCount;
        });
    }

    private function assertQueryCountLessThan(int $max, string $label = 'dispatch'): void
    {
        $this->assertLessThanOrEqual(
            $max,
            $this->queryCount,
            "Query count exceeded limit for {$label}. Expected ≤{$max}, got {$this->queryCount}."
        );
    }

    public function test_job_creates_renewal_invoice_when_subscription_expires(): void
    {
        $package = Package::query()->create([
            'code' => 'starter-renew',
            'name' => 'Starter Renew',
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
                'global_rates' => ['subscription_tax_rate' => 11],
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
            'auto_renew' => false,
            'billing_cycle' => 'monthly',
            'amount' => 100000,
        ]);

        $this->startQueryTracking();
        dispatch_sync(new SubscriptionRenewalNotifier);
        $this->assertQueryCountLessThan(25, 'renewal_invoice_creation');

        $invoice = Invoice::query()
            ->where('company_id', $company->id)
            ->where('subscription_id', $subscription->id)
            ->where('is_paid', false)
            ->latest('id')
            ->firstOrFail();

        $this->assertEqualsWithDelta(111000, (float) $invoice->amount_due, 0.01);
        $this->assertSame('draft', $invoice->status);
        $this->assertStringContainsString('"source":"recurring_subscription_renewal"', (string) $invoice->notes);
        $this->assertNotNull($invoice->renewal_period_key);
        $this->assertSame('RENEWAL_INVOICE_CREATED', $invoice->renewal_reason_code);

        // NO retry events (auto-charge removed)
        $this->assertDatabaseMissing('subscription_events', [
            'subscription_id' => $subscription->id,
            'event_type' => 'renewal_retry_attempted',
        ]);
    }

    public function test_job_is_idempotent_per_subscription_and_period(): void
    {
        $package = Package::query()->create([
            'code' => 'starter-ido',
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
            'auto_renew' => false,
            'billing_cycle' => 'monthly',
            'amount' => 100000,
        ]);

        dispatch_sync(new SubscriptionRenewalNotifier);
        dispatch_sync(new SubscriptionRenewalNotifier);

        $invoices = Invoice::query()
            ->where('company_id', $company->id)
            ->whereNotNull('renewal_period_key')
            ->where('notes', 'like', '%"source":"recurring_subscription_renewal"%')
            ->get();

        $this->assertCount(1, $invoices);
    }

    public function test_expired_grace_period_escalates_to_inactive(): void
    {
        $package = Package::query()->create([
            'code' => 'starter-grace',
            'name' => 'Starter Grace',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'RENEW03',
            'name' => 'Renewal Co 3',
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
            'status' => 'grace_period',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addDays(30),
            'trial_ends_at' => null,
            'auto_renew' => false,
            'billing_cycle' => 'monthly',
            'amount' => 100000,
            'grace_started_at' => now()->subDays(4),
            'grace_ends_at' => now()->subDay(),
        ]);

        $this->startQueryTracking();
        dispatch_sync(new SubscriptionRenewalNotifier);
        $this->assertQueryCountLessThan(17, 'grace_expired_escalation');

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

    public function test_suspension_warning_is_sent_one_day_before_grace_expires(): void
    {
        [$company, $subscription, $user] = $this->createExpiringGraceFixture();

        $job = new SubscriptionRenewalNotifier;
        $job->handle();

        // Warning event recorded in subscription_events
        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->id,
            'event_type' => 'suspension_warning',
            'reason_code' => 'billing.subscription.suspension_warning',
        ]);

        // Guard: re-dispatch must not send duplicate warning.
        $job->handle();

        $events = SubscriptionEvent::query()
            ->where('subscription_id', $subscription->id)
            ->where('event_type', 'suspension_warning')
            ->count();
        $this->assertEquals(1, $events, 'Duplicate suspension warning should not be sent');
    }

    public function test_suspended_notification_sent_after_grace_expires(): void
    {
        [$company, $subscription, $user] = $this->createExpiringGraceFixture('-4 days', '-1 day');

        $job = new SubscriptionRenewalNotifier;
        $job->handle();

        $subscription->refresh();
        $company->refresh();
        $this->assertSame('inactive', $subscription->status);
        $this->assertSame('inactive', $company->status);

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->id,
            'event_type' => 'inactive',
            'reason_code' => 'RENEWAL_GRACE_EXPIRED',
        ]);
    }

    public function test_job_sends_reminder_7_days_before_expiry(): void
    {
        $package = Package::query()->create([
            'code' => 'starter-remind',
            'name' => 'Starter Remind',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'RENEW06',
            'name' => 'Renewal Co 6',
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
            'ends_at' => now()->addDays(7),
            'trial_ends_at' => null,
            'auto_renew' => false,
            'billing_cycle' => 'monthly',
            'amount' => 100000,
        ]);

        $job = new SubscriptionRenewalNotifier;
        $job->handle();

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->id,
            'event_type' => 'expiring_soon',
            'reason_code' => 'billing.subscription.expiring_soon',
        ]);
    }

    public function test_inactive_subscriptions_are_skipped(): void
    {
        $package = Package::query()->create([
            'code' => 'starter-skip',
            'name' => 'Starter Skip',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'RENEW07',
            'name' => 'Renewal Co 7',
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
            'status' => 'inactive',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now(),
            'trial_ends_at' => null,
            'auto_renew' => false,
            'billing_cycle' => 'monthly',
            'amount' => 100000,
        ]);

        $job = new SubscriptionRenewalNotifier;
        $job->handle();

        // No invoice should be created for inactive subscriptions
        $this->assertDatabaseMissing('invoices', [
            'company_id' => $company->id,
        ]);
    }

    /**
     * @return array{0: Company, 1: Subscription, 2: User}
     */
    private function createExpiringGraceFixture(string $graceStartedOffset = '-2 days', string $graceEndsOffset = '+1 day'): array
    {
        $package = Package::query()->create([
            'code' => 'starter-grace-fix',
            'name' => 'Starter Grace',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'GRACE'.strtoupper((string) str()->random(2)),
            'name' => 'Grace Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $user = User::query()->create([
            'name' => 'Grace Contact',
            'email' => 'grace@testco.example',
            'password' => bcrypt('secret'),
        ]);
        $company->forceFill(['owner_user_id' => $user->id])->save();

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'grace_period',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addDays(30),
            'trial_ends_at' => null,
            'auto_renew' => false,
            'billing_cycle' => 'monthly',
            'amount' => 100000,
            'grace_started_at' => now()->subDays(2),
            'grace_ends_at' => now()->addDay(),
        ]);

        // Override grace dates
        $subscription->forceFill([
            'grace_started_at' => Carbon::parse($graceStartedOffset),
            'grace_ends_at' => Carbon::parse($graceEndsOffset),
        ])->save();

        return [$company, $subscription, $user];
    }
}
