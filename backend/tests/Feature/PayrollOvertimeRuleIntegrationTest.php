<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\EmployeeProfile;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\Hcm\OvertimePayCalculator;
use App\Support\PayrollDraftBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class PayrollOvertimeRuleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(string $code): Company
    {
        return Company::query()->create([
            'code' => $code.'_'.substr(uniqid(), -6),
            'name' => ucfirst($code).' Co',
            'legal_name' => ucfirst($code).' Ltd',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);
    }

    private function makeEmployee(int $companyId, string $email, int $base = 3_000_000, int $fixed = 0): User
    {
        $user = User::query()->create([
            'company_id' => $companyId,
            'name' => 'Emp '.$email,
            'email' => $email,
            'password' => bcrypt('StrongPass1'),
        ]);

        EmployeeProfile::query()->create([
            'user_id' => $user->id,
            'company_id' => $companyId,
            'team' => 'Ops',
            'designation' => 'Staff',
            'employment_status' => 'active',
            'base_salary' => $base,
            'fixed_allowance' => $fixed,
        ]);

        return $user;
    }

    private function makePeriod(int $companyId, int $year, int $month): HcmPayrollPeriod
    {
        return HcmPayrollPeriod::query()->create([
            'company_id' => $companyId,
            'period_year' => $year,
            'period_month' => $month,
            'status' => HcmPayrollPeriod::STATUS_OPEN,
        ]);
    }

    public function test_payroll_uses_overtime_request_day_type_and_weekly_work_days(): void
    {
        $company = $this->makeCompany('otrule');
        $user = $this->makeEmployee($company->id, 'otrule@example.com', 3_460_000, 0);

        OvertimeRequest::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'request_type' => 'employee_request',
            'work_date' => '2026-05-01',
            'minutes' => 120,
            'day_type' => 'public_holiday',
            'weekly_work_days' => 5,
            'status' => 'approved',
        ]);

        $period = $this->makePeriod($company->id, 2026, 5);
        PayrollDraftBuilder::rebuildDraftRun($period, $company->id);

        $line = HcmPayrollLine::query()
            ->where('user_id', $user->id)
            ->where('component_code', 'upah_lembur')
            ->first();

        $this->assertNotNull($line);

        $calculator = app(OvertimePayCalculator::class);
        $expected = (float) ($calculator->calculate(3_460_000, 0, 120, 'public_holiday', 5)['totalOvertimePay'] ?? 0.0);
        $workdayDefault = (float) ($calculator->calculate(3_460_000, 0, 120, 'workday', 5)['totalOvertimePay'] ?? 0.0);

        $this->assertSame(round($expected, 2), (float) $line->amount);
        $this->assertNotSame(round($workdayDefault, 2), (float) $line->amount);
    }

    public function test_payroll_overtime_query_is_scoped_by_company_id(): void
    {
        $companyA = $this->makeCompany('scopea');
        $companyB = $this->makeCompany('scopeb');
        $user = $this->makeEmployee($companyA->id, 'scope@example.com', 3_000_000, 0);

        OvertimeRequest::query()->create([
            'company_id' => $companyA->id,
            'user_id' => $user->id,
            'request_type' => 'employee_request',
            'work_date' => '2026-05-10',
            'minutes' => 60,
            'day_type' => 'workday',
            'weekly_work_days' => 5,
            'status' => 'approved',
        ]);

        OvertimeRequest::query()->create([
            'company_id' => $companyB->id,
            'user_id' => $user->id,
            'request_type' => 'employee_request',
            'work_date' => '2026-05-11',
            'minutes' => 120,
            'day_type' => 'workday',
            'weekly_work_days' => 5,
            'status' => 'approved',
        ]);

        $period = $this->makePeriod($companyA->id, 2026, 5);
        PayrollDraftBuilder::rebuildDraftRun($period, $companyA->id);

        $line = HcmPayrollLine::query()
            ->where('user_id', $user->id)
            ->where('component_code', 'upah_lembur')
            ->first();

        $this->assertNotNull($line);

        $calculator = app(OvertimePayCalculator::class);
        $expected = (float) ($calculator->calculate(3_000_000, 0, 60, 'workday', 5)['totalOvertimePay'] ?? 0.0);

        $this->assertSame(round($expected, 2), (float) $line->amount);
    }

    public function test_payroll_overtime_excludes_entries_after_cutoff_snapshot(): void
    {
        $company = $this->makeCompany('cutoffot');
        $user = $this->makeEmployee($company->id, 'cutoff-overtime@example.com', 3_000_000, 0);

        CompanySetting::query()->updateOrCreate(
            ['company_id' => $company->id, 'key' => 'payroll.monthly.payday_day'],
            ['value' => '27', 'type' => 'integer'],
        );
        CompanySetting::query()->updateOrCreate(
            ['company_id' => $company->id, 'key' => 'payroll.monthly.cutoff_offset_days'],
            ['value' => '2', 'type' => 'integer'],
        );
        CompanySetting::query()->updateOrCreate(
            ['company_id' => $company->id, 'key' => 'payroll.monthly.payroll_timezone'],
            ['value' => 'Asia/Jakarta', 'type' => 'string'],
        );

        OvertimeRequest::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'request_type' => 'employee_request',
            'work_date' => '2026-05-24',
            'minutes' => 60,
            'day_type' => 'workday',
            'weekly_work_days' => 5,
            'status' => 'approved',
        ]);

        OvertimeRequest::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'request_type' => 'employee_request',
            'work_date' => '2026-05-26',
            'minutes' => 120,
            'day_type' => 'workday',
            'weekly_work_days' => 5,
            'status' => 'approved',
        ]);

        $period = $this->makePeriod($company->id, 2026, 5);
        PayrollDraftBuilder::rebuildDraftRun($period, $company->id);

        $line = HcmPayrollLine::query()
            ->where('user_id', $user->id)
            ->where('component_code', 'upah_lembur')
            ->first();

        $this->assertNotNull($line);

        $calculator = app(OvertimePayCalculator::class);
        $includedBeforeCutoff = (float) ($calculator->calculate(3_000_000, 0, 60, 'workday', 5)['totalOvertimePay'] ?? 0.0);
        $fullWithoutCutoff = (float) ($calculator->calculate(3_000_000, 0, 180, 'workday', 5)['totalOvertimePay'] ?? 0.0);

        $this->assertSame(round($includedBeforeCutoff, 2), (float) $line->amount);
        $this->assertNotSame(round($fullWithoutCutoff, 2), (float) $line->amount);
    }
}
