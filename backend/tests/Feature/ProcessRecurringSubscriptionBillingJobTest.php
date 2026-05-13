<?php

namespace Tests\Feature;

use App\Jobs\ProcessRecurringSubscriptionBilling;
use App\Models\Company;
use App\Models\HcmBillingTaxPolicy;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }
}
