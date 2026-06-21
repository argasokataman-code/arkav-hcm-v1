<?php

namespace Tests\Feature;

use App\Jobs\ClearRevenueTransactionsJob;
use App\Models\Company;
use App\Models\PlatformRevenueTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxGovernanceRevenueClearingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_clears_posted_uncleared_transactions_past_grace_window(): void
    {
        $company = Company::factory()->create();

        $row = PlatformRevenueTransaction::query()->create([
            'company_id' => $company->id,
            'source_event_type' => 'subscription.created',
            'source_entity_type' => 'subscriptions',
            'source_entity_id' => 1001,
            'transaction_type' => PlatformRevenueTransaction::TYPE_SUBSCRIPTION,
            'amount' => 100000,
            'tax_amount' => 0,
            'net_amount' => 100000,
            'status' => PlatformRevenueTransaction::STATUS_POSTED,
            'clearing_status' => PlatformRevenueTransaction::CLEARING_UNCLEARED,
            'occurred_at' => now()->subDays(3),
            'idempotency_key' => 'test-clearing-1',
        ]);

        (new ClearRevenueTransactionsJob)->handle();

        $this->assertDatabaseHas('platform_revenue_transactions', [
            'id' => $row->id,
            'clearing_status' => PlatformRevenueTransaction::CLEARING_CLEARED,
        ]);
    }

    public function test_job_is_idempotent_and_keeps_non_uncleared_rows_unchanged(): void
    {
        $company = Company::factory()->create();

        $cleared = PlatformRevenueTransaction::query()->create([
            'company_id' => $company->id,
            'source_event_type' => 'addon.purchased',
            'source_entity_type' => 'purchase_transactions',
            'source_entity_id' => 2001,
            'transaction_type' => PlatformRevenueTransaction::TYPE_ADDON_FEATURE,
            'amount' => 55000,
            'tax_amount' => 5000,
            'net_amount' => 50000,
            'status' => PlatformRevenueTransaction::STATUS_POSTED,
            'clearing_status' => PlatformRevenueTransaction::CLEARING_CLEARED,
            'clearing_date' => now()->toDateString(),
            'occurred_at' => now()->subDays(4),
            'idempotency_key' => 'test-clearing-2',
        ]);

        (new ClearRevenueTransactionsJob)->handle();
        (new ClearRevenueTransactionsJob)->handle();

        $this->assertDatabaseHas('platform_revenue_transactions', [
            'id' => $cleared->id,
            'clearing_status' => PlatformRevenueTransaction::CLEARING_CLEARED,
        ]);
    }
}
