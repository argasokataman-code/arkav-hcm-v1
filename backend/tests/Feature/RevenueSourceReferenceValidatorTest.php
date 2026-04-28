<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Models\Package;
use App\Models\PurchaseTransaction;
use App\Models\Subscription;
use App\Services\RevenueSourceReferenceValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RevenueSourceReferenceValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_validator_accepts_valid_subscription_reference(): void
    {
        $company = Company::factory()->create();
        $package = Package::factory()->create(['status' => 'active']);
        $subscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
        ]);

        $validator = app(RevenueSourceReferenceValidator::class);
        $validator->assertValid(
            'subscriptions',
            (int) $subscription->id,
            (string) $subscription->uuid,
            (int) $company->id
        );

        $this->assertTrue(true);
    }

    public function test_validator_rejects_mismatched_uuid_reference(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Revenue source reference mismatch');

        $company = Company::factory()->create();
        $package = Package::factory()->create(['status' => 'active']);
        $subscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
        ]);

        app(RevenueSourceReferenceValidator::class)->assertValid(
            'subscriptions',
            (int) $subscription->id,
            (string) fake()->uuid(),
            (int) $company->id
        );
    }

    public function test_validator_rejects_unknown_source_entity_type(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported source_entity_type');

        app(RevenueSourceReferenceValidator::class)->assertValid(
            'unknown_source',
            1,
            (string) fake()->uuid(),
            1
        );
    }

    public function test_validator_rejects_missing_reference_fields(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('source_entity_id/source_entity_uuid is required');

        app(RevenueSourceReferenceValidator::class)->assertValid(
            'subscriptions',
            null,
            null,
            1
        );
    }

    public function test_validator_accepts_valid_payroll_and_addon_source_reference(): void
    {
        $company = Company::factory()->create();
        $period = HcmPayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_year' => 2026,
            'period_month' => 4,
            'status' => HcmPayrollPeriod::STATUS_POSTED,
        ]);

        $run = HcmPayrollRun::query()->create([
            'company_id' => $company->id,
            'hcm_payroll_period_id' => $period->id,
            'purpose' => HcmPayrollRun::PURPOSE_MONTHLY,
            'status' => HcmPayrollRun::STATUS_FINALIZED,
            'finalized_at' => now(),
        ]);

        $package = Package::factory()->create(['status' => 'active']);
        $subscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
        ]);

        $transaction = PurchaseTransaction::query()->create([
            'transaction_code' => PurchaseTransaction::generateCode(),
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'transaction_type' => 'addon',
            'description' => 'Addon purchase',
            'amount' => 100000,
            'tax_amount' => 10000,
            'discount_amount' => 0,
            'total_amount' => 110000,
            'status' => 'paid',
        ]);

        $validator = app(RevenueSourceReferenceValidator::class);
        $validator->assertValid('hcm_payroll_runs', (int) $run->id, (string) $run->uuid, (int) $company->id);
        $validator->assertValid('purchase_transactions', (int) $transaction->id, (string) $transaction->uuid, (int) $company->id);

        $this->assertTrue(true);
    }
}
