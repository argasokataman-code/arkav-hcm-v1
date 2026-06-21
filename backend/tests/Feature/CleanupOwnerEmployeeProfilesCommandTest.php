<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CompanyUser;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class CleanupOwnerEmployeeProfilesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_command_backfills_owner_settings_and_deletes_orphan_owner_employee_profile(): void
    {
        $user = User::query()->create([
            'name' => 'Legacy Owner',
            'email' => 'legacy.owner@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $company = Company::query()->create([
            'code' => 'legacy_owner_co',
            'name' => 'Legacy Owner Co',
            'legal_name' => 'Legacy Owner Co LLC',
            'status' => 'active',
            'owner_user_id' => $user->id,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ]);

        $profile = EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'phone' => '081234567890',
            'address' => 'Jl. Legacy Owner 1',
            'address_detail' => 'Bandung',
        ]);

        $this->artisan('hcm:cleanup-owner-employee-profiles', ['--companyId' => $company->id, '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('employee_profiles', ['id' => $profile->id]);
        $this->assertDatabaseMissing('company_settings', ['company_id' => $company->id, 'key' => 'owner_phone']);

        $this->artisan('hcm:cleanup-owner-employee-profiles', ['--companyId' => $company->id])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('employee_profiles', ['id' => $profile->id]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'owner_phone',
            'value' => '081234567890',
        ]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'owner_address',
            'value' => 'Jl. Legacy Owner 1',
        ]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'owner_address_detail',
            'value' => 'Bandung',
        ]);
    }

    public function test_cleanup_command_skips_owner_profiles_with_hr_dependencies(): void
    {
        $user = User::query()->create([
            'name' => 'Owner With Assignment',
            'email' => 'owner.assignment@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $company = Company::query()->create([
            'code' => 'owner_assignment_co',
            'name' => 'Owner Assignment Co',
            'legal_name' => 'Owner Assignment Co LLC',
            'status' => 'active',
            'owner_user_id' => $user->id,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ]);

        $profile = EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'phone' => '081299999999',
        ]);

        EmployeeAssignment::query()->create([
            'employee_id' => $profile->id,
            'is_primary' => true,
            'start_date' => now()->toDateString(),
        ]);

        CompanySetting::query()->create([
            'company_id' => $company->id,
            'key' => 'owner_phone',
            'value' => 'existing-owner-phone',
            'type' => 'string',
        ]);

        $this->artisan('hcm:cleanup-owner-employee-profiles', ['--companyId' => $company->id])
            ->assertExitCode(0);

        $this->assertDatabaseHas('employee_profiles', ['id' => $profile->id]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'key' => 'owner_phone',
            'value' => 'existing-owner-phone',
        ]);
    }

    public function test_cleanup_command_skips_protected_global_admin_owners(): void
    {
        config()->set('hcm.admin_email', 'qa.login@example.com');

        $user = User::query()->create([
            'name' => 'Protected Global Admin',
            'email' => 'qa.login@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $company = Company::query()->create([
            'code' => 'protected_global_admin_co',
            'name' => 'Protected Global Admin Co',
            'legal_name' => 'Protected Global Admin Co LLC',
            'status' => 'active',
            'owner_user_id' => $user->id,
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ]);

        $profile = EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'designation' => 'Super Admin',
        ]);

        $this->artisan('hcm:cleanup-owner-employee-profiles', ['--companyId' => $company->id])
            ->assertExitCode(0);

        $this->assertDatabaseHas('employee_profiles', ['id' => $profile->id]);
    }
}
