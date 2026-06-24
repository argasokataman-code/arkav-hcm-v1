<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class HcmPayrollSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Payroll Settings Admin',
            'email' => 'payroll-settings-admin@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', 'payroll-settings-admin@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'team' => 'HR',
                'designation' => 'HR Admin',
                'employment_status' => 'active',
                'base_salary' => 0,
                'fixed_allowance' => 0,
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'payroll-settings-admin@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function workerToken(): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Payroll Settings Worker',
            'email' => 'payroll-settings-worker@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', 'payroll-settings-worker@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'employment_status' => 'active',
                'base_salary' => 4_000_000,
                'fixed_allowance' => 0,
            ],
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'payroll-settings-worker@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    public function test_payroll_settings_forbidden_for_non_admin(): void
    {
        $token = $this->workerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/payroll/settings')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_payroll_settings_forbidden_when_switching_to_unowned_company(): void
    {
        $admin = $this->adminToken();

        Company::query()->create([
            'code' => 'payroll_settings_other_company',
            'name' => 'Payroll Settings Other Company',
            'legal_name' => 'Payroll Settings Other Company LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$admin,
            'X-Company-Code' => 'payroll_settings_other_company',
        ])->getJson('/v1/hcm/payroll/settings')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_FORBIDDEN');
    }

    public function test_payroll_settings_defaults_and_update(): void
    {
        $admin = $this->adminToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll/settings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.paydayDay', 28)
            ->assertJsonPath('data.cutoffOffsetDays', 3)
            ->assertJsonPath('data.payrollTimezone', 'Asia/Jakarta')
            ->assertJsonPath('data.disburseBeforePaydayAllowed', false)
            ->assertJsonPath('data.paydayHolidayStrategy', 'previous_working_day');

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->putJson('/v1/hcm/payroll/settings', [
                'paydayDay' => 27,
                'cutoffOffsetDays' => 2,
                'payrollTimezone' => 'Asia/Makassar',
                'disburseBeforePaydayAllowed' => true,
                'paydayHolidayStrategy' => 'next_working_day',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.paydayDay', 27)
            ->assertJsonPath('data.cutoffOffsetDays', 2)
            ->assertJsonPath('data.payrollTimezone', 'Asia/Makassar')
            ->assertJsonPath('data.disburseBeforePaydayAllowed', true)
            ->assertJsonPath('data.paydayHolidayStrategy', 'next_working_day');

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/payroll/settings')
            ->assertOk()
            ->assertJsonPath('data.paydayDay', 27)
            ->assertJsonPath('data.cutoffOffsetDays', 2)
            ->assertJsonPath('data.payrollTimezone', 'Asia/Makassar')
            ->assertJsonPath('data.disburseBeforePaydayAllowed', true)
            ->assertJsonPath('data.paydayHolidayStrategy', 'next_working_day');
    }

    public function test_payroll_settings_validate_ranges(): void
    {
        $admin = $this->adminToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->putJson('/v1/hcm/payroll/settings', [
                'paydayDay' => 0,
                'cutoffOffsetDays' => 30,
                'payrollTimezone' => 'Invalid/Timezone',
                'paydayHolidayStrategy' => 'unsupported_strategy',
            ])
            ->assertStatus(422)
    }
}
