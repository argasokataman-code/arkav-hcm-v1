<?php

namespace Tests\Feature;

use App\Jobs\ConvertExpiredTrialsToPendingPaymentJob;
use App\Models\Company;
use App\Models\HcmBillingTaxPolicy;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConvertExpiredTrialsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_invoice_and_moves_trial_to_pending_payment(): void
    {
        $package = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'TRIAL01',
            'name' => 'Trial Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $sub = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'trial',
            'starts_at' => now()->subDays(40),
            'ends_at' => now()->subDays(10),
            'trial_ends_at' => now()->subDay(),
            'billing_cycle' => 'monthly',
            'amount' => 100000,
        ]);

        dispatch_sync(new ConvertExpiredTrialsToPendingPaymentJob());

        $sub->refresh();
        $this->assertSame('pending_payment', $sub->status);
        $this->assertNull($sub->trial_ends_at);
        $this->assertNotNull($sub->ends_at);

        $this->assertDatabaseHas('invoices', [
            'company_id' => $company->id,
            'subscription_id' => $sub->id,
            'is_paid' => 0,
        ]);

        $invoice = Invoice::query()->where('subscription_id', $sub->id)->firstOrFail();
        $this->assertSame(now()->addDay()->toDateString(), $invoice->due_date->toDateString());
    }

    public function test_job_is_idempotent_for_existing_unpaid_invoice(): void
    {
        $package = Package::query()->create([
            'code' => 'starter',
            'name' => 'Starter',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'TRIAL02',
            'name' => 'Trial Co 2',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $sub = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'trial',
            'starts_at' => now()->subDays(40),
            'ends_at' => now()->subDays(10),
            'trial_ends_at' => now()->subDay(),
            'billing_cycle' => 'monthly',
            'amount' => 100000,
        ]);

        Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $sub->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 100000,
            'notes' => null,
        ]);

        dispatch_sync(new ConvertExpiredTrialsToPendingPaymentJob());
        dispatch_sync(new ConvertExpiredTrialsToPendingPaymentJob());

        $this->assertSame(1, Invoice::query()->where('subscription_id', $sub->id)->count());
    }

    public function test_job_applies_tax_snapshot_to_trial_conversion_invoice_amount(): void
    {
        $package = Package::query()->create([
            'code' => 'starter-taxed',
            'name' => 'Starter Taxed',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'TRIAL03',
            'name' => 'Trial Co 3',
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

        $sub = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'trial',
            'starts_at' => now()->subDays(40),
            'ends_at' => now()->subDays(10),
            'trial_ends_at' => now()->subDay(),
            'billing_cycle' => 'monthly',
            'amount' => 100000,
        ]);

        dispatch_sync(new ConvertExpiredTrialsToPendingPaymentJob());

        $invoice = Invoice::query()->where('subscription_id', $sub->id)->firstOrFail();
        $this->assertEqualsWithDelta(111000, (float) $invoice->amount_due, 0.01);
        $this->assertEqualsWithDelta(11, (float) ($invoice->billing_tax_rate_snapshot ?? 0), 0.01);
        $this->assertStringContainsString('"source":"trial_expiry_conversion"', (string) $invoice->notes);
    }
}

