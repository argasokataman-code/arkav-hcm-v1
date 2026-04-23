<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\EmployeeProfile;
use App\Models\HcmPayrollLine;
use App\Models\HcmPayrollPeriod;
use App\Models\HcmSalaryComponent;
use App\Models\Holiday;
use App\Models\HolidayCalendar;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\HcmFeatureFlags;
use App\Support\PayrollDraftBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

/**
 * H3 - Payroll leave/holiday integration feature tests.
 *
 * Memastikan flag `payroll.leave_integration_enabled`:
 *  - default OFF → tidak ada line tambahan (existing behavior).
 *  - ON global → deduction unpaid leave + addition tunjangan kerja libur muncul.
 *  - ON per-tenant via company_settings → sama behavior-nya.
 *  - Tenant A ON, Tenant B OFF → isolasi ketat.
 */
#[IgnoreDeprecations]
class PayrollLeaveHolidayIntegrationTest extends TestCase
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

    private function makeEmployee(int $companyId, string $email, int $base = 3000000, int $fixed = 0): User
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
            'status' => 'open',
        ]);
    }

    private function seedHoliday(int $companyId, string $date, string $name = 'Libur Nasional'): void
    {
        $holiday = Holiday::query()->create([
            'title' => $name,
            'holiday_date' => $date,
            'is_active' => true,
            'source' => 'test',
        ]);
        HolidayCalendar::query()->create([
            'company_id' => $companyId,
            'holiday_id' => $holiday->id,
            'date' => $date,
            'name' => $name,
            'is_national' => true,
            'is_joint_leave' => false,
            'deduct_from_leave' => false,
            'source' => 'test',
        ]);
    }

    public function test_flag_off_by_default_produces_no_leave_holiday_lines(): void
    {
        $company = $this->makeCompany('acme_off');
        $user = $this->makeEmployee($company->id, 'off@example.com', 3100000);

        // Seed data yang seharusnya memicu adjustment kalau flag ON
        LeaveRequest::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'leave_type' => 'unpaid',
            'date_from' => '2026-05-04',
            'date_to' => '2026-05-06',
            'days' => 3,
            'status' => 'approved',
        ]);
        $this->seedHoliday($company->id, '2026-05-01', 'Hari Buruh');
        AttendanceRecord::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'work_date' => '2026-05-01',
            'status' => 'present',
            'check_in_at' => '2026-05-01 08:00:00',
            'check_out_at' => '2026-05-01 17:00:00',
        ]);

        $period = $this->makePeriod($company->id, 2026, 5);
        PayrollDraftBuilder::rebuildDraftRun($period, $company->id);

        $codes = HcmPayrollLine::query()
            ->where('user_id', $user->id)
            ->pluck('component_code')
            ->all();

        $this->assertNotContains('potongan_cuti_unpaid', $codes);
        $this->assertNotContains('tunjangan_kerja_libur', $codes);
    }

    public function test_flag_on_globally_emits_unpaid_leave_deduction_and_holiday_work_addition(): void
    {
        config(['hcm.payroll.leave_integration_enabled' => true]);
        config(['hcm.payroll.holiday_work_multiplier' => 2.0]);

        $company = $this->makeCompany('acme_on');
        $user = $this->makeEmployee($company->id, 'on@example.com', 3000000, 0);

        // Unpaid leave: 2026-05-04 s/d 05-06 = 3 hari kalender, tapi calculator
        // akan hitung hari kerja (Mon-Wed) → 3 hari kerja.
        LeaveRequest::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'leave_type' => 'unpaid',
            'date_from' => '2026-05-04',
            'date_to' => '2026-05-06',
            'days' => 3,
            'status' => 'approved',
        ]);

        // Holiday + attendance pada hari libur (Jumat 2026-05-01)
        $this->seedHoliday($company->id, '2026-05-01', 'Hari Buruh');
        AttendanceRecord::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'work_date' => '2026-05-01',
            'status' => 'present',
            'check_in_at' => '2026-05-01 08:00:00',
            'check_out_at' => '2026-05-01 17:00:00',
        ]);

        $period = $this->makePeriod($company->id, 2026, 5);
        PayrollDraftBuilder::rebuildDraftRun($period, $company->id);

        $unpaid = HcmPayrollLine::query()
            ->where('user_id', $user->id)
            ->where('component_code', 'potongan_cuti_unpaid')
            ->first();
        $this->assertNotNull($unpaid, 'Expected potongan_cuti_unpaid line when flag ON');
        $this->assertSame('deduction', $unpaid->kind);
        $this->assertGreaterThan(0, (float) $unpaid->amount);

        $holidayWork = HcmPayrollLine::query()
            ->where('user_id', $user->id)
            ->where('component_code', 'tunjangan_kerja_libur')
            ->first();
        $this->assertNotNull($holidayWork, 'Expected tunjangan_kerja_libur line when flag ON');
        $this->assertSame('addition', $holidayWork->kind);
        $this->assertGreaterThan(0, (float) $holidayWork->amount);

        // Komponen auto-provisioned di tenant tersebut
        $this->assertTrue(
            HcmSalaryComponent::query()
                ->where('company_id', $company->id)
                ->where('code', 'potongan_cuti_unpaid')
                ->exists()
        );
    }

    public function test_flag_can_be_enabled_per_tenant_via_company_settings(): void
    {
        // Global flag OFF
        config(['hcm.payroll.leave_integration_enabled' => false]);

        $companyOn = $this->makeCompany('tenant_on');
        $companyOff = $this->makeCompany('tenant_off');

        CompanySetting::query()->create([
            'company_id' => $companyOn->id,
            'key' => 'payroll.leave_integration_enabled',
            'value' => '1',
            'type' => 'boolean',
        ]);

        $this->assertTrue(
            (bool) HcmFeatureFlags::forCompany($companyOn->id, 'payroll.leave_integration_enabled', false)
        );
        $this->assertFalse(
            (bool) HcmFeatureFlags::forCompany($companyOff->id, 'payroll.leave_integration_enabled', false)
        );

        $userOn = $this->makeEmployee($companyOn->id, 'tenant-on@example.com', 3000000);
        $userOff = $this->makeEmployee($companyOff->id, 'tenant-off@example.com', 3000000);

        foreach ([$companyOn->id => $userOn, $companyOff->id => $userOff] as $cid => $u) {
            LeaveRequest::query()->create([
                'company_id' => $cid,
                'user_id' => $u->id,
                'leave_type' => 'unpaid',
                'date_from' => '2026-06-02',
                'date_to' => '2026-06-02',
                'days' => 1,
                'status' => 'approved',
            ]);
        }

        $periodOn = $this->makePeriod($companyOn->id, 2026, 6);
        $periodOff = $this->makePeriod($companyOff->id, 2026, 6);
        PayrollDraftBuilder::rebuildDraftRun($periodOn, $companyOn->id);
        PayrollDraftBuilder::rebuildDraftRun($periodOff, $companyOff->id);

        $this->assertTrue(
            HcmPayrollLine::query()->where('user_id', $userOn->id)
                ->where('component_code', 'potongan_cuti_unpaid')->exists(),
            'Tenant ON should emit unpaid deduction'
        );
        $this->assertFalse(
            HcmPayrollLine::query()->where('user_id', $userOff->id)
                ->where('component_code', 'potongan_cuti_unpaid')->exists(),
            'Tenant OFF should NOT emit unpaid deduction'
        );
    }
}
