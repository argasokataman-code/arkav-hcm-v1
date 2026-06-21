<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmPayrollRun;
use App\Services\Hcm\RefreshOpenPayrollDraftsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class RefreshOpenPayrollDraftsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_rebuilds_open_periods_for_multiple_tenants_before_cutoff(): void
    {
        $companyA = Company::query()->create([
            'code' => 'refresh_tenant_a',
            'name' => 'Refresh Tenant A',
            'legal_name' => 'Refresh Tenant A LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);
        $companyB = Company::query()->create([
            'code' => 'refresh_tenant_b',
            'name' => 'Refresh Tenant B',
            'legal_name' => 'Refresh Tenant B LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'Asia/Makassar',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        foreach ([$companyA, $companyB] as $company) {
            CompanySetting::query()->create([
                'company_id' => $company->id,
                'key' => 'payroll.monthly.payday_day',
                'value' => '28',
                'type' => 'integer',
            ]);
            CompanySetting::query()->create([
                'company_id' => $company->id,
                'key' => 'payroll.monthly.cutoff_offset_days',
                'value' => '3',
                'type' => 'integer',
            ]);
        }

        $periodA = HcmPayrollPeriod::query()->create([
            'company_id' => $companyA->id,
            'period_year' => 2026,
            'period_month' => 3,
            'status' => HcmPayrollPeriod::STATUS_OPEN,
        ]);
        $periodB = HcmPayrollPeriod::query()->create([
            'company_id' => $companyB->id,
            'period_year' => 2026,
            'period_month' => 3,
            'status' => HcmPayrollPeriod::STATUS_OPEN,
        ]);

        $result = app(RefreshOpenPayrollDraftsService::class)->refresh(
            CarbonImmutable::parse('2026-03-24 00:00:00', 'Asia/Jakarta')
        );

        $this->assertSame([$periodA->id, $periodB->id], $result['refreshedPeriodIds']);
        $this->assertCount(2, HcmPayrollRun::query()->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY)->get());
    }

    public function test_refresh_skips_open_period_after_cutoff(): void
    {
        $company = Company::query()->create([
            'code' => 'refresh_after_cutoff',
            'name' => 'Refresh After Cutoff',
            'legal_name' => 'Refresh After Cutoff LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        CompanySetting::query()->create([
            'company_id' => $company->id,
            'key' => 'payroll.monthly.payday_day',
            'value' => '28',
            'type' => 'integer',
        ]);
        CompanySetting::query()->create([
            'company_id' => $company->id,
            'key' => 'payroll.monthly.cutoff_offset_days',
            'value' => '3',
            'type' => 'integer',
        ]);

        $period = HcmPayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_year' => 2026,
            'period_month' => 3,
            'status' => HcmPayrollPeriod::STATUS_OPEN,
        ]);

        $result = app(RefreshOpenPayrollDraftsService::class)->refresh(
            CarbonImmutable::parse('2026-03-26 00:00:00', 'Asia/Jakarta')
        );

        $this->assertSame([$period->id], $result['skippedAfterCutoffPeriodIds']);
        $this->assertCount(0, HcmPayrollRun::query()->where('purpose', HcmPayrollRun::PURPOSE_MONTHLY)->get());
    }

    public function test_refresh_skips_period_with_finalized_monthly_run(): void
    {
        $company = Company::query()->create([
            'code' => 'refresh_finalized_company',
            'name' => 'Refresh Finalized Company',
            'legal_name' => 'Refresh Finalized Company LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $period = HcmPayrollPeriod::query()->create([
            'company_id' => $company->id,
            'period_year' => 2026,
            'period_month' => 3,
            'status' => HcmPayrollPeriod::STATUS_OPEN,
        ]);

        HcmPayrollRun::query()->create([
            'company_id' => $company->id,
            'hcm_payroll_period_id' => $period->id,
            'purpose' => HcmPayrollRun::PURPOSE_MONTHLY,
            'status' => HcmPayrollRun::STATUS_FINALIZED,
            'finalized_at' => now(),
        ]);

        $result = app(RefreshOpenPayrollDraftsService::class)->refresh(
            CarbonImmutable::parse('2026-03-20 00:00:00', 'Asia/Jakarta')
        );

        $this->assertSame([$period->id], $result['skippedFinalizedPeriodIds']);
    }
}
