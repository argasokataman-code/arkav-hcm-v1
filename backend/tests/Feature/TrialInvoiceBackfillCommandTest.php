<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialInvoiceBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_invoice_for_trial_subscription_without_invoice(): void
    {
        $package = Package::query()->create([
            'code' => 'trial-plan',
            'name' => 'Trial Plan',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'TRIALBACKFILL01',
            'name' => 'Trial Backfill Co',
            'legal_name' => null,
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $missingInvoiceSub = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'trial',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->addDays(28),
            'trial_ends_at' => now()->addDays(28),
            'billing_cycle' => 'monthly',
            'amount' => 0,
        ]);

        $existingInvoiceSub = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'trial',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
            'trial_ends_at' => now()->addDays(29),
            'billing_cycle' => 'monthly',
            'amount' => 0,
        ]);

        Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $existingInvoiceSub->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 0,
            'status' => 'draft',
            'notes' => null,
        ]);

        $this->artisan('hcm:billing-backfill-trial-invoices')
            ->assertExitCode(0);

        $this->assertDatabaseHas('invoices', [
            'company_id' => $company->id,
            'subscription_id' => $missingInvoiceSub->id,
            'status' => 'draft',
            'amount_due' => 0,
        ]);

        $this->assertSame(
            1,
            Invoice::query()->where('subscription_id', $existingInvoiceSub->id)->count(),
            'Command must not create duplicate invoice for trial subscription that already has invoice.'
        );
    }

    public function test_command_dry_run_does_not_persist_invoice(): void
    {
        $package = Package::query()->create([
            'code' => 'trial-plan',
            'name' => 'Trial Plan',
            'monthly_price' => 100000,
            'yearly_price' => 1000000,
            'billing_unit' => 'flat',
            'status' => 'active',
        ]);

        $company = Company::query()->create([
            'code' => 'TRIALBACKFILL02',
            'name' => 'Trial Dry Run Co',
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
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
            'trial_ends_at' => now()->addDays(29),
            'billing_cycle' => 'monthly',
            'amount' => 0,
        ]);

        $this->artisan('hcm:billing-backfill-trial-invoices --dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('invoices', [
            'company_id' => $company->id,
            'subscription_id' => $sub->id,
        ]);
    }
}
