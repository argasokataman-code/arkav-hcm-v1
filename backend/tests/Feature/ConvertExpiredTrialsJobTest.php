<?php

namespace Tests\Feature;

use App\Jobs\ConvertExpiredTrialsToPendingPaymentJob;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}

