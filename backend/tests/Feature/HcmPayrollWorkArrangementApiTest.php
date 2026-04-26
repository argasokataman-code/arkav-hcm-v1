<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class HcmPayrollWorkArrangementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_profile_and_employee_arrangement(): void
    {
        $company = Company::factory()->create(['code' => 'PWARR'.strtoupper(substr(bin2hex(random_bytes(2)), 0, 4))]);

        $adminResult = $this->createHcmAdminWithCompany([
            'name' => 'Payroll Config Admin',
            'email' => 'payroll-config-admin@example.com',
            'password' => 'StrongPass1',
        ], $company);

        $employee = User::factory()->create();
        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'employment_status' => 'active',
            'team' => 'Ops',
            'designation' => 'Staff',
        ]);

        $headers = [
            'Authorization' => 'Bearer '.$adminResult['token'],
            'X-Company-Code' => $company->code,
        ];

        $profileId = (int) $this->withHeaders($headers)
            ->postJson('/v1/hcm/payroll/work-profiles', [
                'code' => 'shift_6d_ops',
                'name' => 'Shift 6 Hari Ops',
                'arrangementMode' => 'shift_worker',
                'defaultDayType' => 'weekly_rest_day',
                'weeklyWorkDays' => 6,
                'isDefault' => true,
            ])
            ->assertStatus(201)
            ->json('data.id');

        $this->withHeaders($headers)
            ->postJson('/v1/hcm/payroll/work-arrangements', [
                'userId' => $employee->id,
                'profileId' => $profileId,
                'arrangementMode' => 'shift_worker',
                'defaultDayType' => 'weekly_rest_day',
                'weeklyWorkDays' => 6,
                'effectiveFrom' => '2026-05-01',
                'effectiveTo' => null,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.userId', $employee->id)
            ->assertJsonPath('data.profileId', $profileId)
            ->assertJsonPath('data.arrangementMode', 'shift_worker')
            ->assertJsonPath('data.weeklyWorkDays', 6);

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/payroll/work-arrangements?userId='.$employee->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }
}
