<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmployeeEmploymentHistory;
use App\Models\EmployeeProfile;
use App\Models\Team;
use App\Models\User;
use App\Models\WilayahDistrict;
use App\Models\WilayahProvince;
use App\Models\WilayahRegency;
use App\Models\WilayahVillage;
use App\Notifications\ProbationCycleAdminNotification;
use App\Notifications\ProbationEndedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HcmProbationTest extends TestCase
{
    use RefreshDatabase;

    private ?Company $company = null;

    private function adminBearerToken(): string
    {
        $result = $this->createHcmAdminWithCompany([
            'name' => 'Probation Admin',
            'email' => 'probation-admin@example.com',
            'password' => 'StrongPass1',
        ]);
        $this->company = $result['company'];

        $user = User::query()->where('email', 'probation-admin@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_id' => $this->company->id,
                'team' => 'HR',
                'designation' => 'Manager',
                'employment_status' => 'active',
            ],
        );

        return $result['token'];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validEmployeePayload(array $overrides = []): array
    {
        $department = Department::query()->firstOrCreate(
            ['code' => 'PROB_ENG'],
            ['name' => 'Probation Engineering', 'is_active' => true],
        );
        $designation = Designation::query()->firstOrCreate(
            ['code' => 'PROB_DEV'],
            ['department_id' => $department->id, 'name' => 'Probation Developer', 'is_active' => true],
        );
        $team = Team::query()->firstOrCreate(
            ['company_id' => $this->company?->id, 'name' => 'Probation Team'],
            ['department_id' => $department->id, 'is_active' => true],
        );

        $province = WilayahProvince::query()->firstOrCreate(['code' => '31'], ['name' => 'DKI Jakarta']);
        $regency = WilayahRegency::query()->firstOrCreate(
            ['code' => '31.74'],
            ['province_id' => $province->id, 'name' => 'Jakarta Selatan'],
        );
        $district = WilayahDistrict::query()->firstOrCreate(
            ['code' => '31.74.09'],
            ['regency_id' => $regency->id, 'name' => 'Jagakarsa'],
        );
        $village = WilayahVillage::query()->firstOrCreate(
            ['code' => '31.74.09.1001'],
            ['district_id' => $district->id, 'name' => 'Jagakarsa'],
        );

        return array_merge([
            'name' => 'Probation Employee',
            'email' => 'prob.emp@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
            'data_disclosure_acknowledged' => true,
            'team' => $team->name,
            'teamId' => $team->id,
            'departmentId' => $department->id,
            'designationId' => $designation->id,
            'employeeType' => 'permanent',
            'employmentStatus' => 'probation',
            'phone' => '081234567890',
            'nik' => '3174011708980001',
            'placeOfBirth' => 'Jakarta',
            'dateOfBirth' => '1998-08-17',
            'gender' => 'male',
            'maritalStatus' => 'single',
            'religion' => 'Islam',
            'nationality' => 'Indonesia',
            'address' => null,
            'addressDetail' => 'Jl. Test No. 1',
            'baseSalary' => 5000000,
            'fixedAllowance' => 0,
            'salaryType' => 'monthly',
            'contractType' => 'permanent',
            'contractStatus' => 'active',
            'contractStartDate' => '2025-01-01',
            'startDate' => '2025-01-01',
            'bankName' => 'BCA',
            'bankAccountNo' => '1234567890',
            'bankAccountHolderName' => 'Probation Employee',
            'emergencyContacts' => [
                ['name' => 'Keluarga', 'relationship' => 'Parent', 'phone' => '081234567891'],
            ],
            'provinceId' => $province->id,
            'regencyId' => $regency->id,
            'districtId' => $district->id,
            'villageId' => $village->id,
        ], $overrides);
    }

    // ============================================================
    // Validation: max 12 months
    // ============================================================

    public function test_probation_end_date_beyond_12_months_is_rejected(): void
    {
        $token = $this->adminBearerToken();

        // startDate = 2025-01-01, probationEndDate = 2026-02-01 (13 months) → must fail
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/employees', $this->validEmployeePayload([
            'name' => 'Over Limit',
            'email' => 'overlimit@example.com',
            'startDate' => '2025-01-01',
            'probationEndDate' => '2026-02-01',
        ]))->assertStatus(422);
    }

    public function test_probation_end_date_exactly_12_months_is_accepted(): void
    {
        $token = $this->adminBearerToken();

        // startDate = 2025-01-01, probationEndDate = 2026-01-01 (exactly 12 months) → must pass
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/employees', $this->validEmployeePayload([
            'name' => 'Exact Limit',
            'email' => 'exactlimit@example.com',
            'startDate' => '2025-01-01',
            'probationEndDate' => '2026-01-01',
                 ]))->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_probation_end_date_within_12_months_is_accepted(): void
    {
        $token = $this->adminBearerToken();

        // startDate = 2025-01-01, probationEndDate = 2025-07-01 (6 months) → must pass
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/employees', $this->validEmployeePayload([
            'name' => 'Half Year',
            'email' => 'halfyear@example.com',
            'startDate' => '2025-01-01',
            'probationEndDate' => '2025-07-01',
        ]))->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // ============================================================
    // Probation cycle command: notifications
    // ============================================================

    public function test_probation_cycle_command_sends_employee_and_admin_notifications(): void
    {
        Notification::fake();

        $result = $this->createHcmAdminWithCompany([
            'name' => 'Cycle Admin',
            'email' => 'cycle-admin@example.com',
            'password' => 'StrongPass1',
        ]);
        $company = $result['company'];
        $adminUser = User::query()->where('email', 'cycle-admin@example.com')->firstOrFail();

        // Ensure admin user is linked as active owner/admin in CompanyUser
        CompanyUser::query()->updateOrCreate(
            ['user_id' => $adminUser->id, 'company_id' => $company->id],
            ['role' => 'admin', 'status' => 'active'],
        );

        // Create a probation employee
        $empUser = User::factory()->create([
            'name' => 'Probation Worker',
            'email' => 'probation-worker@example.com',
        ]);

        $profile = EmployeeProfile::query()->create([
            'user_id' => $empUser->id,
            'company_id' => $company->id,
            'company_uuid' => (string) ($company->uuid ?? (string) $company->id),
            'team' => 'Engineering',
            'designation' => 'Developer',
            'employment_status' => 'probation',
            'contract_type' => 'permanent',
        ]);

        $today = now()->toDateString();

        EmployeeEmploymentHistory::query()->create([
            'employee_id' => $profile->id,
            'employment_status' => 'probation',
            'start_date' => now()->subMonths(3)->toDateString(),
            'probation_end_date' => $today,
        ]);

        $exitCode = Artisan::call('hcm:probation-cycle', ['--date' => $today]);

        $this->assertSame(0, $exitCode);

        // Employee notification sent
        Notification::assertSentTo($empUser, ProbationEndedNotification::class, function ($notification): bool {
            return str_contains($notification->companyName, '')
                && $notification->contractType === 'permanent';
        });

        // Admin notification sent
        Notification::assertSentTo($adminUser, ProbationCycleAdminNotification::class);
    }

    public function test_probation_cycle_command_does_not_notify_if_no_probation_ends_today(): void
    {
        Notification::fake();

        $result = $this->createHcmAdminWithCompany([
            'name' => 'No Notif Admin',
            'email' => 'nonotif-admin@example.com',
            'password' => 'StrongPass1',
        ]);
        $company = $result['company'];

        $empUser = User::factory()->create([
            'name' => 'Future Probation',
            'email' => 'future-probation@example.com',
        ]);

        $profile = EmployeeProfile::query()->create([
            'user_id' => $empUser->id,
            'company_id' => $company->id,
            'team' => 'Engineering',
            'employment_status' => 'probation',
            'contract_type' => 'permanent',
        ]);

        // probation_end_date is tomorrow → should not trigger today
        EmployeeEmploymentHistory::query()->create([
            'employee_id' => $profile->id,
            'employment_status' => 'probation',
            'start_date' => now()->subMonths(3)->toDateString(),
            'probation_end_date' => now()->addDay()->toDateString(),
        ]);

        Artisan::call('hcm:probation-cycle', ['--date' => now()->toDateString()]);

        Notification::assertNothingSent();
    }
}
