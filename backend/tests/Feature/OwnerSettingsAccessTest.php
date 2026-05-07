<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerSettingsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_is_redirected_from_profile_settings_to_company_profile(): void
    {
        [$owner, $company] = $this->createOwnerTenantContext();

        $this->actingAs($owner)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/profile-settings')
            ->assertRedirectToRoute('company-profile');
    }

    public function test_owner_company_profile_becomes_single_settings_entrypoint(): void
    {
        [$owner, $company] = $this->createOwnerTenantContext();

        $response = $this->actingAs($owner)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/company-profile');

        $response->assertOk();
        $response->assertSee('Owner Account', false);
        $response->assertDontSee('href="'.url('profile-settings').'"', false);
        $response->assertSee('href="'.url('company-profile').'"', false);
    }

    public function test_member_still_can_access_profile_settings(): void
    {
        $company = $this->createCompany('member_settings_company', null);
        $member = User::factory()->create([
            'email' => 'member-settings@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now()->subDay(),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $member->id,
            'employment_status' => 'active',
            'designation' => 'Staff',
            'team' => 'Operations',
            'nik' => 'EMP-OWNER-SETTINGS-001',
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($member)
            ->withHeader('X-Company-Code', $company->code)
            ->get('/profile-settings')
            ->assertOk()
            ->assertSee('Profile Settings', false);
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function createOwnerTenantContext(): array
    {
        $owner = User::factory()->create([
            'email' => 'owner-settings@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        $company = $this->createCompany('owner_settings_company', $owner->id);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now()->subDay(),
        ]);

        return [$owner, $company];
    }

    private function createCompany(string $code, ?int $ownerUserId): Company
    {
        return Company::query()->create([
            'code' => $code,
            'name' => 'Settings Access Company',
            'legal_name' => 'Settings Access Company PT',
            'owner_user_id' => $ownerUserId,
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);
    }
}